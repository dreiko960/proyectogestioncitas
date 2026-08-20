<?php

use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\AuditController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AvailabilityController;
use App\Http\Controllers\Api\ConsultorioController;
use App\Http\Controllers\Api\DoctorController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\NoticeController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\QueueController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\SpecialtyController;
use App\Http\Controllers\Api\TriageController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WaitlistController;
use App\Http\Controllers\Api\WebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:auth-login');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:auth-register');
    Route::post('/refresh', [AuthController::class, 'refresh']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:auth-login');
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
});

// 7.2 Webhook Culqi: público, firma HMAC verificada, idempotente
Route::post('/webhooks/culqi', [WebhookController::class, 'handle'])->middleware('culqi.webhook');

// 5.4 Disponibilidad pública
Route::get('/availability', [AvailabilityController::class, 'index']);

// 5.3 Catálogos públicos
Route::get('/specialties', [SpecialtyController::class, 'index']);
Route::get('/consultorios', [ConsultorioController::class, 'index']);
Route::get('/doctors', [DoctorController::class, 'index']);
Route::get('/doctors/{id}', [DoctorController::class, 'show']);

// 5.8 TV: token de solo lectura
Route::post('/tv/token', [QueueController::class, 'tvToken']);

// 5.8 Cola del día: staff autenticado o token TV (solo lectura, validado en el controlador)
Route::get('/queue/day', [QueueController::class, 'day'])->middleware('tv-or-auth');
Route::get('/queue/stats-today', [QueueController::class, 'statsToday'])->middleware('tv-or-auth');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // 5.2 Usuarios (admin)
    Route::get('/users', [UserController::class, 'index'])->middleware('role:administrador');
    Route::post('/users', [UserController::class, 'store'])->middleware('role:administrador');
    Route::patch('/users/{id}', [UserController::class, 'update'])->middleware('role:administrador');
    Route::patch('/users/{id}/activate', [UserController::class, 'activate'])->middleware('role:administrador');

    // 5.3 Catálogos (admin)
    Route::post('/specialties', [SpecialtyController::class, 'store'])->middleware('role:administrador');
    Route::patch('/specialties/{id}', [SpecialtyController::class, 'update'])->middleware('role:administrador');
    Route::post('/consultorios', [ConsultorioController::class, 'store'])->middleware('role:administrador');
    Route::patch('/consultorios/{id}', [ConsultorioController::class, 'update'])->middleware('role:administrador');

    // 5.3 Médicos: agenda
    Route::get('/doctors/{id}/slots', [DoctorController::class, 'slots']);
    Route::post('/doctors/{id}/schedules', [DoctorController::class, 'storeSchedules'])->middleware('role:medico|administrador');
    Route::post('/doctors/{id}/exceptions', [DoctorController::class, 'storeException'])->middleware('role:medico|administrador');

    // 5.5 Citas (núcleo)
    Route::post('/appointments', [AppointmentController::class, 'store'])->middleware('role:paciente|recepcionista');
    Route::get('/appointments/me', [AppointmentController::class, 'me'])->middleware('role:paciente');
    Route::get('/appointments/day', [AppointmentController::class, 'day'])->middleware('role:recepcionista|medico|enfermera');
    Route::get('/appointments/patient/{pid}', [AppointmentController::class, 'patientHistory'])->middleware('role:medico');
    Route::get('/appointments/{id}', [AppointmentController::class, 'show']);
    Route::post('/appointments/{id}/checkin', [AppointmentController::class, 'checkin'])->middleware('role:paciente');
    Route::patch('/appointments/{id}/cancel', [AppointmentController::class, 'cancel'])->middleware('role:paciente|recepcionista');
    Route::patch('/appointments/{id}/reschedule', [AppointmentController::class, 'reschedule'])->middleware('role:paciente|recepcionista');

    // 5.6 Pagos (caja + Culqi)
    Route::post('/payments/charge', [PaymentController::class, 'charge'])->middleware('role:paciente');
    Route::post('/payments/cash', [PaymentController::class, 'cash'])->middleware('role:recepcionista');
    Route::post('/payments/verify', [PaymentController::class, 'verify'])->middleware('role:recepcionista');
    Route::post('/payments/complete-balance', [PaymentController::class, 'completeBalance'])->middleware('role:recepcionista');
    Route::post('/payments/{id}/refund', [PaymentController::class, 'refund'])->middleware('role:administrador');
    Route::get('/payments/receipts/{id}', [PaymentController::class, 'receipt']);

    // 5.7 Triaje (enfermería)
    Route::get('/triage/queue', [TriageController::class, 'queue'])->middleware('role:enfermera');
    Route::get('/triage/history', [TriageController::class, 'history'])->middleware('role:enfermera');
    Route::post('/triage/{appointment}', [TriageController::class, 'start'])->middleware('role:enfermera');
    Route::patch('/triage/{appointment}/complete', [TriageController::class, 'complete'])->middleware('role:enfermera');

    // 5.8 Cola del día (recepción/enfermería)
    Route::post('/queue/{id}/send-triage', [QueueController::class, 'sendTriage'])->middleware('role:recepcionista|enfermera');
    Route::post('/queue/{id}/call-triage', [QueueController::class, 'callTriage'])->middleware('role:enfermera');
    Route::post('/queue/{id}/finish-triage', [QueueController::class, 'finishTriage'])->middleware('role:enfermera');
    Route::post('/queue/{id}/call-consult', [QueueController::class, 'callConsult'])->middleware('role:medico|enfermera');
    Route::post('/queue/{id}/attended', [QueueController::class, 'attended'])->middleware('role:medico|enfermera');

    // 5.9 Lista de espera (paciente + worker)
    Route::post('/waitlist', [WaitlistController::class, 'store'])->middleware('role:paciente');
    Route::get('/waitlist/me', [WaitlistController::class, 'me'])->middleware('role:paciente');
    Route::post('/waitlist/{id}/confirm', [WaitlistController::class, 'confirm'])->middleware('role:paciente');
    Route::post('/waitlist/{id}/reject', [WaitlistController::class, 'reject'])->middleware('role:paciente');
    Route::post('/waitlist/{id}/offer', [WaitlistController::class, 'offer'])->middleware('role:administrador');
    Route::post('/waitlist/{id}/expire', [WaitlistController::class, 'expire'])->middleware('role:administrador');

    // 5.10 Reportes / Auditoría / Configuración / Avisos / Documentos
    Route::get('/reports/summary', [ReportController::class, 'summary'])->middleware('role:administrador');
    Route::get('/reports/occupancy', [ReportController::class, 'occupancy'])->middleware('role:administrador');
    Route::get('/reports/export', [ReportController::class, 'export'])->middleware('role:administrador');
    Route::get('/audit', [AuditController::class, 'index'])->middleware('role:administrador');
    Route::get('/settings', [SettingController::class, 'index'])->middleware('role:administrador');
    Route::patch('/settings', [SettingController::class, 'update'])->middleware('role:administrador');
    Route::get('/notifications/me', [NoticeController::class, 'me']);
    Route::patch('/notifications/{id}/read', [NoticeController::class, 'markRead']);
    Route::post('/documents/{appointment}/pdf', [DocumentController::class, 'pdf'])->middleware('role:paciente|medico|administrador');
});
