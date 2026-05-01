<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DriverController;
use App\Http\Controllers\Api\FuelController;
use App\Http\Controllers\Api\FuelNormController;
use App\Http\Controllers\Api\OrganizationController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\VehicleController;
use App\Http\Controllers\Api\WaybillController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);

    Route::apiResource('organizations', OrganizationController::class);
    Route::apiResource('vehicles', VehicleController::class);
    Route::put('/vehicles/{vehicle}/status', [VehicleController::class, 'updateStatus']);
    Route::apiResource('drivers', DriverController::class);
    Route::post('/drivers/{driver}/assign', [DriverController::class, 'assignVehicle']);
    Route::apiResource('waybills', WaybillController::class);
    Route::apiResource('fuel-norms', FuelNormController::class);

    Route::prefix('waybills/{waybill}')->group(function () {
        Route::post('mechanic-check', [WaybillController::class, 'mechanicCheck']);
        Route::post('doctor-check', [WaybillController::class, 'doctorCheck']);
        Route::post('approve', [WaybillController::class, 'approve']);
        Route::post('depart', [WaybillController::class, 'depart']);
        Route::post('complete', [WaybillController::class, 'complete']);
        Route::post('route', [WaybillController::class, 'saveRoute']);
        Route::post('submit', [WaybillController::class, 'submitToMechanic']);
    });

    Route::prefix('fuel')->group(function () {
        Route::get('monitoring', [FuelController::class, 'monitoring']);
        Route::get('stations', [FuelController::class, 'stationIndex']);
        Route::post('stations', [FuelController::class, 'stationStore']);
        Route::put('stations/{station}', [FuelController::class, 'stationUpdate']);
        Route::post('stations/{station}/transaction', [FuelController::class, 'addTransaction']);
        Route::get('transactions', [FuelController::class, 'transactions']);
    });

    Route::prefix('reports')->group(function () {
        Route::get('waybills', [ReportController::class, 'waybills']);
        Route::get('fuel', [ReportController::class, 'fuel']);
        Route::get('vehicles', [ReportController::class, 'vehicles']);
        Route::get('drivers', [ReportController::class, 'drivers']);
        Route::get('fuel-consumption', [ReportController::class, 'fuelConsumption']);
    });
});
