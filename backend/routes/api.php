<?php

use App\Http\Controllers\Api\AbsenceController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\DriverController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\EmployeePpeController;
use App\Http\Controllers\Api\EmploymentEventController;
use App\Http\Controllers\Api\FuelController;
use App\Http\Controllers\Api\FuelNormController;
use App\Http\Controllers\Api\MedicalExamController;
use App\Http\Controllers\Api\OrganizationController;
use App\Http\Controllers\Api\PositionController;
use App\Http\Controllers\Api\PpeItemController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\SafetyBriefingController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\VehicleController;
use App\Http\Controllers\Api\WaybillController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [AuthController::class, 'login']);

Route::get('/waybills/{waybill}/public', [WaybillController::class, 'publicShow']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);

    Route::apiResource('users', UserController::class);
    Route::post('/users/{user}/toggle-active', [UserController::class, 'toggleActive']);

    Route::get('/permissions', [RoleController::class, 'permissions']);
    Route::apiResource('roles', RoleController::class)->except('show');

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

    Route::get('/audit-logs', [AuditLogController::class, 'index']);

    Route::apiResource('departments', DepartmentController::class);
    Route::apiResource('positions', PositionController::class);
    Route::get('positions/{position}/ppe-norms', [PositionController::class, 'ppeNorms']);
    Route::put('positions/{position}/ppe-norms', [PositionController::class, 'updatePpeNorms']);
    Route::apiResource('ppe-items', PpeItemController::class);

    Route::apiResource('employees', EmployeeController::class);
    Route::prefix('employees/{employee}')->group(function () {
        Route::get('photo', [EmployeeController::class, 'photo']);
        Route::post('photo', [EmployeeController::class, 'uploadPhoto']);
        Route::get('history', [EmployeeController::class, 'history']);
        Route::get('events', [EmploymentEventController::class, 'index']);
        Route::post('events', [EmploymentEventController::class, 'store']);
        Route::get('absences', [AbsenceController::class, 'index']);
        Route::post('absences', [AbsenceController::class, 'store']);
        Route::get('leave-balance', [AbsenceController::class, 'leaveBalance']);
        Route::get('medical-exams', [MedicalExamController::class, 'index']);
        Route::post('medical-exams', [MedicalExamController::class, 'store']);
        Route::get('ppe', [EmployeePpeController::class, 'show']);
        Route::post('ppe-issues', [EmployeePpeController::class, 'store']);
    });
    Route::post('employment-events/{event}/cancel', [EmploymentEventController::class, 'cancel']);
    Route::put('absences/{absence}', [AbsenceController::class, 'update']);
    Route::delete('absences/{absence}', [AbsenceController::class, 'destroy']);
    Route::get('medical-exams', [MedicalExamController::class, 'organizationIndex']);
    Route::put('medical-exams/{exam}', [MedicalExamController::class, 'update']);
    Route::delete('medical-exams/{exam}', [MedicalExamController::class, 'destroy']);
    Route::get('medical-exams/{exam}/document', [MedicalExamController::class, 'document']);
    Route::apiResource('safety-briefings', SafetyBriefingController::class);
    Route::post('ppe-issues/{issue}/close', [EmployeePpeController::class, 'close']);
});
