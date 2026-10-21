<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Waybill;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class WaybillController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Waybill::class);

        $user = $request->user();

        $query = Waybill::with([
            'organization:id,name,short_name',
            'vehicle:id,state_number,model',
            'driver:id,full_name',
        ])
            ->when(! $user->canAccessAllOrganizations(), fn ($q) => $q->where('organization_id', $user->organization_id))
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
        Gate::authorize('create', Waybill::class);

        $validated = $request->validate([
            'organization_id' => ['required', 'exists:organizations,id'],
            'vehicle_id' => ['required', $this->existsInOrganization('vehicles', $request->organization_id)],
            'driver_id' => ['required', $this->existsInOrganization('drivers', $request->organization_id)],
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

        Gate::authorize('access-organization', [(int) $validated['organization_id']]);

        $validated['status'] = 'draft';
        $waybill = Waybill::create($validated);

        return response()->json($waybill->load(['organization', 'vehicle', 'driver']), 201);
    }

    public function show(Waybill $waybill): JsonResponse
    {
        Gate::authorize('view', $waybill);

        return response()->json($waybill->load([
            'organization',
            'vehicle',
            'driver',
            'mechanic:id,name',
            'doctor:id,name',
            'approvedByUser:id,name',
        ]));
    }

    public function publicShow(Waybill $waybill): JsonResponse
    {
        return response()->json([
            'waybill_number' => $waybill->waybill_number,
            'status' => $waybill->status,
            'created_date' => $waybill->created_date,
            'destination' => $waybill->destination,
            'planned_km' => $waybill->planned_km,
            'actual_km' => $waybill->actual_km,
            'fuel_issued' => $waybill->fuel_issued,
            'fuel_consumed' => $waybill->fuel_consumed,
            'mechanic_passed' => $waybill->mechanic_passed,
            'mechanic_notes' => $waybill->mechanic_notes,
            'doctor_passed' => $waybill->doctor_passed,
            'doctor_notes' => $waybill->doctor_notes,
            'organization' => $waybill->organization ? [
                'name' => $waybill->organization->name,
                'short_name' => $waybill->organization->short_name,
            ] : null,
            'vehicle' => $waybill->vehicle ? [
                'state_number' => $waybill->vehicle->state_number,
                'model' => $waybill->vehicle->model,
                'fuel_type' => $waybill->vehicle->fuel_type,
            ] : null,
            'driver' => $waybill->driver ? [
                'full_name' => $waybill->driver->full_name,
                'license_category' => $waybill->driver->license_category,
            ] : null,
            'mechanic' => $waybill->mechanic?->name,
            'doctor' => $waybill->doctor?->name,
        ]);
    }

    public function update(Request $request, Waybill $waybill): JsonResponse
    {
        Gate::authorize('update', $waybill);
        $this->ensureStatusAllows($waybill, 'update');

        $validated = $request->validate([
            'vehicle_id' => [$this->existsInOrganization('vehicles', $waybill->organization_id)],
            'driver_id' => [$this->existsInOrganization('drivers', $waybill->organization_id)],
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
        Gate::authorize('delete', $waybill);
        $this->ensureStatusAllows($waybill, 'delete');

        $waybill->delete();

        return response()->json(null, 204);
    }

    public function mechanicCheck(Request $request, Waybill $waybill): JsonResponse
    {
        Gate::authorize('mechanicCheck', $waybill);
        $this->ensureStatusAllows($waybill, 'mechanicCheck');

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
        Gate::authorize('doctorCheck', $waybill);
        $this->ensureStatusAllows($waybill, 'doctorCheck');

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
        Gate::authorize('approve', $waybill);
        $this->ensureStatusAllows($waybill, 'approve');

        $waybill->update([
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'status' => 'approved',
        ]);

        return response()->json($waybill->fresh(['approvedByUser']));
    }

    public function depart(Waybill $waybill): JsonResponse
    {
        Gate::authorize('depart', $waybill);
        $this->ensureStatusAllows($waybill, 'depart');

        $waybill->update([
            'status' => 'in_progress',
            'departed_at' => now(),
        ]);

        return response()->json($waybill);
    }

    public function complete(Request $request, Waybill $waybill): JsonResponse
    {
        Gate::authorize('complete', $waybill);
        $this->ensureStatusAllows($waybill, 'complete');

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
        Gate::authorize('complete', $waybill);
        $this->ensureStatusAllows($waybill, 'saveRoute');

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
        Gate::authorize('submit', $waybill);
        $this->ensureStatusAllows($waybill, 'submit');

        $waybill->update(['status' => 'mechanic_check']);

        return response()->json($waybill);
    }

    private function ensureStatusAllows(Waybill $waybill, string $action): void
    {
        abort_unless($waybill->allowsAction($action), 422, 'Varaqa holati bu amalga mos emas.');
    }

    /**
     * A vehicle or driver on a waybill must belong to the waybill's organization.
     */
    private function existsInOrganization(string $table, mixed $organizationId): Exists
    {
        return Rule::exists($table, 'id')->where('organization_id', $organizationId)->withoutTrashed();
    }
}
