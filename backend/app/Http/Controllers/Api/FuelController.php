<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FuelStation;
use App\Models\FuelTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FuelController extends Controller
{
    public function monitoring(Request $request): JsonResponse
    {
        $user = $request->user();

        $stations = FuelStation::with('organization:id,name,short_name')
            ->when(! $user->isHqDispatcher(), fn ($q) => $q->where('organization_id', $user->organization_id))
            ->get()
            ->map(function (FuelStation $station) {
                return array_merge($station->toArray(), [
                    'percentage' => $station->percentage,
                    'is_critical' => $station->current_balance <= $station->min_threshold,
                ]);
            });

        return response()->json($stations);
    }

    public function stationIndex(Request $request): JsonResponse
    {
        $user = $request->user();

        $stations = FuelStation::with('organization:id,name,short_name')
            ->when(! $user->isHqDispatcher(), fn ($q) => $q->where('organization_id', $user->organization_id))
            ->get()
            ->map(fn (FuelStation $s) => array_merge($s->toArray(), ['percentage' => $s->percentage]));

        return response()->json($stations);
    }

    public function stationStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'organization_id' => ['required', 'exists:organizations,id'],
            'name' => ['required', 'string'],
            'fuel_type' => ['required', 'string'],
            'capacity' => ['required', 'numeric', 'min:1'],
            'current_balance' => ['numeric', 'min:0'],
            'min_threshold' => ['numeric', 'min:0'],
        ]);

        $station = FuelStation::create($validated);

        return response()->json($station->load('organization'), 201);
    }

    public function stationUpdate(Request $request, FuelStation $station): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['string'],
            'fuel_type' => ['string'],
            'capacity' => ['numeric', 'min:1'],
            'min_threshold' => ['numeric', 'min:0'],
        ]);

        $station->update($validated);

        return response()->json($station);
    }

    public function addTransaction(Request $request, FuelStation $station): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'in:in,out'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'waybill_id' => ['nullable', 'exists:waybills,id'],
            'vehicle_id' => ['nullable', 'exists:vehicles,id'],
            'price_per_liter' => ['nullable', 'numeric'],
            'notes' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($validated, $station, $request, &$transaction) {
            $transaction = FuelTransaction::create(array_merge($validated, [
                'fuel_station_id' => $station->id,
                'fuel_type' => $station->fuel_type,
                'created_by' => $request->user()->id,
            ]));

            $newBalance = $validated['type'] === 'in'
                ? $station->current_balance + $validated['amount']
                : $station->current_balance - $validated['amount'];

            $station->update(['current_balance' => max(0, $newBalance)]);
        });

        return response()->json($transaction->load(['fuelStation', 'vehicle', 'waybill']), 201);
    }

    public function transactions(Request $request): JsonResponse
    {
        $user = $request->user();

        $transactions = FuelTransaction::with([
            'fuelStation.organization:id,name,short_name',
            'vehicle:id,state_number',
            'waybill:id,waybill_number',
            'createdBy:id,name',
        ])
            ->when(! $user->isHqDispatcher(), function ($q) use ($user) {
                $q->whereHas('fuelStation', fn ($sq) => $sq->where('organization_id', $user->organization_id));
            })
            ->when($request->date_from, fn ($q) => $q->whereDate('created_at', '>=', $request->date_from))
            ->when($request->date_to, fn ($q) => $q->whereDate('created_at', '<=', $request->date_to))
            ->when($request->type, fn ($q) => $q->where('type', $request->type))
            ->latest()
            ->paginate($request->per_page ?? 20);

        return response()->json($transactions);
    }
}
