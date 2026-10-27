<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PpeItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PpeItemController extends Controller
{
    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', PpeItem::class);

        return response()->json(PpeItem::orderBy('name')->get());
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', PpeItem::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'kind' => ['required', 'in:clothing,footwear,ppe'],
            'unit' => ['nullable', 'string', 'max:20'],
            'default_wear_months' => ['nullable', 'integer', 'min:1', 'max:240'],
            'is_active' => ['boolean'],
        ]);

        return response()->json(PpeItem::create(array_filter($validated, fn ($value) => $value !== null)), 201);
    }

    public function show(PpeItem $ppeItem): JsonResponse
    {
        Gate::authorize('view', $ppeItem);

        return response()->json($ppeItem);
    }

    public function update(Request $request, PpeItem $ppeItem): JsonResponse
    {
        Gate::authorize('update', $ppeItem);

        $validated = $request->validate([
            'name' => ['string', 'max:255'],
            'kind' => ['in:clothing,footwear,ppe'],
            'unit' => ['string', 'max:20'],
            'default_wear_months' => ['nullable', 'integer', 'min:1', 'max:240'],
            'is_active' => ['boolean'],
        ]);

        $ppeItem->update($validated);

        return response()->json($ppeItem);
    }

    public function destroy(PpeItem $ppeItem): JsonResponse
    {
        Gate::authorize('delete', $ppeItem);

        if ($ppeItem->norms()->exists() || $ppeItem->issues()->exists()) {
            return response()->json(['message' => "Ma'lumot ishlatilmoqda, o'chirib bo'lmaydi"], 422);
        }

        $ppeItem->delete();

        return response()->json(null, 204);
    }
}
