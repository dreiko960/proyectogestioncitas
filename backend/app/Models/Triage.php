<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Triage extends Model
{
    use HasUuids;

    protected $table = 'triajes';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'appointment_id',
        'nurse_id',
        'pa',
        'temp',
        'fc',
        'peso',
        'talla',
        'motivo',
        'alergias',
        'observaciones',
        'at',
    ];

    protected function casts(): array
    {
        return [
            'temp' => 'decimal:1',
            'fc' => 'integer',
            'peso' => 'decimal:1',
            'talla' => 'decimal:2',
            'at' => 'datetime',
        ];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function nurse(): BelongsTo
    {
        return $this->belongsTo(User::class, 'nurse_id');
    }
}
