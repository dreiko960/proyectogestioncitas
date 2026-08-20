<?php

namespace App\Services\Contracts;

interface DniProvider
{
    /**
     * @return array{valid: bool, names?: string|null}
     */
    public function lookup(string $dni): array;
}
