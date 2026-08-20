<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ApiController;
use App\Http\Requests\StoreConsultorioRequest;
use App\Http\Requests\UpdateConsultorioRequest;
use App\Models\Consultorio;
use Illuminate\Http\JsonResponse;

/** Catálogo de consultorios (BACKEND.md §5.3). */
class ConsultorioController extends ApiController
{
    public function index(): JsonResponse
    {
        $consultorios = Consultorio::query()->where('activo', true)->orderBy('piso')->orderBy('nombre')->get();

        return $this->success($consultorios->map(fn ($c) => $this->payload($c))->all());
    }

    public function store(StoreConsultorioRequest $request): JsonResponse
    {
        $consultorio = Consultorio::query()->create($request->validated());

        $consultorio->specialties()->sync($request->validated('specialtyIds', []));

        return $this->success($this->payload($consultorio->refresh()), 201);
    }

    public function update(UpdateConsultorioRequest $request, string $id): JsonResponse
    {
        $consultorio = Consultorio::query()->find($id);

        if (! $consultorio) {
            return $this->error('Consultorio no encontrado', 404);
        }

        $consultorio->update($request->validated());

        if ($request->has('specialtyIds')) {
            $consultorio->specialties()->sync($request->validated('specialtyIds'));
        }

        return $this->success($this->payload($consultorio->refresh()));
    }

    private function payload(Consultorio $consultorio): array
    {
        return [
            'id' => $consultorio->id,
            'nombre' => $consultorio->nombre,
            'piso' => $consultorio->piso,
            'area' => $consultorio->area,
            'activo' => $consultorio->activo,
            'specialtyIds' => $consultorio->specialties()->pluck('specialties.id')->all(),
        ];
    }
}
