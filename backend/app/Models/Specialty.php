<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Specialty extends Model
{
    use HasUuids;

    protected $table = 'especialidades';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'code',
        'name',
        'icon',
        'price',
        'desc',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'active' => 'boolean',
        ];
    }

    public function doctors(): HasMany
    {
        return $this->hasMany(Doctor::class);
    }

    public function consultorios(): BelongsToMany
    {
        return $this->belongsToMany(Consultorio::class, 'consultorio_especialidad', 'specialty_id', 'consultorio_id');
    }
}
