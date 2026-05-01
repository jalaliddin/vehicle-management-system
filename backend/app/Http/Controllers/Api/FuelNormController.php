<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FuelNorm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FuelNormController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(FuelNorm::all());
    }

    public function store(Request $request): JsonResponse
    {
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
        return response()->json($fuelNorm);
    }

    public function update(Request $request, FuelNorm $fuelNorm): JsonResponse
    {
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
        $fuelNorm->delete();

        return response()->json(null, 204);
    }
}
