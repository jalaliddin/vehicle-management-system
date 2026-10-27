<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FuelShift;
use App\Models\FuelStation;
use App\Models\FuelTransaction;
use App\Services\Fuel\FuelLedger;
use App\Support\Fuel\Quantity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class FuelShiftController extends Controller
{
    public function __construct(private FuelLedger $ledger) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', FuelStation::class);

        $request->validate([
            'station_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'with_discrepancy' => ['nullable', 'boolean'],
        ]);

        $shifts = FuelShift::with(['station:id,name,fuel_type', 'openedBy:id,name', 'closedBy:id,name'])
            ->whereIn('fuel_station_id', FuelStation::accessibleBy($request->user())->select('id'))
            ->when($request->station_id, fn ($q) => $q->where('fuel_station_id', $request->station_id))
            ->when($request->from, fn ($q) => $q->whereDate('opened_at', '>=', $request->from))
            ->when($request->to, fn ($q) => $q->whereDate('opened_at', '<=', $request->to))
            ->when($request->boolean('with_discrepancy'), fn ($q) => $q->where('status', 'closed')->where('discrepancy', '!=', 0))
            ->latest('opened_at')
            ->latest('id')
            ->paginate(50);

        return response()->json($shifts);
    }

    public function current(FuelStation $station): JsonResponse
    {
        Gate::authorize('view', $station);

        $shift = $station->openShift()->first();

        return response()->json($shift === null ? ['shift' => null, 'operations' => [], 'totals' => null] : $this->report($shift));
    }

    public function store(Request $request, FuelStation $station): JsonResponse
    {
        Gate::authorize('operate', $station);

        $validated = $request->validate(['opening_measured' => ['required', 'numeric', 'min:0']]);

        $shift = $this->ledger->openShift($station, $validated['opening_measured'], $request->user());

        return response()->json($shift->load(['openedBy:id,name', 'station:id,name,fuel_type']), 201);
    }

    public function close(Request $request, FuelShift $shift): JsonResponse
    {
        Gate::authorize('operate', $shift->station);

        $validated = $request->validate([
            'closing_measured' => ['required', 'numeric', 'min:0'],
            'discrepancy_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $shift = $this->ledger->closeShift($shift, $validated['closing_measured'], $validated['discrepancy_note'] ?? null, $request->user());

        return response()->json($shift->load(['openedBy:id,name', 'closedBy:id,name']));
    }

    public function show(FuelShift $shift): JsonResponse
    {
        Gate::authorize('view', $shift->station);

        return response()->json($this->report($shift));
    }

    /**
     * The shift with its operations, newest first, and its totals.
     *
     * @return array{shift: FuelShift, operations: mixed, totals: array{receipts: string, refuels: string}}
     */
    private function report(FuelShift $shift): array
    {
        $operations = $shift->transactions()
            ->with(['vehicle:id,state_number,model', 'driver:id,full_name', 'supplier:id,name', 'waybill:id,waybill_number', 'createdBy:id,name'])
            ->latest('id')
            ->get();

        $total = fn (string $kind) => Quantity::format($operations
            ->filter(fn (FuelTransaction $t) => $t->kind === $kind && $t->cancelled_at === null)
            ->sum(fn (FuelTransaction $t) => Quantity::toHundredths($t->amount)));

        return [
            'shift' => $shift->load(['station:id,name,fuel_type,organization_id', 'openedBy:id,name', 'closedBy:id,name']),
            'operations' => $operations,
            'totals' => ['receipts' => $total(FuelTransaction::KIND_RECEIPT), 'refuels' => $total(FuelTransaction::KIND_REFUEL)],
        ];
    }
}
