<?php

namespace App\Http\Controllers\Api;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', Role::class);

        $roles = Role::with('permissions')
            ->withCount('users')
            ->orderByDesc('is_system')
            ->orderBy('id')
            ->get()
            ->map(fn (Role $role) => $this->format($role));

        return response()->json($roles);
    }

    public function permissions(): JsonResponse
    {
        Gate::authorize('viewAny', Role::class);

        return response()->json(Permission::grouped());
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Role::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['array'],
            'permissions.*' => [Rule::enum(Permission::class)],
        ]);

        $role = DB::transaction(function () use ($data) {
            $role = Role::create([
                'name' => $data['name'],
                'slug' => $this->uniqueSlug($data['name']),
                'description' => $data['description'] ?? null,
                'is_system' => false,
            ]);

            $role->syncPermissions($data['permissions'] ?? []);

            return $role;
        });

        return response()->json($this->format($role->load('permissions')->loadCount('users')), 201);
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        Gate::authorize('update', $role);

        if ($role->isSuperAdmin()) {
            return response()->json(['message' => 'Super Admin rolini o\'zgartirib bo\'lmaydi.'], 422);
        }

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255', Rule::unique('roles')->ignore($role->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => [Rule::enum(Permission::class)],
        ]);

        DB::transaction(function () use ($role, $data) {
            $role->update(Arr::only($data, ['name', 'description']));

            if (array_key_exists('permissions', $data)) {
                $role->syncPermissions($data['permissions']);
            }
        });

        return response()->json($this->format($role->load('permissions')->loadCount('users')));
    }

    public function destroy(Role $role): JsonResponse
    {
        Gate::authorize('delete', $role);

        if ($role->is_system) {
            return response()->json(['message' => 'Tizim rolini o\'chirib bo\'lmaydi.'], 422);
        }

        if ($role->users()->exists()) {
            return response()->json(['message' => 'Bu rolga foydalanuvchilar biriktirilgan.'], 422);
        }

        $role->delete();

        return response()->json(null, 204);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name, '_') ?: 'role';
        $slug = $base;

        for ($suffix = 2; Role::where('slug', $slug)->exists(); $suffix++) {
            $slug = "{$base}_{$suffix}";
        }

        return $slug;
    }

    private function format(Role $role): array
    {
        return [
            'id' => $role->id,
            'name' => $role->name,
            'slug' => $role->slug,
            'description' => $role->description,
            'is_system' => $role->is_system,
            'is_super_admin' => $role->isSuperAdmin(),
            'users_count' => $role->users_count,
            'permissions' => $role->permissionKeys(),
        ];
    }
}
