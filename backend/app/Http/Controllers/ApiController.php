<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

abstract class ApiController extends Controller
{
    protected function success(mixed $data = null, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data], $status);
    }

    protected function error(string $message, int $status = 400, array $errors = []): JsonResponse
    {
        $payload = ['message' => $message];

        if ($errors !== []) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }

    protected function noContent(int $status = 204): JsonResponse
    {
        return response()->json(null, $status);
    }
}
