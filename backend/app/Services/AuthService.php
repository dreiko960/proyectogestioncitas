<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Patient;
use App\Models\RefreshToken;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthService
{
    public function attempt(string $email, string $password): ?User
    {
        $user = User::query()->where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password_hash)) {
            return null;
        }

        if (! $user->active) {
            throw new AuthenticationException('Cuenta desactivada. Contacte con el administrador.');
        }

        $user->forceFill(['last_login_at' => now()])->save();

        return $user;
    }

    /**
     * @return array{access_token: string, token_type: string, expires_in: int, refresh_token: string}
     */
    public function issueTokens(User $user): array
    {
        $accessTtl = (int) config('sanctum.expiration', 15);

        $access = $user->createToken(
            'access',
            [$user->role->ability()],
            now()->addMinutes($accessTtl),
        );

        return [
            'access_token' => $access->plainTextToken,
            'token_type' => 'Bearer',
            'expires_in' => $accessTtl * 60,
            'refresh_token' => $this->createRefreshToken($user),
        ];
    }

    /**
     * @return array{access_token: string, token_type: string, expires_in: int, refresh_token: string}
     */
    public function rotateRefreshToken(User $user, string $plainToken): array
    {
        $hash = $this->hash($plainToken);

        $stored = RefreshToken::query()
            ->where('user_id', $user->id)
            ->where('token_hash', $hash)
            ->first();

        if (! $stored) {
            throw new AuthenticationException('Refresh token inválido.');
        }

        if ($stored->revoked_at !== null) {
            $this->revokeFamily($user);

            throw new AuthenticationException('Refresh token reutilizado. Se revocaron todas las sesiones.');
        }

        if ($stored->expires_at->isPast()) {
            throw new AuthenticationException('Refresh token expirado.');
        }

        $stored->forceFill(['revoked_at' => now()])->save();

        return $this->issueTokens($user);
    }

    public function revokeRefreshToken(User $user, string $plainToken): void
    {
        RefreshToken::query()
            ->where('user_id', $user->id)
            ->where('token_hash', $this->hash($plainToken))
            ->update(['revoked_at' => now()]);
    }

    public function registerPatient(array $data): User
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password_hash' => $data['password'],
            'role' => UserRole::Paciente,
            'active' => true,
        ]);

        Patient::create([
            'user_id' => $user->id,
            'dni' => $data['dni'],
            'phone' => $data['phone'] ?? null,
            'dob' => $data['dob'],
            'address' => $data['address'] ?? null,
            'consent_29733' => true,
            'consent_at' => now(),
        ]);

        return $user->fresh();
    }

    public function createPasswordResetToken(User $user): string
    {
        $plain = Str::random(64);
        $minutes = (int) Setting::get('tokenExpiryMin', 30);

        app('db')->table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            [
                'token' => $this->hash($plain),
                'expires_at' => now()->addMinutes($minutes),
                'created_at' => now(),
            ],
        );

        return $plain;
    }

    public function consumePasswordResetToken(string $email, string $plainToken): ?User
    {
        $row = app('db')->table('password_reset_tokens')
            ->where('email', $email)
            ->where('token', $this->hash($plainToken))
            ->first();

        if (! $row || $row->expires_at < now()) {
            return null;
        }

        app('db')->table('password_reset_tokens')->where('email', $email)->delete();

        return User::query()->where('email', $email)->first();
    }

    public function resetPassword(User $user, string $password): void
    {
        $user->forceFill(['password_hash' => Hash::make($password)])->save();

        RefreshToken::query()->where('user_id', $user->id)->update(['revoked_at' => now()]);
    }

    private function createRefreshToken(User $user): string
    {
        $plain = bin2hex(random_bytes(48));
        $ttlDays = (int) config('auth.refresh_token_ttl_days', 30);

        RefreshToken::create([
            'user_id' => $user->id,
            'token_hash' => $this->hash($plain),
            'expires_at' => now()->addDays($ttlDays),
            'ip' => request()->ip(),
            'user_agent' => mb_substr((string) request()->userAgent(), 0, 250),
        ]);

        return $plain;
    }

    private function revokeFamily(User $user): void
    {
        RefreshToken::query()
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    private function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
