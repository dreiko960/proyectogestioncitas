<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ApiController;
use App\Http\Requests\StoreSpecialtyRequest;
use App\Http\Requests\UpdateSpecialtyRequest;
use App\Models\Specialty;
use Illuminate\Http\JsonResponse;

/** Catálogo de especialidades (BACKEND.md §5.3). */
class SpecialtyController extends ApiController
{
    public function index(): JsonResponse
    {
        $specialties = Specialty::query()->where('active', true)->orderBy('name')->get();

        return $this->success($specialties->map(fn ($s) => $this->payload($s))->all());
    }

    public function store(StoreSpecialtyRequest $request): JsonResponse
    {
        $specialty = Specialty::query()->create($request->validated());

        return $this->success($this->payload($specialty), 201);
    }

    public function update(UpdateSpecialtyRequest $request, string $id): JsonResponse
    {
        $specialty = Specialty::query()->find($id);

        if (! $specialty) {
            return $this->error('Especialidad no encontrada', 404);
        }

        $warning = null;

        if ($request->has('active') && $request->boolean('active') === false && $specialty->doctors()->exists()) {
            $warning = 'La especialidad tiene médicos asignados; se desactiva pero sus citas activas continúan';
        }

        $specialty->update($request->validated());

        return $this->success([
            'specialty' => $this->payload($specialty->refresh()),
            'warning' => $warning,
        ]);
    }

    private function payload(Specialty $specialty): array
    {
        return [
            'id' => $specialty->id,
            'code' => $specialty->code,
            'name' => $specialty->name,
            'icon' => $specialty->icon,
            'price' => (float) $specialty->price,
            'desc' => $specialty->desc,
            'active' => $specialty->active,
        ];
    }
}
