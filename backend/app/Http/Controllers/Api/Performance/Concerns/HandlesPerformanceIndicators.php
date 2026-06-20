<?php

namespace App\Http\Controllers\Api\Performance\Concerns;

use App\Http\Controllers\Controller;
use App\Models\CompanyUser;
use App\Models\EmployeeProfile;
use App\Models\LeaveRequest;
use App\Models\PerformanceCycle;
use App\Models\PerformanceIndicatorItem;
use App\Models\PerformanceIndicatorTemplate;
use App\Models\PerformanceGoal;
use App\Models\PerformanceGoalType;
use App\Models\PerformanceReview;
use App\Models\PerformanceReviewScore;
use App\Models\User;
use App\Notifications\PerformanceReviewCreatedNotification;
use App\Notifications\PerformanceReviewSubmittedNotification;
use App\Notifications\PerformanceReviewManagerReviewedNotification;
use App\Notifications\PerformanceReviewFinalizedNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

trait HandlesPerformanceIndicators
{    public function indicatorTemplates(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'performance.view')) {
            return $forbidden;
        }

        $rows = PerformanceIndicatorTemplate::query()
            ->orderByDesc('id')
            ->get()
            ->map(fn (PerformanceIndicatorTemplate $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'department' => $t->department ?? '',
                'designation' => $t->designation ?? '',
                'isActive' => (bool) $t->is_active,
            ])->values();

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function storeIndicatorTemplate(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'performance.manage')) {
            return $forbidden;
        }

        $v = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'department' => ['nullable', 'string', 'max:120'],
            'designation' => ['nullable', 'string', 'max:150'],
            'isActive' => ['nullable', 'boolean'],
        ]);

        $t = PerformanceIndicatorTemplate::query()->create([
            'name' => trim((string) $v['name']),
            'department' => isset($v['department']) ? trim((string) $v['department']) : null,
            'designation' => isset($v['designation']) ? trim((string) $v['designation']) : null,
            'is_active' => (bool) ($v['isActive'] ?? true),
        ]);

        return response()->json(['success' => true, 'data' => ['id' => $t->id]], 201);
    }

    public function updateIndicatorTemplate(Request $request, int $id): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'performance.manage')) {
            return $forbidden;
        }

        $t = PerformanceIndicatorTemplate::query()->findOrFail($id);
        $v = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'department' => ['nullable', 'string', 'max:120'],
            'designation' => ['nullable', 'string', 'max:150'],
            'isActive' => ['nullable', 'boolean'],
        ]);

        $t->update([
            'name' => trim((string) $v['name']),
            'department' => isset($v['department']) ? trim((string) $v['department']) : null,
            'designation' => isset($v['designation']) ? trim((string) $v['designation']) : null,
            'is_active' => (bool) ($v['isActive'] ?? true),
        ]);

        return response()->json(['success' => true]);
    }

    public function destroyIndicatorTemplate(Request $request, int $id): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'performance.manage')) {
            return $forbidden;
        }

        PerformanceIndicatorTemplate::query()->whereKey($id)->delete();
        return response()->json(['success' => true]);
    }

    public function indicatorItems(Request $request, int $id): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'performance.view')) {
            return $forbidden;
        }

        $t = PerformanceIndicatorTemplate::query()->findOrFail($id);
        $items = $t->items()->orderBy('section')->orderBy('sort_order')->orderBy('id')->get()->map(fn (PerformanceIndicatorItem $i) => [
            'id' => $i->id,
            'section' => $i->section,
            'title' => $i->title,
            'description' => $i->description ?? '',
            'weight' => $i->weight !== null ? (float) $i->weight : null,
            'ratingScaleMin' => (int) $i->rating_scale_min,
            'ratingScaleMax' => (int) $i->rating_scale_max,
            'sortOrder' => (int) $i->sort_order,
        ])->values();

        return response()->json(['success' => true, 'data' => $items]);
    }

    public function storeIndicatorItem(Request $request, int $id): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'performance.manage')) {
            return $forbidden;
        }

        PerformanceIndicatorTemplate::query()->findOrFail($id);
        $v = $request->validate([
            'section' => ['required', 'in:kpi,behavioral'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'weight' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'ratingScaleMin' => ['nullable', 'integer', 'min:1', 'max:10'],
            'ratingScaleMax' => ['nullable', 'integer', 'min:1', 'max:10'],
            'sortOrder' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);

        $section = (string) $v['section'];
        $item = PerformanceIndicatorItem::query()->create([
            'template_id' => $id,
            'section' => $section,
            'title' => trim((string) $v['title']),
            'description' => isset($v['description']) ? trim((string) $v['description']) : null,
            'weight' => $section === 'kpi' ? (float) ($v['weight'] ?? 0) : null,
            'rating_scale_min' => (int) ($v['ratingScaleMin'] ?? 1),
            'rating_scale_max' => (int) ($v['ratingScaleMax'] ?? 5),
            'sort_order' => (int) ($v['sortOrder'] ?? 0),
        ]);

        return response()->json(['success' => true, 'data' => ['id' => $item->id]], 201);
    }

    public function updateIndicatorItem(Request $request, int $itemId): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'performance.manage')) {
            return $forbidden;
        }

        $item = PerformanceIndicatorItem::query()->findOrFail($itemId);
        $v = $request->validate([
            'section' => ['required', 'in:kpi,behavioral'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'weight' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'ratingScaleMin' => ['nullable', 'integer', 'min:1', 'max:10'],
            'ratingScaleMax' => ['nullable', 'integer', 'min:1', 'max:10'],
            'sortOrder' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);

        $section = (string) $v['section'];
        $item->update([
            'section' => $section,
            'title' => trim((string) $v['title']),
            'description' => isset($v['description']) ? trim((string) $v['description']) : null,
            'weight' => $section === 'kpi' ? (float) ($v['weight'] ?? 0) : null,
            'rating_scale_min' => (int) ($v['ratingScaleMin'] ?? 1),
            'rating_scale_max' => (int) ($v['ratingScaleMax'] ?? 5),
            'sort_order' => (int) ($v['sortOrder'] ?? 0),
        ]);

        return response()->json(['success' => true]);
    }

    public function destroyIndicatorItem(Request $request, int $itemId): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'performance.manage')) {
            return $forbidden;
        }
        PerformanceIndicatorItem::query()->whereKey($itemId)->delete();
        return response()->json(['success' => true]);
    }

    // -------------------------
    // Cycles (admin)
    // -------------------------
}
