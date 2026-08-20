<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('auth-login', function (Request $request) {
            $key = sha1($request->input('email').'|'.$request->ip());

            return Limit::perMinute(5)->by($key);
        });

        RateLimiter::for('auth-register', function (Request $request) {
            return Limit::perHour(3)->by($request->ip());
        });

        RateLimiter::for('webhooks', function (Request $request) {
            return Limit::perMinute(100)->by($request->ip());
        });
    }
}
