<?php

namespace App\Http\Controllers\Api;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\FuelStation;
use App\Models\FuelTransaction;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class FuelController extends Controller
{
    public function monitoring(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', FuelStation::class);

        $user = $request->user();

        $stations = FuelStation::with('organization:id,name,short_name')
            ->accessibleBy($user)
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
        Gate::authorize('viewAny', FuelStation::class);

        $user = $request->user();

        $stations = FuelStation::with(['organization:id,name,short_name', 'fuelers:id,name'])
            ->accessibleBy($user)
            ->get()
            ->map(fn (FuelStation $s) => array_merge($s->toArray(), ['percentage' => $s->percentage, 'unit' => $s->unit()]));

        return response()->json($stations);
    }

    public function stationStore(Request $request): JsonResponse
    {
        Gate::authorize('create', FuelStation::class);

        $validated = $request->validate([
            'organization_id' => ['required', 'exists:organizations,id'],
            'name' => ['required', 'string'],
            'fuel_type' => ['required', 'string'],
            'capacity' => ['required', 'numeric', 'min:1'],
            'current_balance' => ['numeric', 'min:0'],
            'min_threshold' => ['numeric', 'min:0'],
        ]);

        Gate::authorize('access-organization', [(int) $validated['organization_id']]);

        $station = FuelStation::create($validated);

        return response()->json($station->load('organization'), 201);
    }

    public function stationUpdate(Request $request, FuelStation $station): JsonResponse
    {
        Gate::authorize('update', $station);

        $validated = $request->validate([
            'name' => ['string'],
            'fuel_type' => ['string'],
            'capacity' => ['numeric', 'min:1'],
            'min_threshold' => ['numeric', 'min:0'],
        ]);

        $station->update($validated);

        return response()->json($station);
    }

    public function transactions(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', FuelStation::class);

        $user = $request->user();

        $transactions = FuelTransaction::with([
            'fuelStation.organization:id,name,short_name',
            'vehicle:id,state_number',
            'waybill:id,waybill_number',
            'createdBy:id,name',
            'driver:id,full_name',
            'supplier:id,name',
            'cancelledBy:id,name',
        ])
            ->whereIn('fuel_station_id', FuelStation::accessibleBy($user)->select('id'))
            ->when($request->date_from, fn ($q) => $q->whereDate('created_at', '>=', $request->date_from))
            ->when($request->date_to, fn ($q) => $q->whereDate('created_at', '<=', $request->date_to))
            ->when($request->type, fn ($q) => $q->where('type', $request->type))
            ->when($request->kind, fn ($q) => $q->where('kind', $request->kind))
            ->when($request->station_id, fn ($q) => $q->where('fuel_station_id', $request->station_id))
            ->when($request->vehicle_id, fn ($q) => $q->where('vehicle_id', $request->vehicle_id))
            ->latest()
            ->paginate($request->per_page ?? 20);

        return response()->json($transactions);
    }

    /**
     * Replace the fuelers assigned to the station.
     */
    public function assignFuelers(Request $request, FuelStation $station): JsonResponse
    {
        Gate::authorize('update', $station);

        $validated = $request->validate([
            'user_ids' => ['present', 'array'],
            'user_ids.*' => ['integer', 'distinct'],
        ]);

        $users = User::with('role.permissions')->whereKey($validated['user_ids'])->get();
        $eligible = $users->filter(fn (User $user) => $user->organization_id === $station->organization_id
            && ($user->isSuperAdmin() || $user->hasPermission(Permission::FuelTransactionsCreate)));

        if ($eligible->count() !== count($validated['user_ids'])) {
            throw ValidationException::withMessages([
                'user_ids' => "Quyuvchi shu tashkilot xodimi va yoqilg'i harakati huquqiga ega bo'lishi kerak",
            ]);
        }

        $station->fuelers()->sync($eligible->modelKeys());

        return response()->json($station->load('fuelers:id,name'));
    }
}
