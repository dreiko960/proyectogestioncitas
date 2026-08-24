<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ApiController;
use App\Http\Requests\UpdateSettingsRequest;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;


class SettingController extends ApiController
{
    private const KEYS = [
        'minCancelHours',
        'minReserveHours',
        'tokenExpiryMin',
        'waitlistWindowMin',
        'lateFeeDays',
        'nonWorkingDays',
    ];

    public function index(): JsonResponse
    {
        $settings = Setting::query()->orderBy('key')->get();

        return $this->success(collect(self::KEYS)->mapWithKeys(fn ($key) => [
            $key => $settings->firstWhere('key', $key)?->value['v'] ?? null,
        ])->all());
    }

    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        foreach ($request->validated() as $key => $value) {
            if (in_array($key, self::KEYS, true)) {
                Setting::set($key, $value);
            }
        }

        return $this->success($this->index()->getData(true)['data']);
    }
}
