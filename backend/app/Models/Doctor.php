<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Doctor extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'id',
        'user_id',
        'initials',
        'specialty_id',
        'consultorio_id',
        'phone',
        'bio',
        'rating',
        'rating_count',
        'studies',
        'exp',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'decimal:2',
            'rating_count' => 'integer',
            'exp' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function specialty(): BelongsTo
    {
        return $this->belongsTo(Specialty::class);
    }

    public function consultorio(): BelongsTo
    {
        return $this->belongsTo(Consultorio::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(DoctorSchedule::class);
    }

    public function exceptions(): HasMany
    {
        return $this->hasMany(DoctorDateException::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }
}
