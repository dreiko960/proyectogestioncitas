<?php

namespace App\Http\Controllers\Api;

use App\Enums\AuditSev;
use App\Http\Controllers\ApiController;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\LogoutRequest;
use App\Http\Requests\RefreshRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuditService;
use App\Services\AuthService;
use App\Services\DniService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends ApiController
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly AuditService $audit,
        private readonly DniService $dni,
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $user = $this->auth->attempt($request->email, $request->password);
        } catch (AuthenticationException $e) {
            return $this->error($e->getMessage(), 403);
        }

        if (! $user) {
            $this->audit->record(null, 'login fallido', "email={$request->email}", AuditSev::Warning);

            return $this->error('Credenciales incorrectas.', 401);
        }

        $tokens = $this->auth->issueTokens($user);
        $this->audit->record($user, 'login', 'Inicio de sesión exitoso');

        return $this->success([
            'user' => new UserResource($user),
            ...$tokens,
        ]);
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $dni = $this->dni->lookup($request->dni);

        if (! $dni['valid']) {
            return $this->error('DNI no válido según RENIEC.', 422);
        }

        $user = $this->auth->registerPatient($request->validated());
        $tokens = $this->auth->issueTokens($user);
        $this->audit->record($user, 'Registro de paciente', "dni={$request->dni}");

        return $this->success([
            'user' => new UserResource($user),
            ...$tokens,
        ], 201);
    }

    public function logout(LogoutRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($request->filled('refresh_token')) {
            $this->auth->revokeRefreshToken($user, $request->refresh_token);
        }

        $user->currentAccessToken()?->delete();
        $this->audit->record($user, 'logout', 'Cierre de sesión');

        return $this->noContent();
    }

    public function refresh(RefreshRequest $request): JsonResponse
    {
        $user = User::query()
            ->whereHas('refreshTokens', fn ($q) => $q->where('token_hash', hash('sha256', $request->refresh_token)))
            ->first();

        if (! $user) {
            return $this->error('Refresh token inválido.', 401);
        }

        try {
            $tokens = $this->auth->rotateRefreshToken($user, $request->refresh_token);
        } catch (AuthenticationException $e) {
            $this->audit->warning($user, 'Refresh token reutilizado', $e->getMessage());

            return $this->error($e->getMessage(), 401);
        }

        $this->audit->record($user, 'refresh', 'Tokens rotados');

        return $this->success([
            'user' => new UserResource($user->fresh()),
            ...$tokens,
        ]);
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->email)->first();

        if (! $user) {
            return $this->success(['message' => 'Si el correo existe, recibirá un enlace para recuperar su contraseña.']);
        }

        $token = $this->auth->createPasswordResetToken($user);

        if (config('app.debug')) {
            $resetUrl = config('app.frontend_url').'/recuperar/nueva-password?token='.$token.'&email='.urlencode($user->email);
            $this->audit->record($user, 'Solicitud de recuperación', 'Enlace generado');

            return $this->success(['message' => 'Enlace enviado.', 'reset_url' => $resetUrl]);
        }

        $this->audit->record($user, 'Solicitud de recuperación', 'Enlace enviado por correo');

        return $this->success(['message' => 'Si el correo existe, recibirá un enlace para recuperar su contraseña.']);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $user = $this->auth->consumePasswordResetToken($request->email, $request->token);

        if (! $user) {
            return $this->error('Token inválido o expirado.', 422);
        }

        $this->auth->resetPassword($user, $request->password);
        $this->audit->record($user, 'Contraseña restablecida');

        return $this->success(['message' => 'Contraseña restablecida correctamente.']);
    }

    public function me(Request $request): JsonResponse
    {
        return $this->success(new UserResource($request->user()));
    }
}
