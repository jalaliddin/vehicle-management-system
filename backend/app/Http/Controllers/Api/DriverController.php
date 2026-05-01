<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DriverController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $drivers = Driver::with(['organization:id,name,short_name', 'vehicle:id,state_number,model'])
            ->when(! $user->isHqDispatcher(), fn ($q) => $q->where('organization_id', $user->organization_id))
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
        $validated = $request->validate([
            'organization_id' => ['required', 'exists:organizations,id'],
            'full_name' => ['required', 'string', 'max:255'],
            'license_number' => ['required', 'string', 'unique:drivers,license_number'],
            'license_category' => ['required', 'string', 'max:20'],
            'vehicle_id' => ['nullable', 'exists:vehicles,id'],
            'work_experience' => ['integer', 'min:0'],
            'pinfl' => ['required', 'string', 'size:14', 'unique:drivers,pinfl'],
            'phone' => ['nullable', 'string', 'max:20'],
            'license_expiry' => ['nullable', 'date'],
            'employment_type' => ['in:staff,contract,part_time'],
            'status' => ['in:active,inactive,sick_leave,vacation'],
            'notes' => ['nullable', 'string'],
        ]);

        $driver = Driver::create($validated);

        return response()->json($driver->load(['organization', 'vehicle']), 201);
    }

    public function show(Driver $driver): JsonResponse
    {
        return response()->json($driver->load(['organization', 'vehicle', 'waybills' => fn ($q) => $q->latest()->take(10)]));
    }

    public function update(Request $request, Driver $driver): JsonResponse
    {
        $validated = $request->validate([
            'organization_id' => ['exists:organizations,id'],
            'full_name' => ['string', 'max:255'],
            'license_number' => ['string', 'unique:drivers,license_number,'.$driver->id],
            'license_category' => ['string', 'max:20'],
            'vehicle_id' => ['nullable', 'exists:vehicles,id'],
            'work_experience' => ['integer', 'min:0'],
            'pinfl' => ['string', 'size:14', 'unique:drivers,pinfl,'.$driver->id],
            'phone' => ['nullable', 'string', 'max:20'],
            'license_expiry' => ['nullable', 'date'],
            'employment_type' => ['in:staff,contract,part_time'],
            'status' => ['in:active,inactive,sick_leave,vacation'],
            'notes' => ['nullable', 'string'],
        ]);

        $driver->update($validated);

        return response()->json($driver->load(['organization', 'vehicle']));
    }

    public function destroy(Driver $driver): JsonResponse
    {
        $driver->delete();

        return response()->json(null, 204);
    }

    public function assignVehicle(Request $request, Driver $driver): JsonResponse
    {
        $request->validate([
            'vehicle_id' => ['nullable', 'exists:vehicles,id'],
        ]);

        $driver->update(['vehicle_id' => $request->vehicle_id]);

        return response()->json($driver->load('vehicle'));
    }
}
