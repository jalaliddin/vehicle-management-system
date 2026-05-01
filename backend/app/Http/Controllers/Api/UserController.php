<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (! $request->user()->isSuperAdmin()) {
            return response()->json(['message' => 'Ruxsat yo\'q.'], 403);
        }

        $users = User::with('organization')
            ->when($request->org_id, fn ($q) => $q->where('organization_id', $request->org_id))
            ->when($request->role, fn ($q) => $q->where('role', $request->role))
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
        if (! $request->user()->isSuperAdmin()) {
            return response()->json(['message' => 'Ruxsat yo\'q.'], 403);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'role' => ['required', Rule::in(['hq_dispatcher', 'dispatcher', 'mechanic', 'doctor', 'viewer'])],
            'organization_id' => ['nullable', 'exists:organizations,id'],
            'is_active' => ['boolean'],
        ]);

        $data['password'] = Hash::make($data['password']);
        $user = User::create($data);

        return response()->json($this->format($user->load('organization')), 201);
    }

    public function show(Request $request, User $user): JsonResponse
    {
        if (! $request->user()->isSuperAdmin()) {
            return response()->json(['message' => 'Ruxsat yo\'q.'], 403);
        }

        return response()->json($this->format($user->load('organization')));
    }

    public function update(Request $request, User $user): JsonResponse
    {
        if (! $request->user()->isSuperAdmin()) {
            return response()->json(['message' => 'Ruxsat yo\'q.'], 403);
        }

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', Rule::unique('users')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:6'],
            'role' => ['sometimes', Rule::in(['hq_dispatcher', 'dispatcher', 'mechanic', 'doctor', 'viewer'])],
            'organization_id' => ['nullable', 'exists:organizations,id'],
            'is_active' => ['boolean'],
        ]);

        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);

        return response()->json($this->format($user->load('organization')));
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if (! $request->user()->isSuperAdmin()) {
            return response()->json(['message' => 'Ruxsat yo\'q.'], 403);
        }

        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'O\'zingizni o\'chirib bo\'lmaydi.'], 422);
        }

        $user->tokens()->delete();
        $user->delete();

        return response()->json(['message' => 'O\'chirildi.']);
    }

    public function toggleActive(Request $request, User $user): JsonResponse
    {
        if (! $request->user()->isSuperAdmin()) {
            return response()->json(['message' => 'Ruxsat yo\'q.'], 403);
        }

        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'O\'z akkauntingizni o\'chirib bo\'lmaydi.'], 422);
        }

        $user->update(['is_active' => ! $user->is_active]);

        return response()->json($this->format($user->load('organization')));
    }

    private function format(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
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
