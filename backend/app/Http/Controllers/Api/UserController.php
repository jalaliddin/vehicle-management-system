<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', User::class);

        $actor = $request->user();

        $users = User::with(['organization', 'role'])
            ->when(! $actor->canAccessAllOrganizations(), fn ($q) => $q->where('organization_id', $actor->organization_id))
            ->when($request->org_id, fn ($q) => $q->where('organization_id', $request->org_id))
            ->when($request->role_id, fn ($q) => $q->where('role_id', $request->role_id))
            ->when($request->search, fn ($q) => $q->where(function ($q2) use ($request) {
                $q2->where('name', 'like', "%{$request->search}%")
                    ->orWhere('email', 'like', "%{$request->search}%");
            }))
            ->orderBy('organization_id')
            ->orderBy('name')
            ->get()
            ->map(fn ($u) => $this->format($u));

        return response()->json($users);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', User::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'role_id' => ['required', 'exists:roles,id'],
            'organization_id' => ['nullable', 'exists:organizations,id'],
            'is_active' => ['boolean'],
        ]);

        Gate::authorize('assign', Role::findOrFail($data['role_id']));
        $this->authorizeOrganization($data['organization_id'] ?? null);

        $data['password'] = Hash::make($data['password']);
        $user = User::create($data);

        return response()->json($this->format($user->load(['organization', 'role'])), 201);
    }

    public function show(User $user): JsonResponse
    {
        Gate::authorize('view', $user);

        return response()->json($this->format($user->load(['organization', 'role'])));
    }

    public function update(Request $request, User $user): JsonResponse
    {
        Gate::authorize('update', $user);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', Rule::unique('users')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:6'],
            'role_id' => ['sometimes', 'exists:roles,id'],
            'organization_id' => ['nullable', 'exists:organizations,id'],
            'is_active' => ['boolean'],
        ]);

        if (isset($data['role_id']) && (int) $data['role_id'] !== $user->role_id) {
            if ($user->is($request->user())) {
                return response()->json(['message' => 'O\'z rolingizni o\'zgartirib bo\'lmaydi.'], 422);
            }

            Gate::authorize('assign', Role::findOrFail($data['role_id']));
        }

        if (array_key_exists('organization_id', $data)) {
            $this->authorizeOrganization($data['organization_id']);
        }

        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);

        return response()->json($this->format($user->load(['organization', 'role'])));
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        Gate::authorize('delete', $user);

        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'O\'zingizni o\'chirib bo\'lmaydi.'], 422);
        }

        $user->tokens()->delete();
        $user->delete();

        return response()->json(['message' => 'O\'chirildi.']);
    }

    public function toggleActive(Request $request, User $user): JsonResponse
    {
        Gate::authorize('update', $user);

        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'O\'z akkauntingizni o\'chirib bo\'lmaydi.'], 422);
        }

        $user->update(['is_active' => ! $user->is_active]);

        return response()->json($this->format($user->load(['organization', 'role'])));
    }

    private function authorizeOrganization(mixed $organizationId): void
    {
        Gate::authorize('access-organization', [$organizationId === null ? null : (int) $organizationId]);
    }

    private function format(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role?->slug,
            'role_id' => $user->role_id,
            'role_name' => $user->role?->name,
            'is_active' => $user->is_active,
            'organization_id' => $user->organization_id,
            'organization' => $user->organization ? [
                'id' => $user->organization->id,
                'name' => $user->organization->name,
                'short_name' => $user->organization->short_name,
            ] : null,
            'created_at' => $user->created_at?->format('d.m.Y'),
        ];
    }
}
