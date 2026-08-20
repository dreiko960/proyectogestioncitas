<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\ApiController;
use App\Http\Requests\ActivateUserRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Doctor;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** Gestión de usuarios del panel admin (BACKEND.md §5.2). */
class UserController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $users = User::query()
            ->when($request->filled('role'), fn ($q) => $q->where('role', $request->query('role')))
            ->when($request->filled('active'), fn ($q) => $q->where('active', filter_var($request->query('active'), FILTER_VALIDATE_BOOL)))
            ->orderBy('created_at')
            ->paginate((int) $request->query('per_page', 15));

        return $this->success([
            'items' => $users->map(fn ($u) => $this->payload($u))->all(),
            'pagination' => [
                'page' => $users->currentPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
                'last_page' => $users->lastPage(),
            ],
        ]);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->validated();
        $password = Str::password(12);

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password_hash' => Hash::make($password),
            'role' => $data['role'],
            'active' => true,
        ]);

        if (UserRole::tryFrom($data['role']) === UserRole::Medico) {
            Doctor::query()->create([
                'user_id' => $user->id,
                'initials' => $data['doctorData']['initials'],
                'specialty_id' => $data['doctorData']['specialtyId'],
                'consultorio_id' => $data['doctorData']['consultorioId'] ?? null,
                'phone' => $data['doctorData']['phone'] ?? null,
            ]);
        }

        return $this->success([
            'user' => $this->payload($user),
            'password' => $password,
        ], 201);
    }

    public function update(UpdateUserRequest $request, string $id): JsonResponse
    {
        $user = User::query()->find($id);

        if (! $user) {
            return $this->error('Usuario no encontrado', 404);
        }

        $user->update([
            'name' => $request->validated('name'),
            'role' => $request->validated('role'),
        ]);

        return $this->success($this->payload($user->refresh()));
    }

    public function activate(ActivateUserRequest $request, string $id): JsonResponse
    {
        $user = User::query()->find($id);

        if (! $user) {
            return $this->error('Usuario no encontrado', 404);
        }

        $user->update(['active' => $request->validated('active')]);

        return $this->success($this->payload($user->refresh()));
    }

    private function payload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
            'role_label' => $user->role->label(),
            'active' => $user->active,
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }
}
