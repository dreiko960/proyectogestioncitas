<?php

namespace Tests\Feature;

use App\Enums\AuditSev;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Consultorio;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Patient;
use App\Models\Specialty;
use App\Models\User;
use App\Models\UserNotice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $patientUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::query()->create([
            'name' => 'Admin Root',
            'email' => 'admin@test.local',
            'password_hash' => Hash::make('Demo1234'),
            'role' => UserRole::Administrador,
            'active' => true,
        ]);

        $this->patientUser = User::query()->create([
            'name' => 'Paciente Admin',
            'email' => 'paciente.admin@test.local',
            'password_hash' => Hash::make('Demo1234'),
            'role' => UserRole::Paciente,
            'active' => true,
        ]);

        Patient::query()->create([
            'user_id' => $this->patientUser->id,
            'dni' => '55667788',
            'dob' => '1988-08-08',
            'consent_29733' => true,
            'consent_at' => now(),
        ]);
    }

    private function adminToken(): string
    {
        $token = $this->postJson('/api/auth/login', ['email' => 'admin@test.local', 'password' => 'Demo1234'])
            ->json('data.access_token');

        Auth::forgetGuards();

        return $token;
    }

    private function patientToken(): string
    {
        $token = $this->postJson('/api/auth/login', ['email' => 'paciente.admin@test.local', 'password' => 'Demo1234'])
            ->json('data.access_token');

        Auth::forgetGuards();

        return $token;
    }

    public function test_admin_creates_doctor_user_with_generated_password(): void
    {
        $specialty = Specialty::query()->create([
            'code' => 'gineco',
            'name' => 'Ginecología',
            'icon' => 'f',
            'price' => 90,
            'active' => true,
        ]);

        $consultorio = Consultorio::query()->create(['nombre' => 'C-401', 'piso' => '4', 'activo' => true]);

        $response = $this->withToken($this->adminToken())
            ->postJson('/api/users', [
                'name' => 'Dra. Nueva',
                'email' => 'nueva@test.local',
                'role' => 'medico',
                'doctorData' => [
                    'initials' => 'DN',
                    'specialtyId' => $specialty->id,
                    'consultorioId' => $consultorio->id,
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.user.role', 'medico')
            ->assertJsonPath('data.password', fn ($v) => strlen((string) $v) >= 8);

        $this->assertDatabaseHas('doctors', ['user_id' => $response->json('data.user.id')]);
    }

    public function test_patient_cannot_access_admin_routes(): void
    {
        $this->withToken($this->patientToken())
            ->getJson('/api/users')
            ->assertStatus(403);

        $this->withToken($this->patientToken())
            ->getJson('/api/settings')
            ->assertStatus(403);
    }

    public function test_specialty_admin_update_deactivates(): void
    {
        $specialty = Specialty::query()->create([
            'code' => 'neuro',
            'name' => 'Neurología',
            'icon' => 'n',
            'price' => 120,
            'active' => true,
        ]);

        $this->withToken($this->adminToken())
            ->patchJson("/api/specialties/{$specialty->id}", ['active' => false])
            ->assertOk()
            ->assertJsonPath('data.specialty.active', false);
    }

    public function test_settings_update_and_read(): void
    {
        $this->withToken($this->adminToken())
            ->patchJson('/api/settings', ['minCancelHours' => 24, 'lateFeeDays' => 3])
            ->assertOk();

        $this->withToken($this->adminToken())
            ->getJson('/api/settings')
            ->assertOk()
            ->assertJsonPath('data.minCancelHours', 24)
            ->assertJsonPath('data.lateFeeDays', 3);
    }

    public function test_audit_index_filters_by_severity(): void
    {
        AuditLog::query()->create([
            'at' => now(),
            'user_id' => $this->admin->id,
            'email' => 'admin@test.local',
            'action' => 'Test warning',
            'detail' => 'x',
            'sev' => AuditSev::Warning,
            'ip' => '127.0.0.1',
            'user_agent' => 'test',
            'route' => 'api.test',
            'method' => 'GET',
        ]);

        $this->withToken($this->adminToken())
            ->getJson('/api/audit?sev=warning')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.action', 'Test warning');
    }

    public function test_reports_summary_and_occupancy(): void
    {
        $this->withToken($this->adminToken())
            ->getJson('/api/reports/summary')
            ->assertOk();

        $this->withToken($this->adminToken())
            ->getJson('/api/reports/occupancy')
            ->assertOk();
    }

    public function test_notices_mark_read(): void
    {
        $notice = UserNotice::query()->create([
            'user_id' => $this->patientUser->id,
            'title' => 'Bienvenida',
            'body' => 'Tu cuenta fue creada.',
            'created_at' => now(),
        ]);

        $this->withToken($this->patientToken())
            ->getJson('/api/notifications/me')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.title', 'Bienvenida');

        $this->withToken($this->patientToken())
            ->patchJson("/api/notifications/{$notice->id}/read")
            ->assertOk()
            ->assertJsonPath('data.read_at', fn ($v) => $v !== null);

        $this->assertDatabaseHas('user_notices', ['id' => $notice->id])
            ->assertNotNull(UserNotice::find($notice->id)->read_at);
    }

    public function test_availability_returns_slots_from_schedules(): void
    {
        $specialty = Specialty::query()->create([
            'code' => 'trauma',
            'name' => 'Traumatología',
            'icon' => 't',
            'price' => 100,
            'active' => true,
        ]);

        $consultorio = Consultorio::query()->create(['nombre' => 'C-501', 'piso' => '5', 'activo' => true]);

        $doctor = Doctor::query()->create([
            'user_id' => $this->admin->id,
            'initials' => 'DT',
            'specialty_id' => $specialty->id,
            'consultorio_id' => $consultorio->id,
            'exp' => 4,
        ]);

        DoctorSchedule::query()->create([
            'doctor_id' => $doctor->id,
            'day_of_week' => now()->addDay()->dayOfWeek,
            'start_time' => '09:00',
            'end_time' => '11:00',
        ]);

        $this->getJson('/api/availability')
            ->assertOk()
            ->assertJsonPath('data.slots.0.specialty.name', 'Traumatología')
            ->assertJsonCount(4, 'data.slots');
    }
}
