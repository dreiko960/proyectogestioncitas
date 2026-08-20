<?php

namespace App\Models;

use App\Enums\AuditSev;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $table = 'audit_log';

    protected $fillable = [
        'at',
        'user_id',
        'email',
        'action',
        'detail',
        'sev',
        'ip',
        'user_agent',
        'route',
        'method',
    ];

    protected function casts(): array
    {
        return [
            'at' => 'datetime',
            'sev' => AuditSev::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
