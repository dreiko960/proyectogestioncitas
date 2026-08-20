<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\WaitlistStatus;
use App\Models\Consultorio;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Patient;
use App\Models\Specialty;
use App\Models\User;
use App\Models\WaitlistEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WaitlistTest extends TestCase
{
    use RefreshDatabase;

    private Doctor $doctor;

    private User $patientUser;

    protected function setUp(): void
    {
        parent::setUp();

        $specialty = Specialty::query()->create([
            'code' => 'derma',
            'name' => 'Dermatología',
            'icon' => 'skin',
            'price' => 100,
            'active' => true,
        ]);

        $consultorio = Consultorio::query()->create(['nombre' => 'C-301', 'piso' => '3', 'activo' => true]);

        $this->doctor = Doctor::query()->create([
            'user_id' => User::query()->create([
                'name' => 'Dr. Espera',
                'email' => 'espera@test.local',
                'password_hash' => Hash::make('Demo1234'),
                'role' => UserRole::Medico,
                'active' => true,
            ])->id,
            'initials' => 'DE',
            'specialty_id' => $specialty->id,
            'consultorio_id' => $consultorio->id,
            'exp' => 2,
        ]);

        DoctorSchedule::query()->create([
            'doctor_id' => $this->doctor->id,
            'day_of_week' => now()->addDay()->dayOfWeek,
            'start_time' => '08:00',
            'end_time' => '18:00',
        ]);

        $this->patientUser = User::query()->create([
            'name' => 'Paciente Espera',
            'email' => 'paciente.espera@test.local',
            'password_hash' => Hash::make('Demo1234'),
            'role' => UserRole::Paciente,
            'active' => true,
        ]);

        Patient::query()->create([
            'user_id' => $this->patientUser->id,
            'dni' => '99887766',
            'dob' => '1978-03-03',
            'consent_29733' => true,
            'consent_at' => now(),
        ]);
    }

    private function token(string $email): string
    {
        $token = $this->postJson('/api/auth/login', ['email' => $email, 'password' => 'Demo1234'])
            ->json('data.access_token');

        Auth::forgetGuards();

        return $token;
    }

    private function enrollEntry(int $position, WaitlistStatus $status = WaitlistStatus::EnEspera): WaitlistEntry
    {
        return WaitlistEntry::query()->create([
            'code' => 'WL-'.sprintf('%03d', $position),
            'patient_id' => $this->patientUser->patient->id,
            'doctor_id' => $this->doctor->id,
            'specialty_id' => $this->doctor->specialty_id,
            'position' => $position,
            'status' => $status,
            'confirm_window_min' => 15,
            'enrolled_at' => now()->addMinutes(-$position),
        ]);
    }

    public function test_enroll_creates_waitlist_entry_with_position(): void
    {
        $this->withToken($this->token('paciente.espera@test.local'))
            ->postJson('/api/waitlist', [
                'specialtyId' => $this->doctor->specialty_id,
                'doctorId' => $this->doctor->id,
                'preferred' => 'Mañanas',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'en_espera')
            ->assertJsonPath('data.position', 1)
            ->assertJsonPath('data.preferred', 'Mañanas');
    }

    public function test_offer_then_confirm_reserves_appointment_with_pending_payment(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin.espera@test.local',
            'password_hash' => Hash::make('Demo1234'),
            'role' => UserRole::Administrador,
            'active' => true,
        ]);

        $entry = $this->enrollEntry(1);

        $this->withToken($this->token('admin.espera@test.local'))
            ->postJson("/api/waitlist/{$entry->id}/offer", [
                'date' => now()->addDay()->toDateString(),
                'time' => '15:30',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'oferta')
            ->assertJsonPath('data.offer.time', '15:30:00');

        $patientToken = $this->token('paciente.espera@test.local');
        dump('TOKENS-FULL:', DB::table('personal_access_tokens')->get()->toArray());
        dump('TOKEN-PREFIX:', substr((string) $patientToken, 0, 60));
        $this->withToken($patientToken)->getJson('/api/auth/me')->dump();
        $confirmed = $this->withToken($patientToken)
            ->postJson("/api/waitlist/{$entry->id}/confirm")
            ->dump()
            ->assertOk()
            ->json('data');

        $this->assertSame('confirmada', $confirmed['entry']['status']);
        $this->assertDatabaseHas('appointments', [
            'id' => $confirmed['appointment_id'],
            'status' => 'agendada',
        ]);
        $this->assertDatabaseHas('payments', [
            'appointment_id' => $confirmed['appointment_id'],
            'status' => 'pendiente_verificacion',
        ]);
    }

    public function test_expired_offer_rejects_confirmation_with_410(): void
    {
        $entry = $this->enrollEntry(1);
        $entry->update([
            'status' => WaitlistStatus::Oferta,
            'offer_date' => now()->addDay()->toDateString(),
            'offer_time' => '14:00',
            'offer_expires_at' => now()->subMinutes(5),
        ]);

        $this->withToken($this->token('paciente.espera@test.local'))
            ->postJson("/api/waitlist/{$entry->id}/confirm")
            ->assertStatus(410);

        $this->assertDatabaseHas('waitlist_entries', [
            'id' => $entry->id,
            'status' => 'oferta',
        ]);
    }

    public function test_reject_returns_entry_to_waiting(): void
    {
        $entry = $this->enrollEntry(1);
        $entry->update([
            'status' => WaitlistStatus::Oferta,
            'offer_date' => now()->addDay()->toDateString(),
            'offer_time' => '14:00',
            'offer_expires_at' => now()->addMinutes(15),
        ]);

        $this->withToken($this->token('paciente.espera@test.local'))
            ->postJson("/api/waitlist/{$entry->id}/reject")
            ->assertOk()
            ->assertJsonPath('data.status', 'en_espera')
            ->assertJsonPath('data.offer', null);
    }

    public function test_expire_promotes_next_in_line(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin 2',
            'email' => 'admin2.espera@test.local',
            'password_hash' => Hash::make('Demo1234'),
            'role' => UserRole::Administrador,
            'active' => true,
        ]);

        $w1 = $this->enrollEntry(1);

        $other = User::query()->create([
            'name' => 'Otro Paciente',
            'email' => 'otro.espera@test.local',
            'password_hash' => Hash::make('Demo1234'),
            'role' => UserRole::Paciente,
            'active' => true,
        ]);
        Patient::query()->create([
            'user_id' => $other->id,
            'dni' => '11112222',
            'dob' => '1995-05-05',
            'consent_29733' => true,
            'consent_at' => now(),
        ]);

        $w2 = WaitlistEntry::query()->create([
            'code' => 'WL-002',
            'patient_id' => $other->patient->id,
            'doctor_id' => $this->doctor->id,
            'specialty_id' => $this->doctor->specialty_id,
            'position' => 2,
            'status' => WaitlistStatus::EnEspera,
            'confirm_window_min' => 15,
            'enrolled_at' => now(),
        ]);

        $this->withToken($this->token('admin2.espera@test.local'))
            ->postJson("/api/waitlist/{$w1->id}/offer", [
                'date' => now()->addDay()->toDateString(),
                'time' => '10:00',
            ])
            ->assertOk();

        $this->withToken($this->token('admin2.espera@test.local'))
            ->postJson("/api/waitlist/{$w1->id}/expire")
            ->assertOk();

        $this->assertDatabaseHas('waitlist_entries', ['id' => $w1->id, 'status' => 'expirada']);
        $this->assertDatabaseHas('waitlist_entries', ['id' => $w2->id, 'position' => 1]);
    }
}
