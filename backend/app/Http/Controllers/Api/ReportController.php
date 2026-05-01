<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\FuelTransaction;
use App\Models\Vehicle;
use App\Models\Waybill;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function waybills(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = Waybill::with(['organization:id,name,short_name', 'vehicle:id,state_number,model', 'driver:id,full_name'])
            ->when(! $user->isHqDispatcher(), fn ($q) => $q->where('organization_id', $user->organization_id))
            ->when($request->org_id, fn ($q) => $q->where('organization_id', $request->org_id))
            ->when($request->from, fn ($q) => $q->whereDate('created_date', '>=', $request->from))
            ->when($request->to, fn ($q) => $q->whereDate('created_date', '<=', $request->to))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->get();

        return response()->json([
            'total' => $data->count(),
            'completed' => $data->where('status', 'completed')->count(),
            'cancelled' => $data->where('status', 'cancelled')->count(),
            'total_km' => $data->sum('actual_km'),
            'total_fuel' => $data->sum('fuel_consumed'),
            'items' => $data,
        ]);
    }

    public function fuel(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = FuelTransaction::with([
            'fuelStation.organization:id,name,short_name',
            'vehicle:id,state_number',
        ])
            ->when(! $user->isHqDispatcher(), function ($q) use ($user) {
                $q->whereHas('fuelStation', fn ($sq) => $sq->where('organization_id', $user->organization_id));
            })
            ->when($request->from, fn ($q) => $q->whereDate('created_at', '>=', $request->from))
            ->when($request->to, fn ($q) => $q->whereDate('created_at', '<=', $request->to))
            ->get();

        return response()->json([
            'total_in' => $data->where('type', 'in')->sum('amount'),
            'total_out' => $data->where('type', 'out')->sum('amount'),
            'items' => $data,
        ]);
    }

    public function vehicles(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = Vehicle::with('organization:id,name,short_name')
            ->when(! $user->isHqDispatcher(), fn ($q) => $q->where('organization_id', $user->organization_id))
            ->when($request->org_id, fn ($q) => $q->where('organization_id', $request->org_id))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->get();

        return response()->json([
            'total' => $data->count(),
            'active' => $data->where('status', 'active')->count(),
            'broken' => $data->where('status', 'broken')->count(),
            'maintenance' => $data->where('status', 'maintenance')->count(),
            'items' => $data,
        ]);
    }

    public function drivers(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = Driver::with(['organization:id,name,short_name', 'vehicle:id,state_number'])
            ->when(! $user->isHqDispatcher(), fn ($q) => $q->where('organization_id', $user->organization_id))
            ->when($request->org_id, fn ($q) => $q->where('organization_id', $request->org_id))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->get();

        return response()->json([
            'total' => $data->count(),
            'active' => $data->where('status', 'active')->count(),
            'items' => $data,
        ]);
    }

    public function fuelConsumption(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = Waybill::with(['vehicle:id,state_number,model,fuel_norm', 'organization:id,short_name'])
            ->when(! $user->isHqDispatcher(), fn ($q) => $q->where('organization_id', $user->organization_id))
            ->when($request->org_id, fn ($q) => $q->where('organization_id', $request->org_id))
            ->when($request->from, fn ($q) => $q->whereDate('created_date', '>=', $request->from))
            ->when($request->to, fn ($q) => $q->whereDate('created_date', '<=', $request->to))
            ->where('status', 'completed')
            ->whereNotNull('actual_km')
            ->whereNotNull('fuel_consumed')
            ->get()
            ->map(function (Waybill $w) {
                $normKm = $w->actual_km > 0 ? ($w->fuel_consumed / $w->actual_km) * 100 : 0;

                return [
                    'waybill_number' => $w->waybill_number,
                    'vehicle' => $w->vehicle,
                    'organization' => $w->organization,
                    'actual_km' => $w->actual_km,
                    'fuel_issued' => $w->fuel_issued,
                    'fuel_consumed' => $w->fuel_consumed,
                    'fuel_returned' => $w->fuel_returned,
                    'norm_per_100km' => round($normKm, 2),
                    'fuel_norm' => $w->vehicle?->fuel_norm,
                    'deviation' => $w->vehicle?->fuel_norm ? round($normKm - $w->vehicle->fuel_norm, 2) : null,
                ];
            });

        return response()->json([
            'total_km' => $data->sum('actual_km'),
            'total_fuel_consumed' => $data->sum('fuel_consumed'),
            'items' => $data,
        ]);
    }
}
