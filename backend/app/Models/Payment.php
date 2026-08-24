<?php

namespace App\Models;

use App\Enums\PaidType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasUuids;

    protected $table = 'pagos';

    public const UPDATED_AT = null;

    protected $fillable = [
        'id',
        'code',
        'appointment_id',
        'patient_id',
        'amount',
        'method',
        'status',
        'paid_type',
        'receipt_code',
        'verified_by',
        'gateway',
        'culqi_order_id',
        'culqi_charge_id',
        'culqi_data',
        'refunded_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'paid_type' => PaidType::class,
            'gateway' => 'boolean',
            'culqi_data' => 'array',
            'refunded_at' => 'datetime',
        ];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
