<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ApiController;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;


class AuditController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $logs = AuditLog::query()
            ->with('user')
            ->when($request->filled('sev'), fn ($q) => $q->where('sev', $request->query('sev')))
            ->when($request->filled('from'), fn ($q) => $q->where('at', '>=', $request->query('from')))
            ->when($request->filled('to'), fn ($q) => $q->where('at', '<=', $request->query('to')))
            ->orderByDesc('at')
            ->paginate((int) $request->query('per_page', 25));

        return $this->success([
            'items' => $logs->map(fn ($log) => [
                'id' => $log->id,
                'at' => $log->at->toIso8601String(),
                'user' => $log->user ? ['id' => $log->user->id, 'name' => $log->user->name, 'email' => $log->user->email] : ($log->email ? ['name' => $log->email] : null),
                'action' => $log->action,
                'detail' => $log->detail,
                'sev' => $log->sev->value,
                'ip' => $log->ip,
                'route' => $log->route,
                'method' => $log->method,
            ])->all(),
            'pagination' => [
                'page' => $logs->currentPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
                'last_page' => $logs->lastPage(),
            ],
        ]);
    }
}
