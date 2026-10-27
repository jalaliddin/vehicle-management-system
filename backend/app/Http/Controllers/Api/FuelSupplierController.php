<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FuelSupplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class FuelSupplierController extends Controller
{
    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', FuelSupplier::class);

        return response()->json(FuelSupplier::orderBy('name')->get());
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', FuelSupplier::class);

        return response()->json(FuelSupplier::create($this->validated($request, true)), 201);
    }

    public function update(Request $request, FuelSupplier $supplier): JsonResponse
    {
        Gate::authorize('update', $supplier);

        $supplier->update($this->validated($request, false));

        return response()->json($supplier);
    }

    public function destroy(FuelSupplier $supplier): JsonResponse
    {
        Gate::authorize('delete', $supplier);

        if ($supplier->transactions()->exists()) {
            return response()->json(['message' => "Ma'lumot ishlatilmoqda, o'chirib bo'lmaydi"], 422);
        }

        $supplier->delete();

        return response()->json(null, 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $creating): array
    {
        return $request->validate([
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'tin' => ['nullable', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'max:20'],
            'is_active' => ['boolean'],
        ]);
    }
}
