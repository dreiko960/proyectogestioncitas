<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'No autenticado'], 401);
        }

        $allowed = collect($roles)->flatMap(fn (string $r) => explode('|', $r))->unique();

        if ($allowed->contains(fn (string $r) => $request->user()->tokenCan($r))) {
            return $next($request);
        }

        if ($allowed->contains($user->role->value)) {
            return $next($request);
        }

        return response()->json(['message' => 'No autorizado para esta acción'], 403);
    }
}
