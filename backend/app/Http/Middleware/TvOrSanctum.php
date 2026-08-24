<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Symfony\Component\HttpFoundation\Response;


class TvOrSanctum
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->query('tvToken')) {
            try {
                $payload = json_decode(Crypt::decryptString($request->query('tvToken')), true);

                if (($payload['aud'] ?? null) === 'tv' && ($payload['exp'] ?? 0) > now()->timestamp) {
                    $request->attributes->set('tv_mode', true);

                    return $next($request);
                }
            } catch (\Throwable) {
                
            }

            abort(401, 'No autenticado');
        }

        $guard = Auth::guard('sanctum');
        $guard->setRequest($request);

        if (! $guard->check()) {
            abort(401, 'No autenticado');
        }

        $request->setUserResolver(fn () => $guard->user());

        return $next($request);
    }
}
