<?php

namespace App\Support;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;


final class Codes
{
    public static function next(string $table, string $prefix, string $column = 'code'): string
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $max = DB::table($table)->where($column, 'like', $prefix.'-%')->max($column);
            $number = $max ? ((int) Str::afterLast($max, '-')) + 1 : 1;
            $code = $prefix.'-'.$number;

            if (DB::table($table)->where($column, $code)->doesntExist()) {
                return $code;
            }
        }

        throw new QueryException(DB::connection(), '', [], new \Exception("No se pudo generar un código único para {$prefix}"));
    }
}
