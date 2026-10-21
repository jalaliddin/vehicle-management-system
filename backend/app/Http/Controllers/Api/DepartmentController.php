<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

class DepartmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Department::class);

        $user = $request->user();

        $departments = Department::query()
            ->when(! $user->canAccessAllOrganizations(), fn ($q) => $q->where('organization_id', $user->organization_id))
            ->when($request->organization_id, fn ($q) => $q->where('organization_id', $request->organization_id))
            ->orderBy('name')
            ->get();

        return response()->json($departments);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Department::class);

        $validated = $request->validate([
            'organization_id' => ['required', 'exists:organizations,id'],
            'parent_id' => ['nullable', $this->departmentOf($request->organization_id)],
            'name' => ['required', 'string', 'max:255', $this->uniqueAmongSiblings($request->organization_id, $request->parent_id)],
            'code' => ['nullable', 'string', 'max:50'],
            'is_active' => ['boolean'],
        ]);

        Gate::authorize('access-organization', [(int) $validated['organization_id']]);

        return response()->json(Department::create($validated), 201);
    }

    public function show(Department $department): JsonResponse
    {
        Gate::authorize('view', $department);

        return response()->json($department->load('parent:id,name'));
    }

    public function update(Request $request, Department $department): JsonResponse
    {
        Gate::authorize('update', $department);

        $parentId = $request->has('parent_id') ? $request->parent_id : $department->parent_id;

        $validated = $request->validate([
            'parent_id' => ['nullable', $this->departmentOf($department->organization_id), Rule::notIn([$department->id])],
            'name' => ['string', 'max:255', $this->uniqueAmongSiblings($department->organization_id, $parentId)->ignore($department->id)],
            'code' => ['nullable', 'string', 'max:50'],
            'is_active' => ['boolean'],
        ]);

        if ($parentId !== null && $this->isDescendant((int) $parentId, $department)) {
            return response()->json(['message' => "Bo'limni o'zining ichki bo'limiga ko'chirib bo'lmaydi"], 422);
        }

        $department->update($validated);

        return response()->json($department);
    }

    public function destroy(Department $department): JsonResponse
    {
        Gate::authorize('delete', $department);

        if ($department->children()->exists()) {
            return response()->json(['message' => "Ichki bo'limlar mavjud"], 422);
        }

        $department->delete();

        return response()->json(null, 204);
    }

    /**
     * A parent department must belong to the same organization.
     */
    private function departmentOf(mixed $organizationId): Exists
    {
        return Rule::exists('departments', 'id')->where('organization_id', $organizationId);
    }

    /**
     * Department names are unique among the children of one parent.
     */
    private function uniqueAmongSiblings(mixed $organizationId, mixed $parentId): Unique
    {
        return Rule::unique('departments', 'name')
            ->where('organization_id', $organizationId)
            ->where(fn ($q) => $parentId ? $q->where('parent_id', $parentId) : $q->whereNull('parent_id'));
    }

    private function isDescendant(int $candidateId, Department $department): bool
    {
        $current = Department::find($candidateId);

        while ($current !== null) {
            if ($current->id === $department->id) {
                return true;
            }

            $current = $current->parent;
        }

        return false;
    }
}
