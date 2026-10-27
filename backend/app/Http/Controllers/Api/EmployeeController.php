<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EmployeeListResource;
use App\Http\Resources\EmployeeResource;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Services\Hr\EmploymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeController extends Controller
{
    public function __construct(private EmploymentService $employment) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Employee::class);

        $user = $request->user();

        $employees = Employee::with(['organization:id,name,short_name', 'department:id,name', 'position:id,name'])
            ->when(! $user->canAccessAllOrganizations(), fn ($q) => $q->where('organization_id', $user->organization_id))
            ->when($request->organization_id, fn ($q) => $q->where('organization_id', $request->organization_id))
            ->when($request->department_id, fn ($q) => $q->where('department_id', $request->department_id))
            ->when($request->position_id, fn ($q) => $q->where('position_id', $request->position_id))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->boolean('without_driver'), fn ($q) => $q->whereDoesntHave('driver'))
            ->when($request->search, fn ($q) => $q->where(function ($q2) use ($request) {
                $q2->where('last_name', 'like', "%{$request->search}%")
                    ->orWhere('first_name', 'like', "%{$request->search}%")
                    ->orWhere('middle_name', 'like', "%{$request->search}%")
                    ->orWhere('pinfl', 'like', "%{$request->search}%")
                    ->orWhere('personnel_number', 'like', "%{$request->search}%");
            }))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(50);

        return EmployeeListResource::collection($employees)->response();
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Employee::class);

        $validated = $request->validate(array_merge($this->cardRules(), [
            'organization_id' => ['required', 'exists:organizations,id'],
            'last_name' => ['required', 'string', 'max:100'],
            'first_name' => ['required', 'string', 'max:100'],
            'pinfl' => ['required', 'regex:/^\d{14}$/', 'unique:employees,pinfl'],
            'hire' => ['required', 'array'],
            'hire.effective_date' => ['required', 'date', 'before_or_equal:today'],
            'hire.order_number' => ['required', 'string', 'max:50'],
            'hire.order_date' => ['required', 'date', 'before_or_equal:today'],
            'hire.department_id' => ['nullable', 'integer'],
            'hire.position_id' => ['required', 'exists:positions,id'],
            'hire.notes' => ['nullable', 'string'],
        ]));

        Gate::authorize('access-organization', [(int) $validated['organization_id']]);

        $hire = $validated['hire'];
        unset($validated['hire']);

        $employee = $this->employment->createWithHire($validated, $hire, $request->user());

        return (new EmployeeResource($employee->load(['organization', 'department', 'position'])))->response()->setStatusCode(201);
    }

    public function show(Employee $employee): JsonResponse
    {
        Gate::authorize('view', $employee);

        AuditLog::record($employee, 'viewed');

        return (new EmployeeResource($employee->load(['organization', 'department', 'position', 'driver'])))->response();
    }

    public function update(Request $request, Employee $employee): JsonResponse
    {
        Gate::authorize('update', $employee);

        $validated = $request->validate(array_merge($this->cardRules(), [
            'last_name' => ['string', 'max:100'],
            'first_name' => ['string', 'max:100'],
            'pinfl' => ['regex:/^\d{14}$/', Rule::unique('employees', 'pinfl')->ignore($employee->id)],
        ]));

        DB::transaction(function () use ($employee, $validated) {
            $employee->update($validated);
            $employee->syncDriverProfile();
        });

        return (new EmployeeResource($employee->load(['organization', 'department', 'position', 'driver'])))->response();
    }

    public function destroy(Employee $employee): JsonResponse
    {
        Gate::authorize('delete', $employee);

        $driver = $employee->driver;

        if ($driver?->waybills()->exists()) {
            return response()->json(['message' => "Haydovchining yo'l varaqalari bor, xodimni o'chirib bo'lmaydi"], 422);
        }

        DB::transaction(function () use ($employee, $driver) {
            $driver?->delete();
            $employee->delete();
        });

        return response()->json(null, 204);
    }

    public function uploadPhoto(Request $request, Employee $employee): JsonResponse
    {
        Gate::authorize('update', $employee);

        $request->validate(['photo' => ['required', 'image', 'max:2048']]);

        $path = $request->file('photo')->store('hr/photos', 'local');

        if ($employee->photo_path !== null) {
            Storage::disk('local')->delete($employee->photo_path);
        }

        $employee->update(['photo_path' => $path]);

        return response()->json(['has_photo' => true]);
    }

    public function photo(Employee $employee): StreamedResponse
    {
        Gate::authorize('view', $employee);

        abort_if($employee->photo_path === null, 404);

        return Storage::disk('local')->response($employee->photo_path);
    }

    public function history(Employee $employee): JsonResponse
    {
        Gate::authorize('viewHistory', $employee);

        $user = request()->user();

        return response()->json(
            AuditLog::with('user:id,name')->where('employee_id', $employee->id)->latest('id')->paginate(50)
                ->through(fn (AuditLog $log) => $log->visibleTo($user))
        );
    }

    /**
     * Rules for the card fields both create and update accept.
     *
     * @return array<string, list<mixed>>
     */
    private function cardRules(): array
    {
        return [
            'personnel_number' => ['nullable', 'string', 'max:50'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'passport' => ['nullable', 'string', 'max:20'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', 'in:male,female'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
            'education' => ['nullable', 'string', 'max:255'],
            'clothing_size' => ['nullable', 'string', 'max:10'],
            'shoe_size' => ['nullable', 'string', 'max:10'],
            'employment_type' => ['in:staff,contract,part_time'],
            'annual_leave_days' => ['integer', 'min:0', 'max:120'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
