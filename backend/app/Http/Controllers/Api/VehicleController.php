<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class VehicleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Vehicle::class);

        $user = $request->user();

        $query = Vehicle::with(['organization:id,name,short_name', 'driver:id,full_name,vehicle_id'])
            ->when(! $user->canAccessAllOrganizations(), fn ($q) => $q->where('organization_id', $user->organization_id))
            ->when($request->organization_id, fn ($q) => $q->where('organization_id', $request->organization_id))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->vehicle_type, fn ($q) => $q->where('vehicle_type', $request->vehicle_type))
            ->when($request->search, fn ($q) => $q->where(function ($q2) use ($request) {
                $q2->where('state_number', 'like', "%{$request->search}%")
                    ->orWhere('model', 'like', "%{$request->search}%");
            }));

        return response()->json($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Vehicle::class);

        $validated = $request->validate([
            'organization_id' => ['required', 'exists:organizations,id'],
            'state_number' => ['required', 'string', 'unique:vehicles,state_number'],
            'model' => ['required', 'string', 'max:100'],
            'manufacture_year' => ['required', 'integer', 'min:1900', 'max:'.date('Y')],
            'chassis_number' => ['nullable', 'string'],
            'body_type' => ['nullable', 'string'],
            'tech_passport_series' => ['nullable', 'string'],
            'tech_passport_number' => ['nullable', 'string'],
            'engine_number' => ['nullable', 'string'],
            'empty_weight' => ['nullable', 'numeric'],
            'full_weight' => ['nullable', 'numeric'],
            'color' => ['nullable', 'string'],
            'engine_power' => ['nullable', 'integer'],
            'seat_count' => ['nullable', 'integer'],
            'vehicle_type' => ['required', 'string'],
            'fuel_norm' => ['nullable', 'numeric'],
            'fuel_norm_ac' => ['nullable', 'numeric'],
            'fuel_type' => ['required', 'string'],
            'tank_capacity' => ['nullable', 'numeric', 'min:0', 'max:2000'],
            'odometer' => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'status' => ['in:active,broken,maintenance,inactive'],
            'notes' => ['nullable', 'string'],
        ]);

        Gate::authorize('access-organization', [(int) $validated['organization_id']]);

        $vehicle = Vehicle::create($validated);

        return response()->json($vehicle->load('organization'), 201);
    }

    public function show(Vehicle $vehicle): JsonResponse
    {
        Gate::authorize('view', $vehicle);

        return response()->json($vehicle->load(['organization', 'driver', 'waybills' => fn ($q) => $q->latest()->take(10)]));
    }

    public function update(Request $request, Vehicle $vehicle): JsonResponse
    {
        Gate::authorize('update', $vehicle);

        $validated = $request->validate([
            'organization_id' => ['exists:organizations,id'],
            'state_number' => ['string', 'unique:vehicles,state_number,'.$vehicle->id],
            'model' => ['string', 'max:100'],
            'manufacture_year' => ['integer', 'min:1900', 'max:'.date('Y')],
            'chassis_number' => ['nullable', 'string'],
            'body_type' => ['nullable', 'string'],
            'tech_passport_series' => ['nullable', 'string'],
            'tech_passport_number' => ['nullable', 'string'],
            'engine_number' => ['nullable', 'string'],
            'empty_weight' => ['nullable', 'numeric'],
            'full_weight' => ['nullable', 'numeric'],
            'color' => ['nullable', 'string'],
            'engine_power' => ['nullable', 'integer'],
            'seat_count' => ['nullable', 'integer'],
            'vehicle_type' => ['string'],
            'fuel_norm' => ['nullable', 'numeric'],
            'fuel_norm_ac' => ['nullable', 'numeric'],
            'fuel_type' => ['string'],
            'tank_capacity' => ['nullable', 'numeric', 'min:0', 'max:2000'],
            'odometer' => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'status' => ['in:active,broken,maintenance,inactive'],
            'notes' => ['nullable', 'string'],
        ]);

        if (isset($validated['organization_id'])) {
            Gate::authorize('access-organization', [(int) $validated['organization_id']]);
        }

        $vehicle->update($validated);

        return response()->json($vehicle->load('organization'));
    }

    public function destroy(Vehicle $vehicle): JsonResponse
    {
        Gate::authorize('delete', $vehicle);

        $vehicle->delete();

        return response()->json(null, 204);
    }

    public function updateStatus(Request $request, Vehicle $vehicle): JsonResponse
    {
        Gate::authorize('update', $vehicle);

        $request->validate([
            'status' => ['required', 'in:active,broken,maintenance,inactive'],
        ]);

        $vehicle->update(['status' => $request->status]);

        return response()->json($vehicle);
    }
}
