<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FuelNorm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class FuelNormController extends Controller
{
    public function index(): JsonResponse
    {
        Gate::authorize('fuel_norms.view');

        return response()->json(FuelNorm::all());
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('fuel_norms.manage');

        $validated = $request->validate([
            'route_name' => ['required', 'string'],
            'route_number' => ['nullable', 'string'],
            'vehicle_model' => ['required', 'string'],
            'norm_without_ac' => ['required', 'numeric', 'min:0'],
            'norm_with_ac' => ['nullable', 'numeric', 'min:0'],
            'heating_norm' => ['nullable', 'numeric', 'min:0'],
            'fuel_type' => ['required', 'string'],
        ]);

        return response()->json(FuelNorm::create($validated), 201);
    }

    public function show(FuelNorm $fuelNorm): JsonResponse
    {
        Gate::authorize('fuel_norms.view');

        return response()->json($fuelNorm);
    }

    public function update(Request $request, FuelNorm $fuelNorm): JsonResponse
    {
        Gate::authorize('fuel_norms.manage');

        $validated = $request->validate([
            'route_name' => ['string'],
            'route_number' => ['nullable', 'string'],
            'vehicle_model' => ['string'],
            'norm_without_ac' => ['numeric', 'min:0'],
            'norm_with_ac' => ['nullable', 'numeric', 'min:0'],
            'heating_norm' => ['nullable', 'numeric', 'min:0'],
            'fuel_type' => ['string'],
        ]);

        $fuelNorm->update($validated);

        return response()->json($fuelNorm);
    }

    public function destroy(FuelNorm $fuelNorm): JsonResponse
    {
        Gate::authorize('fuel_norms.manage');

        $fuelNorm->delete();

        return response()->json(null, 204);
    }
}
