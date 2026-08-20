<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\Consultorio;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class QueueTest extends TestCase
{
    use RefreshDatabase;

    private Doctor $doctor;

    private User $receptionist;

    private User $nurse;

    private User $patientUser;

    protected function setUp(): void
    {
        parent::setUp();

        $specialty = Specialty::query()->create([
            'code' => 'pediatria',
            'name' => 'Pediatría',
            'icon' => 'baby',
            'price' => 60,
            'active' => true,
        ]);

        $consultorio = Consultorio::query()->create(['nombre' => 'C-201', 'piso' => '2', 'activo' => true]);

        $this->doctor = Doctor::query()->create([
            'user_id' => User::query()->create([
                'name' => 'Dr. Cola',
                'email' => 'cola@test.local',
                'password_hash' => Hash::make('Demo1234'),
                'role' => UserRole::Medico,
                'active' => true,
            ])->id,
            'initials' => 'DC',
            'specialty_id' => $specialty->id,
            'consultorio_id' => $consultorio->id,
            'exp' => 3,
        ]);

        $this->receptionist = User::query()->create([
            'name' => 'Recepcionista',
            'email' => 'recepcion@test.local',
            'password_hash' => Hash::make('Demo1234'),
            'role' => UserRole::Recepcionista,
            'active' => true,
        ]);

        $this->nurse = User::query()->create([
            'name' => 'Enfermera',
            'email' => 'enfermera@test.local',
            'password_hash' => Hash::make('Demo1234'),
            'role' => UserRole::Enfermera,
            'active' => true,
        ]);

        $this->patientUser = User::query()->create([
            'name' => 'Paciente Cola',
            'email' => 'paciente.cola@test.local',
            'password_hash' => Hash::make('Demo1234'),
            'role' => UserRole::Paciente,
            'active' => true,
        ]);

        Patient::query()->create([
            'user_id' => $this->patientUser->id,
            'dni' => '87654321',
            'dob' => '1985-05-05',
            'consent_29733' => true,
            'consent_at' => now(),
        ]);
    }

    private function makeQueuedAppointment(AppointmentStatus $status, string $time = '09:00'): Appointment
    {
        return Appointment::query()->create([
            'code' => 'C-'.rand(1000, 9999),
            'patient_id' => $this->patientUser->patient->id,
            'doctor_id' => $this->doctor->id,
            'specialty_id' => $this->doctor->specialty_id,
            'date' => now()->toDateString(),
            'time' => $time,
            'status' => $status,
            'turno' => 'A-001',
            'check_in_time' => '08:45',
        ]);
    }

    private function token(string $email): string
    {
        return $this->postJson('/api/auth/login', ['email' => $email, 'password' => 'Demo1234'])
            ->json('data.access_token');
    }

    public function test_send_triage_assigns_turno_and_moves_to_queue(): void
    {
        $appointment = Appointment::query()->create([
            'code' => 'C-2001',
            'patient_id' => $this->patientUser->patient->id,
            'doctor_id' => $this->doctor->id,
            'specialty_id' => $this->doctor->specialty_id,
            'date' => now()->toDateString(),
            'time' => '09:00',
            'status' => AppointmentStatus::Pagada,
        ]);

        $response = $this->withToken($this->token('recepcion@test.local'))
            ->postJson("/api/queue/{$appointment->id}/send-triage");

        $response->assertOk()
            ->assertJsonPath('data.status', 'en_espera_triaje')
            ->assertJsonPath('data.turno', 'A-001');
    }

    public function test_full_pipeline_transitions_are_enforced(): void
    {
        $appointment = $this->makeQueuedAppointment(AppointmentStatus::EnEsperaTriaje);
        $token = $this->token('enfermera@test.local');

        $this->withToken($token)->postJson("/api/queue/{$appointment->id}/call-triage")
            ->assertOk()->assertJsonPath('data.status', 'en_triaje');

        $this->withToken($token)->postJson("/api/queue/{$appointment->id}/finish-triage")
            ->assertOk()->assertJsonPath('data.status', 'triaje_completado');

        $this->withToken($token)->postJson("/api/queue/{$appointment->id}/call-consult")
            ->assertOk()->assertJsonPath('data.status', 'en_atencion');

        $this->withToken($token)->postJson("/api/queue/{$appointment->id}/attended")
            ->assertOk()->assertJsonPath('data.status', 'atendida');
    }

    public function test_invalid_transition_returns_422(): void
    {
        $appointment = $this->makeQueuedAppointment(AppointmentStatus::EnEsperaTriaje);

        $this->withToken($this->token('enfermera@test.local'))
            ->postJson("/api/queue/{$appointment->id}/attended")
            ->assertStatus(422);
    }

    public function test_queue_day_lists_items_ordered_by_turno(): void
    {
        $a1 = $this->makeQueuedAppointment(AppointmentStatus::EnEsperaTriaje, '08:30');
        $a1->update(['turno' => 'A-002']);
        $a2 = $this->makeQueuedAppointment(AppointmentStatus::EnTriaje, '08:00');
        $a2->update(['turno' => 'A-001']);

        $this->withToken($this->token('recepcion@test.local'))
            ->getJson('/api/queue/day')
            ->assertOk()
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.items.0.turno', 'A-001')
            ->assertJsonPath('data.stats.waiting', 1)
            ->assertJsonPath('data.stats.in_triage', 1);
    }

    public function test_tv_token_allows_read_only_access(): void
    {
        $token = $this->postJson('/api/tv/token', ['key' => 'cmas-tv'])->json('data.token');

        $this->getJson('/api/queue/stats-today?tvToken='.$token)
            ->assertOk();
    }

    public function test_tv_token_rejects_wrong_key(): void
    {
        $this->postJson('/api/tv/token', ['key' => 'clave-incorrecta'])
            ->assertStatus(401);
    }
}
