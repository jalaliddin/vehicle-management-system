<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Organization::all());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'short_name' => ['nullable', 'string', 'max:50'],
            'code' => ['required', 'string', 'unique:organizations,code'],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:20'],
            'director' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ]);

        $organization = Organization::create($validated);

        return response()->json($organization, 201);
    }

    public function show(Organization $organization): JsonResponse
    {
        return response()->json($organization->load(['vehicles', 'drivers', 'fuelStations', 'users']));
    }

    public function update(Request $request, Organization $organization): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['string', 'max:255'],
            'short_name' => ['nullable', 'string', 'max:50'],
            'code' => ['string', 'unique:organizations,code,'.$organization->id],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:20'],
            'director' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ]);

        $organization->update($validated);

        return response()->json($organization);
    }

    public function destroy(Organization $organization): JsonResponse
    {
        $organization->delete();

        return response()->json(null, 204);
    }
}
