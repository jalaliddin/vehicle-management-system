<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\FuelStation;
use App\Models\Organization;
use App\Models\Vehicle;
use App\Models\Waybill;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        $orgId = $user->isHqDispatcher() ? null : $user->organization_id;

        $vehicleQuery = Vehicle::query();
        $driverQuery = Driver::query();
        $waybillQuery = Waybill::query();

        if ($orgId) {
            $vehicleQuery->where('organization_id', $orgId);
            $driverQuery->where('organization_id', $orgId);
            $waybillQuery->where('organization_id', $orgId);
        }

        $totalVehicles = (clone $vehicleQuery)->count();
        $activeVehicles = (clone $vehicleQuery)->where('status', 'active')->count();
        $brokenVehicles = (clone $vehicleQuery)->where('status', 'broken')->count();
        $maintenanceVehicles = (clone $vehicleQuery)->where('status', 'maintenance')->count();

        $totalDrivers = (clone $driverQuery)->count();
        $activeDrivers = (clone $driverQuery)->where('status', 'active')->count();

        $todayWaybills = (clone $waybillQuery)->whereDate('created_date', today())->count();
        $pendingWaybills = (clone $waybillQuery)->whereIn('status', ['mechanic_check', 'doctor_check', 'hq_review'])->count();
        $activeWaybills = (clone $waybillQuery)->whereIn('status', ['approved', 'in_progress'])->count();

        $vehiclesByOrg = Organization::withCount([
            'vehicles',
            'vehicles as active_vehicles_count' => fn ($q) => $q->where('status', 'active'),
            'vehicles as broken_vehicles_count' => fn ($q) => $q->where('status', 'broken'),
        ])->when($orgId, fn ($q) => $q->where('id', $orgId))->get(['id', 'name', 'short_name']);

        $fuelStations = FuelStation::with('organization:id,name,short_name')
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->get();

        $fuelAlerts = $fuelStations->filter(fn ($s) => $s->current_balance <= $s->min_threshold);

        $recentWaybills = (clone $waybillQuery)
            ->with(['vehicle:id,state_number,model', 'driver:id,full_name', 'organization:id,short_name'])
            ->latest()
            ->take(10)
            ->get();

        $activeWaybillsList = (clone $waybillQuery)
            ->with(['vehicle:id,state_number,model', 'driver:id,full_name'])
            ->whereIn('status', ['in_progress'])
            ->get();

        return response()->json([
            'total_vehicles' => $totalVehicles,
            'active_vehicles' => $activeVehicles,
            'broken_vehicles' => $brokenVehicles,
            'maintenance_vehicles' => $maintenanceVehicles,
            'total_drivers' => $totalDrivers,
            'active_drivers' => $activeDrivers,
            'today_waybills' => $todayWaybills,
            'pending_waybills' => $pendingWaybills,
            'active_waybills' => $activeWaybills,
            'vehicles_by_org' => $vehiclesByOrg,
            'fuel_stations' => $fuelStations,
            'fuel_alerts' => $fuelAlerts->values(),
            'recent_waybills' => $recentWaybills,
            'active_waybills_list' => $activeWaybillsList,
        ]);
    }
}
