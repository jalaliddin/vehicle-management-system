<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\Employee;
use App\Services\Hr\DriverClearance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class DriverController extends Controller
{
    public function __construct(private DriverClearance $clearance) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Driver::class);

        $user = $request->user();

        $drivers = Driver::with(['organization:id,name,short_name', 'vehicle:id,state_number,model'])
            ->when(! $user->canAccessAllOrganizations(), fn ($q) => $q->where('organization_id', $user->organization_id))
            ->when($request->organization_id, fn ($q) => $q->where('organization_id', $request->organization_id))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->search, fn ($q) => $q->where(function ($q2) use ($request) {
                $q2->where('full_name', 'like', "%{$request->search}%")
                    ->orWhere('license_number', 'like', "%{$request->search}%")
                    ->orWhere('pinfl', 'like', "%{$request->search}%");
            }))
            ->get();

        return response()->json($drivers);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Driver::class);

        $previous = $this->deletedProfileOf(Employee::find($request->employee_id));

        $validated = $request->validate([
            'organization_id' => ['required', 'exists:organizations,id'],
            'employee_id' => [
                'required',
                Rule::exists('employees', 'id')->where('organization_id', $request->organization_id)->where('status', 'active')->withoutTrashed(),
                Rule::unique('drivers', 'employee_id')->withoutTrashed(),
            ],
            'license_number' => ['required', 'string', Rule::unique('drivers', 'license_number')->ignore($previous?->id)],
            'license_category' => ['required', 'string', 'max:20'],
            'vehicle_id' => ['nullable', $this->vehicleInOrganization($request->organization_id)],
            'work_experience' => ['integer', 'min:0'],
            'license_expiry' => ['nullable', 'date'],
            'employment_type' => ['in:staff,contract,part_time'],
            'status' => ['in:active,inactive,sick_leave,vacation'],
            'notes' => ['nullable', 'string'],
        ]);

        Gate::authorize('access-organization', [(int) $validated['organization_id']]);

        $employee = Employee::findOrFail($validated['employee_id']);
        $attributes = array_merge($validated, [
            'full_name' => $employee->full_name,
            'pinfl' => $employee->pinfl,
            'phone' => $employee->phone,
        ]);

        $driver = DB::transaction(function () use ($previous, $attributes) {
            if ($previous === null) {
                return Driver::create($attributes);
            }

            $previous->restore();
            $previous->update($attributes);

            return $previous;
        });

        return response()->json($driver->load(['organization', 'vehicle']), 201);
    }

    /**
     * A deleted driver profile of this person — by employee or, for legacy
     * rows, by PINFL — is brought back instead of colliding with it.
     */
    private function deletedProfileOf(?Employee $employee): ?Driver
    {
        if ($employee === null) {
            return null;
        }

        return Driver::onlyTrashed()
            ->where(fn ($q) => $q->where('employee_id', $employee->id)->orWhere('pinfl', $employee->pinfl))
            ->first();
    }

    public function show(Driver $driver): JsonResponse
    {
        Gate::authorize('view', $driver);

        return response()->json(array_merge(
            $driver->load(['organization', 'vehicle', 'waybills' => fn ($q) => $q->latest()->take(10)])->toArray(),
            ['clearance' => $this->clearance->check($driver)],
        ));
    }

    public function clearance(Driver $driver): JsonResponse
    {
        Gate::authorize('view', $driver);

        return response()->json($this->clearance->check($driver));
    }

    public function update(Request $request, Driver $driver): JsonResponse
    {
        Gate::authorize('update', $driver);

        $validated = $request->validate([
            'organization_id' => ['exists:organizations,id'],
            'full_name' => ['string', 'max:255'],
            'license_number' => ['string', 'unique:drivers,license_number,'.$driver->id],
            'license_category' => ['string', 'max:20'],
            'vehicle_id' => ['nullable', $this->vehicleInOrganization(
                $driver->employee_id !== null ? $driver->organization_id : ($request->organization_id ?? $driver->organization_id),
            )],
            'work_experience' => ['integer', 'min:0'],
            'pinfl' => ['string', 'size:14', 'unique:drivers,pinfl,'.$driver->id],
            'phone' => ['nullable', 'string', 'max:20'],
            'license_expiry' => ['nullable', 'date'],
            'employment_type' => ['in:staff,contract,part_time'],
            'status' => ['in:active,inactive,sick_leave,vacation'],
            'notes' => ['nullable', 'string'],
        ]);

        if ($driver->employee_id !== null) {
            $validated = array_diff_key($validated, array_flip(['organization_id', 'full_name', 'pinfl', 'phone']));
        }

        if (isset($validated['organization_id'])) {
            Gate::authorize('access-organization', [(int) $validated['organization_id']]);
        }

        $driver->update($validated);

        return response()->json($driver->load(['organization', 'vehicle']));
    }

    public function destroy(Driver $driver): JsonResponse
    {
        Gate::authorize('delete', $driver);

        $driver->delete();

        return response()->json(null, 204);
    }

    public function assignVehicle(Request $request, Driver $driver): JsonResponse
    {
        Gate::authorize('update', $driver);

        $request->validate([
            'vehicle_id' => ['nullable', $this->vehicleInOrganization($driver->organization_id)],
        ]);

        $driver->update(['vehicle_id' => $request->vehicle_id]);

        return response()->json($driver->load('vehicle'));
    }

    /**
     * A driver can only be assigned a vehicle of their own organization.
     */
    private function vehicleInOrganization(mixed $organizationId): Exists
    {
        return Rule::exists('vehicles', 'id')->where('organization_id', $organizationId)->withoutTrashed();
    }
}
