<?php

namespace App\Services;

use App\Services\Contracts\DniProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class DniService
{
    public function __construct(private readonly ?DniProvider $provider = null) {}

    public function lookup(string $dni): array
    {
        $cached = Cache::get('dni:'.$dni);

        if ($cached !== null) {
            return $cached;
        }

        if (! $this->provider) {
            $result = ['valid' => true, 'names' => null];
            Log::info('DNI sin proveedor configurado, validación omitida', ['dni' => $dni]);

            return $result;
        }

        $result = $this->provider->lookup($dni);
        Cache::put('dni:'.$dni, $result, now()->addDays(30));

        return $result;
    }
}
