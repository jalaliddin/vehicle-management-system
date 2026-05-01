<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Waybill;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WaybillController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Waybill::with([
            'organization:id,name,short_name',
            'vehicle:id,state_number,model',
            'driver:id,full_name',
        ])
            ->when(! $user->isHqDispatcher(), fn ($q) => $q->where('organization_id', $user->organization_id))
            ->when($request->organization_id, fn ($q) => $q->where('organization_id', $request->organization_id))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->date_from, fn ($q) => $q->whereDate('created_date', '>=', $request->date_from))
            ->when($request->date_to, fn ($q) => $q->whereDate('created_date', '<=', $request->date_to))
            ->when($request->search, fn ($q) => $q->where('waybill_number', 'like', "%{$request->search}%"))
            ->latest()
            ->paginate($request->per_page ?? 20);

        return response()->json($query);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'organization_id' => ['required', 'exists:organizations,id'],
            'vehicle_id' => ['required', 'exists:vehicles,id'],
            'driver_id' => ['required', 'exists:drivers,id'],
            'vehicle_type' => ['required', 'string'],
            'tabel_number' => ['nullable', 'string'],
            'created_date' => ['required', 'date'],
            'valid_until' => ['required', 'date', 'after_or_equal:created_date'],
            'trip_count' => ['integer', 'min:1'],
            'destination' => ['required', 'string'],
            'destination_organization' => ['nullable', 'string'],
            'planned_km' => ['nullable', 'numeric'],
            'fuel_issued' => ['nullable', 'numeric'],
            'has_ac' => ['boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        $validated['status'] = 'draft';
        $waybill = Waybill::create($validated);

        return response()->json($waybill->load(['organization', 'vehicle', 'driver']), 201);
    }

    public function show(Waybill $waybill): JsonResponse
    {
        return response()->json($waybill->load([
            'organization',
            'vehicle',
            'driver',
            'mechanic:id,name',
            'doctor:id,name',
            'approvedByUser:id,name',
        ]));
    }

    public function update(Request $request, Waybill $waybill): JsonResponse
    {
        $validated = $request->validate([
            'vehicle_id' => ['exists:vehicles,id'],
            'driver_id' => ['exists:drivers,id'],
            'vehicle_type' => ['string'],
            'tabel_number' => ['nullable', 'string'],
            'valid_until' => ['date'],
            'trip_count' => ['integer', 'min:1'],
            'destination' => ['string'],
            'destination_organization' => ['nullable', 'string'],
            'planned_km' => ['nullable', 'numeric'],
            'fuel_issued' => ['nullable', 'numeric'],
            'has_ac' => ['boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        $waybill->update($validated);

        return response()->json($waybill->load(['organization', 'vehicle', 'driver']));
    }

    public function destroy(Waybill $waybill): JsonResponse
    {
        $waybill->delete();

        return response()->json(null, 204);
    }

    public function mechanicCheck(Request $request, Waybill $waybill): JsonResponse
    {
        $request->validate([
            'passed' => ['required', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        $waybill->update([
            'mechanic_id' => $request->user()->id,
            'mechanic_checked_at' => now(),
            'mechanic_notes' => $request->notes,
            'mechanic_passed' => $request->passed,
            'status' => $request->passed ? 'mechanic_ok' : 'cancelled',
        ]);

        return response()->json($waybill->fresh(['mechanic']));
    }

    public function doctorCheck(Request $request, Waybill $waybill): JsonResponse
    {
        $request->validate([
            'passed' => ['required', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        $waybill->update([
            'doctor_id' => $request->user()->id,
            'doctor_checked_at' => now(),
            'doctor_notes' => $request->notes,
            'doctor_passed' => $request->passed,
            'status' => $request->passed ? 'doctor_ok' : 'cancelled',
        ]);

        return response()->json($waybill->fresh(['doctor']));
    }

    public function approve(Request $request, Waybill $waybill): JsonResponse
    {
        $waybill->update([
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'status' => 'approved',
        ]);

        return response()->json($waybill->fresh(['approvedByUser']));
    }

    public function depart(Waybill $waybill): JsonResponse
    {
        $waybill->update([
            'status' => 'in_progress',
            'departed_at' => now(),
        ]);

        return response()->json($waybill);
    }

    public function complete(Request $request, Waybill $waybill): JsonResponse
    {
        $request->validate([
            'actual_km' => ['nullable', 'numeric'],
            'fuel_consumed' => ['nullable', 'numeric'],
            'fuel_returned' => ['nullable', 'numeric'],
        ]);

        $waybill->update([
            'status' => 'completed',
            'arrived_at' => now(),
            'actual_km' => $request->actual_km,
            'fuel_consumed' => $request->fuel_consumed,
            'fuel_returned' => $request->fuel_returned,
        ]);

        return response()->json($waybill);
    }

    public function saveRoute(Request $request, Waybill $waybill): JsonResponse
    {
        $request->validate([
            'coordinates' => ['array'],
            'distance_km' => ['numeric'],
        ]);

        $waybill->update([
            'route_coordinates' => $request->coordinates,
            'actual_km' => $request->distance_km,
        ]);

        return response()->json($waybill);
    }

    public function submitToMechanic(Waybill $waybill): JsonResponse
    {
        $waybill->update(['status' => 'mechanic_check']);

        return response()->json($waybill);
    }
}
