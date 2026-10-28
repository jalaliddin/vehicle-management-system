<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FuelStation;
use App\Models\FuelTransaction;
use App\Models\Vehicle;
use App\Models\Waybill;
use App\Services\Fuel\FuelLedger;
use App\Support\Fuel\Quantity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class FuelOperationController extends Controller
{
    private const RELATIONS = ['vehicle:id,state_number,model', 'driver:id,full_name', 'waybill:id,waybill_number', 'supplier:id,name'];

    public function __construct(private FuelLedger $ledger) {}

    public function receipt(Request $request, FuelStation $station): JsonResponse
    {
        Gate::authorize('operate', $station);

        $validated = $request->validate([
            'fuel_supplier_id' => ['required', Rule::exists('fuel_suppliers', 'id')->where('is_active', true)],
            'document_number' => ['required', 'string', 'max:50'],
            'document_date' => ['required', 'date', 'before_or_equal:today'],
            'document_amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:'.Quantity::MAX],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:'.Quantity::MAX],
            'price_per_liter' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:999999.99'],
            'notes' => ['nullable', 'string'],
        ]);

        $transaction = $this->ledger->receive($station, $validated, $request->user());

        return response()->json($transaction->load(self::RELATIONS), 201);
    }

    public function refuel(Request $request, FuelStation $station): JsonResponse
    {
        Gate::authorize('operate', $station);

        $validated = $request->validate([
            'vehicle_id' => ['required', Rule::exists('vehicles', 'id')
                ->where('organization_id', $station->organization_id)
                ->whereNot('status', 'inactive')
                ->withoutTrashed()],
            'odometer' => ['required', 'integer', 'min:0', 'max:9999999'],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:'.Quantity::MAX],
            'driver_id' => ['nullable', Rule::exists('drivers', 'id')->where('organization_id', $station->organization_id)->withoutTrashed()],
            'notes' => ['nullable', 'string'],
        ]);

        $transaction = $this->ledger->refuel($station, $validated, $request->user());

        return response()->json($transaction->load(self::RELATIONS), 201);
    }

    public function cancel(Request $request, FuelTransaction $transaction): JsonResponse
    {
        Gate::authorize('operate', $transaction->fuelStation);

        $validated = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        $this->ledger->cancel($transaction, $validated['reason'], $request->user());

        return response()->json($transaction->fresh()->load(self::RELATIONS));
    }

    public function adjustment(Request $request, FuelStation $station): JsonResponse
    {
        Gate::authorize('adjust', $station);

        $validated = $request->validate([
            'actual_balance' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:'.Quantity::MAX],
            'document_number' => ['required', 'string', 'max:50'],
            'notes' => ['required', 'string', 'max:1000'],
        ]);

        return response()->json($this->ledger->adjust($station, $validated, $request->user()), 201);
    }

    /**
     * Vehicles of the station's organization for the refuel form.
     */
    public function vehicles(Request $request, FuelStation $station): JsonResponse
    {
        Gate::authorize('operate', $station);

        $search = mb_strtoupper(trim((string) $request->search));

        $vehicles = Vehicle::with('driver:id,full_name,vehicle_id')
            ->where('organization_id', $station->organization_id)
            ->where('status', '!=', 'inactive')
            ->when($search !== '', fn ($q) => $q->where('state_number', 'like', "%{$search}%"))
            ->orderBy('state_number')
            ->limit(20)
            ->get();

        $waybills = Waybill::whereIn('vehicle_id', $vehicles->modelKeys())
            ->whereIn('status', ['approved', 'in_progress'])
            ->orderBy('id')
            ->get(['id', 'vehicle_id', 'waybill_number'])
            ->keyBy('vehicle_id');

        return response()->json($vehicles->map(fn (Vehicle $vehicle) => [
            'id' => $vehicle->id,
            'state_number' => $vehicle->state_number,
            'model' => $vehicle->model,
            'fuel_type' => $vehicle->fuel_type,
            'fuel_type_matches' => $vehicle->fuel_type === $station->fuel_type,
            'tank_capacity' => $vehicle->tank_capacity,
            'odometer' => $vehicle->odometer,
            'driver' => $vehicle->driver?->only(['id', 'full_name']),
            'waybill' => $waybills->get($vehicle->id)?->only(['id', 'waybill_number']),
        ])->values());
    }
}
