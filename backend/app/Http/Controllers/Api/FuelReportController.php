<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FuelShift;
use App\Models\FuelStation;
use App\Models\FuelTransaction;
use App\Support\Fuel\Consumption;
use App\Support\Fuel\Quantity;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class FuelReportController extends Controller
{
    /**
     * Per station: opening balance, receipts, refuels, adjustments and closing balance.
     */
    public function turnover(Request $request): JsonResponse
    {
        [$stations, $from, $to] = $this->scope($request);

        $sums = fn ($query) => $query->active()
            ->whereIn('fuel_station_id', $stations->modelKeys())
            ->selectRaw('fuel_station_id, kind, type, SUM(amount) as total')
            ->groupBy('fuel_station_id', 'kind', 'type')
            ->get()
            ->groupBy('fuel_station_id');

        $before = $sums(FuelTransaction::where('created_at', '<', $from));
        $within = $sums(FuelTransaction::whereBetween('created_at', [$from, $to]));

        $rows = $stations->map(function (FuelStation $station) use ($before, $within) {
            $pick = fn ($rows, ?string $kind, string $type) => ($rows ?? collect())
                ->filter(fn ($row) => ($kind === null || $row->kind === $kind) && $row->type === $type)
                ->sum(fn ($row) => Quantity::toHundredths((string) $row->total));

            $opening = $pick($before->get($station->id), null, 'in') - $pick($before->get($station->id), null, 'out');
            $in = $within->get($station->id);
            $receipts = $pick($in, FuelTransaction::KIND_RECEIPT, 'in');
            $refuels = $pick($in, FuelTransaction::KIND_REFUEL, 'out');
            $adjustmentsIn = $pick($in, FuelTransaction::KIND_ADJUSTMENT, 'in');
            $adjustmentsOut = $pick($in, FuelTransaction::KIND_ADJUSTMENT, 'out');

            return [
                'station_id' => $station->id,
                'station' => $station->name,
                'unit' => $station->unit(),
                'opening' => Quantity::format($opening),
                'receipts' => Quantity::format($receipts),
                'refuels' => Quantity::format($refuels),
                'adjustments_in' => Quantity::format($adjustmentsIn),
                'adjustments_out' => Quantity::format($adjustmentsOut),
                'closing' => Quantity::format($opening + $receipts - $refuels + $adjustmentsIn - $adjustmentsOut),
            ];
        });

        return response()->json($rows->values());
    }

    public function refuels(Request $request): JsonResponse
    {
        [$stations, $from, $to] = $this->scope($request);

        $refuels = $this->refuelsIn($stations, $from, $to);

        $rows = $refuels->map(fn (FuelTransaction $t) => [
            'id' => $t->id,
            'created_at' => $t->created_at->toDateTimeString(),
            'station' => $t->fuelStation->name,
            'vehicle_id' => $t->vehicle_id,
            'state_number' => $t->vehicle?->state_number,
            'driver_id' => $t->driver_id,
            'driver' => $t->driver?->full_name,
            'waybill_number' => $t->waybill?->waybill_number,
            'odometer' => $t->odometer,
            'amount' => $t->amount,
        ]);

        $group = fn (string $key, callable $label) => $refuels->groupBy($key)->map(fn ($group) => array_merge($label($group->first()), [
            'amount' => Quantity::format($group->sum(fn ($t) => Quantity::toHundredths($t->amount))),
        ]))->values();

        return response()->json([
            'rows' => $rows->values(),
            'by_vehicle' => $group('vehicle_id', fn ($t) => ['vehicle_id' => $t->vehicle_id, 'state_number' => $t->vehicle?->state_number]),
            'by_driver' => $group('driver_id', fn ($t) => ['driver_id' => $t->driver_id, 'full_name' => $t->driver?->full_name]),
            'total' => Quantity::format($refuels->sum(fn ($t) => Quantity::toHundredths($t->amount))),
        ]);
    }

    /**
     * Estimated consumption per vehicle compared with its norm.
     */
    public function consumption(Request $request): JsonResponse
    {
        [$stations, $from, $to] = $this->scope($request);
        $tolerance = config('fuel.overconsumption_percent');

        $rows = $this->refuelsIn($stations, $from, $to)
            ->filter(fn (FuelTransaction $t) => $t->vehicle !== null && $t->odometer !== null)
            ->groupBy('vehicle_id')
            ->map(function ($refuels) use ($tolerance) {
                $ordered = $refuels->sortBy([['odometer', 'asc'], ['id', 'asc']])->values();
                $estimate = Consumption::estimate($ordered->map(fn ($t) => ['odometer' => $t->odometer, 'amount' => $t->amount])->all());

                if ($estimate === null) {
                    return null;
                }

                $vehicle = $ordered->first()->vehicle;
                $norm = $vehicle->fuel_norm === null ? null : (float) $vehicle->fuel_norm;
                $deviation = $norm ? round(($estimate['per_100km'] - $norm) / $norm * 100, 1) : null;

                return array_merge([
                    'vehicle_id' => $vehicle->id,
                    'state_number' => $vehicle->state_number,
                    'model' => $vehicle->model,
                ], $estimate, [
                    'norm' => $norm,
                    'deviation_percent' => $deviation,
                    'over_norm' => $deviation !== null && $deviation > $tolerance,
                ]);
            })
            ->filter()
            ->values();

        return response()->json($rows);
    }

    public function discrepancies(Request $request): JsonResponse
    {
        [$stations, $from, $to] = $this->scope($request);

        $rows = FuelShift::with(['station:id,name', 'closedBy:id,name'])
            ->whereIn('fuel_station_id', $stations->modelKeys())
            ->where('status', 'closed')
            ->where('discrepancy', '!=', 0)
            ->whereBetween('closed_at', [$from, $to])
            ->orderBy('closed_at')
            ->get()
            ->map(fn (FuelShift $shift) => [
                'shift_id' => $shift->id,
                'station' => $shift->station->name,
                'closed_by' => $shift->closedBy?->name,
                'closed_at' => $shift->closed_at->toDateTimeString(),
                'closing_balance' => $shift->closing_balance,
                'closing_measured' => $shift->closing_measured,
                'discrepancy' => $shift->discrepancy,
                'note' => $shift->discrepancy_note,
            ]);

        return response()->json($rows);
    }

    /**
     * Accessible stations narrowed by the request, and the period bounds.
     *
     * @return array{0: Collection<int, FuelStation>, 1: CarbonImmutable, 2: CarbonImmutable}
     */
    private function scope(Request $request): array
    {
        Gate::authorize('viewAny', FuelStation::class);

        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'organization_id' => ['nullable', 'integer'],
            'station_id' => ['nullable', 'integer'],
        ]);

        $stations = FuelStation::accessibleBy($request->user())
            ->when($request->organization_id, fn ($q) => $q->where('organization_id', $request->organization_id))
            ->when($request->station_id, fn ($q) => $q->whereKey($request->station_id))
            ->orderBy('name')
            ->get();

        return [
            $stations,
            CarbonImmutable::parse($request->from ?? now()->startOfMonth())->startOfDay(),
            CarbonImmutable::parse($request->to ?? now())->endOfDay(),
        ];
    }

    /**
     * @param  Collection<int, FuelStation>  $stations
     * @return Collection<int, FuelTransaction>
     */
    private function refuelsIn(Collection $stations, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return FuelTransaction::active()
            ->with(['fuelStation:id,name', 'vehicle:id,state_number,model,fuel_norm', 'driver:id,full_name', 'waybill:id,waybill_number'])
            ->where('kind', FuelTransaction::KIND_REFUEL)
            ->whereIn('fuel_station_id', $stations->modelKeys())
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('created_at')
            ->get();
    }
}
