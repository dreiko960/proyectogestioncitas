<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'app' => config('app.name'),
        'docs' => url('/docs'),
        'health' => url('/health'),
    ]);
});

Route::get('/health', function () {
    $db = false;
    try {
        DB::select('SELECT 1');
        $db = true;
    } catch (Throwable) {
    }

    $redis = false;
    try {
        Redis::connection()->ping();
        $redis = true;
    } catch (Throwable) {
    }

    return response()->json([
        'status' => $db && $redis ? 'ok' : 'degraded',
        'db' => $db,
        'redis' => $redis,
        'time' => now()->toIso8601String(),
    ], $db ? 200 : 503);
});
