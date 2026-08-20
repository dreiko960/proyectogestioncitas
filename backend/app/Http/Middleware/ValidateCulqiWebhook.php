<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateCulqiWebhook
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('services.culqi.webhook_secret');

        if (blank($secret)) {
            return response()->json(['message' => 'Webhook no configurado'], 503);
        }

        $signature = $request->bearerToken();

        if (! $signature || ! hash_equals($secret, $signature)) {
            return response()->json(['message' => 'Firma inválida'], 401);
        }

        return $next($request);
    }
}
