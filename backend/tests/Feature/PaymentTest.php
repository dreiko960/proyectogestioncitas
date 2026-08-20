<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\PaidType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\Consultorio;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    private Appointment $appointment;

    private User $receptionist;

    private User $patientUser;

    protected function setUp(): void
    {
        parent::setUp();

        $specialty = Specialty::query()->create([
            'code' => 'medicina',
            'name' => 'Medicina General',
            'icon' => 'stethoscope',
            'price' => 80,
            'active' => true,
        ]);

        $consultorio = Consultorio::query()->create(['nombre' => 'C-101', 'piso' => '1', 'activo' => true]);

        $doctor = Doctor::query()->create([
            'user_id' => User::query()->create([
                'name' => 'Dra. Pagos',
                'email' => 'pagos.doctor@test.local',
                'password_hash' => Hash::make('Demo1234'),
                'role' => UserRole::Medico,
                'active' => true,
            ])->id,
            'initials' => 'DP',
            'specialty_id' => $specialty->id,
            'consultorio_id' => $consultorio->id,
            'exp' => 5,
        ]);

        $this->receptionist = User::query()->create([
            'name' => 'Cajera',
            'email' => 'cajera@test.local',
            'password_hash' => Hash::make('Demo1234'),
            'role' => UserRole::Recepcionista,
            'active' => true,
        ]);

        $this->patientUser = User::query()->create([
            'name' => 'Paciente Pagos',
            'email' => 'pagos@test.local',
            'password_hash' => Hash::make('Demo1234'),
            'role' => UserRole::Paciente,
            'active' => true,
        ]);

        Patient::query()->create([
            'user_id' => $this->patientUser->id,
            'dni' => '11223344',
            'dob' => '1990-01-01',
            'consent_29733' => true,
            'consent_at' => now(),
        ]);

        $this->appointment = Appointment::query()->create([
            'code' => 'C-PAY01',
            'patient_id' => $this->patientUser->patient->id,
            'doctor_id' => $doctor->id,
            'specialty_id' => $specialty->id,
            'date' => now()->addDay()->toDateString(),
            'time' => '10:00',
            'status' => AppointmentStatus::Agendada,
        ]);
    }

    private function token(string $email): string
    {
        return $this->postJson('/api/auth/login', ['email' => $email, 'password' => 'Demo1234'])
            ->json('data.access_token');
    }

    public function test_cash_payment_marks_appointment_paid(): void
    {
        $this->withToken($this->token('cajera@test.local'))
            ->postJson('/api/payments/cash', [
                'appointmentId' => $this->appointment->id,
                'method' => 'efectivo',
                'type' => 'total',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'pagado')
            ->assertJsonPath('data.method', 'efectivo')
            ->assertJsonPath('data.paid_type', 'total')
            ->assertJsonPath('data.amount', 80)
            ->assertJsonPath('data.receipt_code', fn ($v) => str_starts_with($v, 'R-'));

        $this->assertDatabaseHas('appointments', [
            'id' => $this->appointment->id,
            'status' => 'pagada',
            'paid_type' => 'total',
        ]);
    }

    public function test_adelanto_50_percent_leaves_pending_balance(): void
    {
        $this->withToken($this->token('cajera@test.local'))
            ->postJson('/api/payments/cash', [
                'appointmentId' => $this->appointment->id,
                'method' => 'efectivo',
                'type' => 'adelanto',
            ])
            ->assertOk()
            ->assertJsonPath('data.paid_type', 'adelanto')
            ->assertJsonPath('data.amount', 40);

        $this->assertDatabaseHas('appointments', [
            'id' => $this->appointment->id,
            'status' => 'pagada',
            'paid_type' => 'adelanto',
        ]);
    }

    public function test_complete_balance_charges_only_the_remaining(): void
    {
        Payment::query()->create([
            'id' => Str::uuid()->toString(),
            'code' => 'P-TEST-ADEL',
            'appointment_id' => $this->appointment->id,
            'patient_id' => $this->appointment->patient_id,
            'amount' => 40,
            'method' => PaymentMethod::Efectivo,
            'paid_type' => PaidType::Adelanto,
            'status' => PaymentStatus::Pagado,
            'operation_number' => 'op-adelanto',
        ]);
        $this->appointment->update(['paid_type' => PaidType::Adelanto]);

        $this->withToken($this->token('cajera@test.local'))
            ->postJson('/api/payments/complete-balance', [
                'appointmentId' => $this->appointment->id,
                'method' => 'transferencia',
            ])
            ->assertOk()
            ->assertJsonPath('data.paid_type', 'total')
            ->assertJsonPath('data.amount', 40);

        $this->assertDatabaseHas('appointments', [
            'id' => $this->appointment->id,
            'paid_type' => 'total',
        ]);
    }

    public function test_complete_balance_rejects_without_adelanto(): void
    {
        $this->withToken($this->token('cajera@test.local'))
            ->postJson('/api/payments/complete-balance', [
                'appointmentId' => $this->appointment->id,
                'method' => 'transferencia',
            ])
            ->assertStatus(422);
    }

    public function test_charge_creates_culqi_order_in_mock_mode(): void
    {
        $response = $this->withToken($this->token('pagos@test.local'))
            ->postJson('/api/payments/charge', [
                'appointmentId' => $this->appointment->id,
                'type' => 'total',
                'culqiToken' => 'tok_test_123',
            ]);

        $response->assertOk();
        $this->assertStringStartsWith('mock_order_', $response->json('data.culqi_order_id'));
        $this->assertSame(80, $response->json('data.amount'));
        $this->assertSame('pagado', $response->json('data.status'));

        $this->assertDatabaseHas('appointments', [
            'id' => $this->appointment->id,
            'status' => 'pagada',
        ]);
    }

    public function test_verify_marks_declared_payment_completed(): void
    {
        $payment = Payment::query()->create([
            'id' => Str::uuid()->toString(),
            'code' => 'P-TEST-VER',
            'appointment_id' => $this->appointment->id,
            'patient_id' => $this->appointment->patient_id,
            'amount' => 80,
            'method' => PaymentMethod::Yape,
            'paid_type' => PaidType::Total,
            'status' => PaymentStatus::PendienteVerificacion,
            'operation_number' => 'yape-ver-1',
        ]);

        $this->withToken($this->token('cajera@test.local'))
            ->postJson('/api/payments/verify', [
                'paymentId' => $payment->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'pagado');

        $this->assertDatabaseHas('appointments', [
            'id' => $this->appointment->id,
            'status' => 'pagada',
        ]);
    }

    public function test_receipt_generates_pdf_file(): void
    {
        $payment = Payment::query()->create([
            'id' => Str::uuid()->toString(),
            'code' => 'P-TEST-REC',
            'appointment_id' => $this->appointment->id,
            'patient_id' => $this->appointment->patient_id,
            'amount' => 80,
            'method' => PaymentMethod::Efectivo,
            'paid_type' => PaidType::Total,
            'status' => PaymentStatus::Pagado,
            'operation_number' => 'rec-1',
            'receipt_code' => 'R-2026-0001',
        ]);

        $this->withToken($this->token('pagos@test.local'))
            ->getJson("/api/payments/receipts/{$payment->id}")
            ->assertOk()
            ->assertJsonPath('data.receipt_code', 'R-2026-0001')
            ->assertJsonPath('data.url', fn ($v) => str_ends_with($v, '.pdf'));
    }
}
