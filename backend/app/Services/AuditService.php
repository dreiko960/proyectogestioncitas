<?php

namespace App\Services;

use App\Enums\AuditSev;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AuditService
{
    public function record(
        ?User $user,
        string $action,
        ?string $detail = null,
        AuditSev $sev = AuditSev::Info,
        ?string $route = null,
        ?string $method = null,
    ): AuditLog {
        $request = request();

        $ip = null;
        $userAgent = null;

        if ($request instanceof Request) {
            $ip = $request->ip();
            $userAgent = mb_substr((string) $request->userAgent(), 0, 250);
            $route ??= $request->route()?->uri();
            $method ??= $request->method();
        }

        return AuditLog::create([
            'at' => now(),
            'user_id' => $user?->id,
            'email' => $user?->email,
            'action' => $action,
            'detail' => $detail,
            'sev' => $sev,
            'ip' => $ip,
            'user_agent' => $userAgent,
            'route' => $route,
            'method' => $method,
        ]);
    }

    public function warning(?User $user, string $action, ?string $detail = null): AuditLog
    {
        return $this->record($user, $action, $detail, AuditSev::Warning);
    }

    public function danger(?User $user, string $action, ?string $detail = null): AuditLog
    {
        return $this->record($user, $action, $detail, AuditSev::Danger);
    }

    public function failed(string $action, \Throwable $e): void
    {
        Log::error($action, [
            'exception' => $e->getMessage(),
            'request_id' => request()->header('X-Request-Id'),
        ]);
    }
}
