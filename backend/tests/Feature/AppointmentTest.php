<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\Consultorio;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Patient;
use App\Models\Specialty;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AppointmentTest extends TestCase
{
    use RefreshDatabase;

    private Specialty $specialty;

    private Doctor $doctor;

    private User $patientUser;

    private User $doctorUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->specialty = Specialty::query()->create([
            'code' => 'medicina',
            'name' => 'Medicina General',
            'icon' => 'stethoscope',
            'price' => 50,
            'desc' => 'Atención general',
            'active' => true,
        ]);

        $this->doctorUser = User::query()->create([
            'name' => 'Dr. Prueba',
            'email' => 'doctor.prueba@test.local',
            'password_hash' => Hash::make('Demo1234'),
            'role' => UserRole::Medico,
            'active' => true,
        ]);

        $consultorio = Consultorio::query()->create([
            'nombre' => 'Consultorio 101',
            'piso' => '1',
            'area' => 'Medicina',
            'activo' => true,
        ]);

        $this->doctor = Doctor::query()->create([
            'user_id' => $this->doctorUser->id,
            'initials' => 'DP',
            'specialty_id' => $this->specialty->id,
            'consultorio_id' => $consultorio->id,
            'phone' => '999888777',
            'exp' => 5,
        ]);

        foreach (range(0, 6) as $day) {
            DoctorSchedule::query()->create([
                'doctor_id' => $this->doctor->id,
                'day_of_week' => $day,
                'start_time' => '08:00',
                'end_time' => '12:00',
            ]);
        }

        $this->patientUser = User::query()->create([
            'name' => 'Paciente Prueba',
            'email' => 'paciente.prueba@test.local',
            'password_hash' => Hash::make('Demo1234'),
            'role' => UserRole::Paciente,
            'active' => true,
        ]);

        Patient::query()->create([
            'user_id' => $this->patientUser->id,
            'dni' => '12345678',
            'phone' => '987654321',
            'dob' => '1990-01-01',
            'consent_29733' => true,
            'consent_at' => now(),
        ]);
    }

    private function futureTime(): string
    {
        return Carbon::now()->startOfDay()->addHours(10)->format('H:i');
    }

    private function authPatient(): string
    {
        return $this->postJson('/api/auth/login', [
            'email' => 'paciente.prueba@test.local',
            'password' => 'Demo1234',
        ])->json('data.access_token');
    }

    public function test_patient_reserves_an_appointment(): void
    {
        $token = $this->authPatient();
        $date = Carbon::now()->addDays(2)->toDateString();

        $response = $this->withToken($token)->postJson('/api/appointments', [
            'doctorId' => $this->doctor->id,
            'specialtyId' => $this->specialty->id,
            'date' => $date,
            'time' => $this->futureTime(),
            'duration' => 30,
            'reason' => 'Dolor de cabeza',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'agendada')
            ->assertJsonPath('data.doctor.id', $this->doctor->id)
            ->assertJsonStructure(['data' => ['id', 'code', 'date', 'time', 'turno', 'doctor']]);

        $this->assertDatabaseHas('appointments', ['code' => $response->json('data.code')]);
        $this->assertDatabaseCount('appointment_status_history', 1);
    }

    public function test_second_booking_same_slot_returns_409_with_alternatives(): void
    {
        $token = $this->authPatient();
        $date = Carbon::now()->addDays(2)->toDateString();
        $time = $this->futureTime();

        $payload = [
            'doctorId' => $this->doctor->id,
            'specialtyId' => $this->specialty->id,
            'date' => $date,
            'time' => $time,
            'duration' => 30,
        ];

        $this->withToken($token)->postJson('/api/appointments', $payload)->assertCreated();

        $response = $this->withToken($token)->postJson('/api/appointments', $payload);

        $response->assertStatus(409)
            ->assertJsonPath('message', 'Este horario ya no está disponible')
            ->assertJsonStructure(['errors' => ['alternatives' => [['date', 'time']]]]);
    }

    public function test_cancel_within_12h_returns_late_warning(): void
    {
        $token = $this->authPatient();
        $date = Carbon::now()->addDays(2)->toDateString();

        $id = $this->withToken($token)->postJson('/api/appointments', [
            'doctorId' => $this->doctor->id,
            'specialtyId' => $this->specialty->id,
            'date' => $date,
            'time' => $this->futureTime(),
        ])->json('data.id');

        $response = $this->withToken($token)->patchJson("/api/appointments/{$id}/cancel", [
            'reason' => 'Cambio de planes',
        ]);

        $response->assertOk()->assertJsonPath('data.appointment.status', 'cancelada');

        $this->assertDatabaseHas('appointments', ['id' => $id, 'cancel_reason' => 'Cambio de planes']);
    }

    public function test_checkin_mobile_after_payment_state(): void
    {
        $token = $this->authPatient();
        $date = Carbon::now()->addDays(2)->toDateString();

        $id = $this->withToken($token)->postJson('/api/appointments', [
            'doctorId' => $this->doctor->id,
            'specialtyId' => $this->specialty->id,
            'date' => $date,
            'time' => $this->futureTime(),
        ])->json('data.id');

        $appointment = Appointment::find($id);
        $appointment->status = AppointmentStatus::Pagada;
        $appointment->save();

        $this->withToken($token)->postJson("/api/appointments/{$id}/checkin")
            ->assertOk()
            ->assertJsonPath('data.status', 'check_in');
    }

    public function test_me_lists_upcoming_appointments(): void
    {
        $token = $this->authPatient();
        $date = Carbon::now()->addDays(2)->toDateString();

        $this->withToken($token)->postJson('/api/appointments', [
            'doctorId' => $this->doctor->id,
            'specialtyId' => $this->specialty->id,
            'date' => $date,
            'time' => $this->futureTime(),
        ])->assertCreated();

        $this->withToken($token)->getJson('/api/appointments/me')
            ->assertOk()
            ->assertJsonCount(1, 'data.upcoming')
            ->assertJsonCount(0, 'data.past')
            ->assertJsonCount(0, 'data.cancelled');
    }
}
