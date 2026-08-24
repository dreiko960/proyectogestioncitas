<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Consultorio extends Model
{
    use HasUuids;

    protected $table = 'consultorios';

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
        return $this->belongsToMany(Specialty::class, 'consultorio_especialidad', 'consultorio_id', 'specialty_id');
    }

    public function doctors(): HasMany
    {
        return $this->hasMany(Doctor::class);
    }
}
