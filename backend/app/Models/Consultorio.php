<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Consultorio extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'id',
        'nombre',
        'piso',
        'area',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function specialties(): BelongsToMany
    {
        return $this->belongsToMany(Specialty::class, 'consultorio_specialties');
    }

    public function doctors(): HasMany
    {
        return $this->hasMany(Doctor::class);
    }
}
