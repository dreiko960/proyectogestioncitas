<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Patient extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'id',
        'user_id',
        'dni',
        'phone',
        'dob',
        'address',
        'consent_29733',
        'consent_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'dob' => 'date',
            'consent_29733' => 'boolean',
            'consent_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function waitlistEntries(): HasMany
    {
        return $this->hasMany(WaitlistEntry::class);
    }
}
