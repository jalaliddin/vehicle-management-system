<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class DriverController extends Controller
{
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

        $validated = $request->validate([
            'organization_id' => ['required', 'exists:organizations,id'],
            'full_name' => ['required', 'string', 'max:255'],
            'license_number' => ['required', 'string', 'unique:drivers,license_number'],
            'license_category' => ['required', 'string', 'max:20'],
            'vehicle_id' => ['nullable', $this->vehicleInOrganization($request->organization_id)],
            'work_experience' => ['integer', 'min:0'],
            'pinfl' => ['required', 'string', 'size:14', 'unique:drivers,pinfl'],
            'phone' => ['nullable', 'string', 'max:20'],
            'license_expiry' => ['nullable', 'date'],
            'employment_type' => ['in:staff,contract,part_time'],
            'status' => ['in:active,inactive,sick_leave,vacation'],
            'notes' => ['nullable', 'string'],
        ]);

        Gate::authorize('access-organization', [(int) $validated['organization_id']]);

        $driver = Driver::create($validated);

        return response()->json($driver->load(['organization', 'vehicle']), 201);
    }

    public function show(Driver $driver): JsonResponse
    {
        Gate::authorize('view', $driver);

        return response()->json($driver->load(['organization', 'vehicle', 'waybills' => fn ($q) => $q->latest()->take(10)]));
    }

    public function update(Request $request, Driver $driver): JsonResponse
    {
        Gate::authorize('update', $driver);

        $validated = $request->validate([
            'organization_id' => ['exists:organizations,id'],
            'full_name' => ['string', 'max:255'],
            'license_number' => ['string', 'unique:drivers,license_number,'.$driver->id],
            'license_category' => ['string', 'max:20'],
            'vehicle_id' => ['nullable', $this->vehicleInOrganization($request->organization_id ?? $driver->organization_id)],
            'work_experience' => ['integer', 'min:0'],
            'pinfl' => ['string', 'size:14', 'unique:drivers,pinfl,'.$driver->id],
            'phone' => ['nullable', 'string', 'max:20'],
            'license_expiry' => ['nullable', 'date'],
            'employment_type' => ['in:staff,contract,part_time'],
            'status' => ['in:active,inactive,sick_leave,vacation'],
            'notes' => ['nullable', 'string'],
        ]);

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
