<?php

namespace App\Http\Controllers\Api\Performance\Concerns;

use App\Models\PerformanceCycle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait HandlesPerformanceCycles
{
    public function cycles(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'performance.view')) {
            return $forbidden;
        }
        $rows = PerformanceCycle::query()->orderByDesc('id')->get()->map(fn (PerformanceCycle $c) => [
            'id' => $c->id,
            'name' => $c->name,
            'periodStart' => $c->period_start->toDateString(),
            'periodEnd' => $c->period_end->toDateString(),
            'status' => $c->status,
        ])->values();

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function storeCycle(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'performance.manage')) {
            return $forbidden;
        }
        $v = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'periodStart' => ['required', 'date'],
            'periodEnd' => ['required', 'date', 'after_or_equal:periodStart'],
        ]);
        $c = PerformanceCycle::query()->create([
            'name' => trim((string) $v['name']),
            'period_start' => $v['periodStart'],
            'period_end' => $v['periodEnd'],
            'status' => 'draft',
        ]);

        return response()->json(['success' => true, 'data' => ['id' => $c->id]], 201);
    }

    public function updateCycle(Request $request, int $id): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'performance.manage')) {
            return $forbidden;
        }
        $c = PerformanceCycle::query()->findOrFail($id);
        $v = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'periodStart' => ['required', 'date'],
            'periodEnd' => ['required', 'date', 'after_or_equal:periodStart'],
            'status' => ['required', 'in:draft,active,closed'],
        ]);
        $c->update([
            'name' => trim((string) $v['name']),
            'period_start' => $v['periodStart'],
            'period_end' => $v['periodEnd'],
            'status' => $v['status'],
        ]);

        return response()->json(['success' => true]);
    }

    public function activateCycle(Request $request, int $id): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'performance.manage')) {
            return $forbidden;
        }
        PerformanceCycle::query()->where('status', 'active')->update(['status' => 'closed']);
        $c = PerformanceCycle::query()->findOrFail($id);
        $c->update(['status' => 'active']);

        return response()->json(['success' => true]);
    }

    public function closeCycle(Request $request, int $id): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'performance.manage')) {
            return $forbidden;
        }
        $c = PerformanceCycle::query()->findOrFail($id);
        $c->update(['status' => 'closed']);

        return response()->json(['success' => true]);
    }

    // -------------------------
    // Reviews
    // -------------------------
}
