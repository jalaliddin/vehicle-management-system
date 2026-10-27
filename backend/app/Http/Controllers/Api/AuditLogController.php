<?php

namespace App\Http\Controllers\Api;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize(Permission::AuditView->value);

        $request->validate([
            'user_id' => ['nullable', 'integer'],
            'employee_id' => ['nullable', 'integer'],
            'event' => ['nullable', 'string', 'max:32'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $user = $request->user();

        $logs = AuditLog::with('user:id,name')
            ->when(! $user->canAccessAllOrganizations(), fn ($q) => $user->organization_id === null
                ? $q->whereRaw('1 = 0')
                : $q->where('organization_id', $user->organization_id))
            ->when($request->user_id, fn ($q) => $q->where('user_id', $request->user_id))
            ->when($request->employee_id, fn ($q) => $q->where('employee_id', $request->employee_id))
            ->when($request->event, fn ($q) => $q->where('event', $request->event))
            ->when($request->from, fn ($q) => $q->where('created_at', '>=', $request->date('from')->startOfDay()))
            ->when($request->to, fn ($q) => $q->where('created_at', '<=', $request->date('to')->endOfDay()))
            ->latest('id')
            ->paginate(50);

        return response()->json($logs);
    }
}
