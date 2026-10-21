<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmploymentEvent;
use App\Models\Position;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class PositionController extends Controller
{
    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', Position::class);

        return response()->json(Position::orderBy('name')->get());
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Position::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:positions,name'],
            'medical_exam_months' => ['nullable', 'integer', 'min:1', 'max:120'],
            'briefing_months' => ['nullable', 'integer', 'min:1', 'max:120'],
            'is_active' => ['boolean'],
        ]);

        return response()->json(Position::create($validated), 201);
    }

    public function show(Position $position): JsonResponse
    {
        Gate::authorize('view', $position);

        return response()->json($position->load('ppeNorms.ppeItem'));
    }

    public function update(Request $request, Position $position): JsonResponse
    {
        Gate::authorize('update', $position);

        $validated = $request->validate([
            'name' => ['string', 'max:255', 'unique:positions,name,'.$position->id],
            'medical_exam_months' => ['nullable', 'integer', 'min:1', 'max:120'],
            'briefing_months' => ['nullable', 'integer', 'min:1', 'max:120'],
            'is_active' => ['boolean'],
        ]);

        $position->update($validated);

        return response()->json($position);
    }

    public function destroy(Position $position): JsonResponse
    {
        Gate::authorize('delete', $position);

        if ($position->employees()->withTrashed()->exists() || EmploymentEvent::where('position_id', $position->id)->exists()) {
            return response()->json(['message' => "Ma'lumot ishlatilmoqda, o'chirib bo'lmaydi"], 422);
        }

        $position->delete();

        return response()->json(null, 204);
    }

    public function ppeNorms(Position $position): JsonResponse
    {
        Gate::authorize('view', $position);

        return response()->json($position->ppeNorms()->with('ppeItem')->get());
    }

    /**
     * Replace the position's PPE norms with the given set.
     */
    public function updatePpeNorms(Request $request, Position $position): JsonResponse
    {
        Gate::authorize('update', $position);

        $validated = $request->validate([
            'norms' => ['present', 'array'],
            'norms.*.ppe_item_id' => ['required', 'distinct', 'exists:ppe_items,id'],
            'norms.*.quantity' => ['required', 'integer', 'min:1', 'max:1000'],
            'norms.*.wear_months' => ['required', 'integer', 'min:1', 'max:240'],
        ]);

        DB::transaction(function () use ($position, $validated) {
            $itemIds = array_column($validated['norms'], 'ppe_item_id');

            $position->ppeNorms()->whereNotIn('ppe_item_id', $itemIds)->get()->each->delete();

            foreach ($validated['norms'] as $norm) {
                $position->ppeNorms()->updateOrCreate(
                    ['ppe_item_id' => $norm['ppe_item_id']],
                    ['quantity' => $norm['quantity'], 'wear_months' => $norm['wear_months']],
                );
            }
        });

        return response()->json($position->ppeNorms()->with('ppeItem')->get());
    }
}
