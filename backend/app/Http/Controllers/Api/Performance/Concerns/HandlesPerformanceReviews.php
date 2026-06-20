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
use App\Modelsser;
use App\Notifications\PerformanceReviewCreatedNotification;
use App\Notifications\PerformanceReviewSubmittedNotification;
use App\Notifications\PerformanceReviewManagerReviewedNotification;
use App\Notifications\PerformanceReviewFinalizedNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

trait HandlesPerformanceReviews
{    private function userBelongsToActiveCompany(int $userId, ?int $companyId): bool
    {
        if (! $companyId) {
            return true;
        }

        return DB::table('company_users')
            ->where('user_id', $userId)
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->exists();
    }

    private function resolveReviewCreationIdentifiersOrFail(Request $request, array $validated): array
    {
        $cycleId = $this->resolveNumericOrUuidModelId(PerformanceCycle::class, $validated['cycleId'] ?? null);
        $templateId = $this->resolveNumericOrUuidModelId(PerformanceIndicatorTemplate::class, $validated['templateId'] ?? null);
        $userId = $this->resolveNumericOrUuidModelId(User::class, $validated['userId'] ?? null);

        $errors = [];
        if (! $cycleId) {
            $errors['cycleId'] = ['The selected cycle id is invalid.'];
        }
        if (! $templateId) {
            $errors['templateId'] = ['The selected template id is invalid.'];
        }
        if (! $userId) {
            $errors['userId'] = ['The selected user id is invalid.'];
        } elseif (! $this->userBelongsToActiveCompany($userId, $this->activeCompanyId($request))) {
            $errors['userId'] = ['The selected user id is invalid for the active company.'];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'cycleId' => $cycleId,
            'templateId' => $templateId,
            'userId' => $userId,
        ];
    }

    // -------------------------
    // Goals & Goal Types (Phase 1)
    // -------------------------
    public function reviews(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'scope' => ['nullable', 'in:me,team,all'],
            'cycleId' => ['nullable', 'integer'],
            'status' => ['nullable', 'in:draft,submitted,manager_reviewed,finalized'],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $user = $request->user();
        $scope = (string) ($validated['scope'] ?? 'me');
        $isAdmin = $this->canManagePerformance($request);

        $query = PerformanceReview::query()
            ->with(['employee:id,name,email', 'manager:id,name,email', 'cycle:id,name,status'])
            ->orderByDesc('id');

        if ($scope === 'all') {
            if (! $isAdmin) {
                return $this->forbidden();
            }
        } elseif ($scope === 'team') {
            $query->where('manager_user_id', $user->id);
        } else {
            $query->where('user_id', $user->id);
        }

        if (! empty($validated['cycleId'])) {
            $query->where('cycle_id', (int) $validated['cycleId']);
        }
        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $rows = $query->paginate((int) ($validated['perPage'] ?? 20));
        $data = $rows->getCollection()->map(fn (PerformanceReview $r) => [
            'id' => $r->id,
            'cycle' => $r->cycle ? ['id' => $r->cycle->id, 'name' => $r->cycle->name, 'status' => $r->cycle->status] : null,
            'employee' => $r->employee ? ['id' => $r->employee->id, 'name' => $r->employee->name, 'email' => $r->employee->email] : null,
            'manager' => $r->manager ? ['id' => $r->manager->id, 'name' => $r->manager->name, 'email' => $r->manager->email] : null,
            'status' => $r->status,
            'selfTotalScore' => $r->self_total_score !== null ? (float) $r->self_total_score : null,
            'managerTotalScore' => $r->manager_total_score !== null ? (float) $r->manager_total_score : null,
            'finalTotalScore' => $r->final_total_score !== null ? (float) $r->final_total_score : null,
            'updatedAt' => $r->updated_at?->toIso8601String(),
        ])->values();

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'currentPage' => $rows->currentPage(),
                'lastPage' => $rows->lastPage(),
                'perPage' => $rows->perPage(),
                'total' => $rows->total(),
            ],
        ]);
    }

    public function createReview(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'performance.manage')) {
            return $forbidden;
        }

        $v = $request->validate([
            'cycleId' => ['required'],
            'userId' => ['required'],
            'templateId' => ['required'],
        ]);

        $resolved = $this->resolveReviewCreationIdentifiersOrFail($request, $v);

        $employee = User::query()->findOrFail($resolved['userId']);
        $profile = EmployeeProfile::query()->where('user_id', $employee->id)->first();
        $managerUserId = $profile?->manager_user_id;

        $review = PerformanceReview::query()->create([
            'company_id' => $this->activeCompanyId($request),
            'cycle_id' => $resolved['cycleId'],
            'user_id' => $employee->id,
            'manager_user_id' => $managerUserId,
            'template_id' => $resolved['templateId'],
            'status' => 'draft',
        ]);

        // Pre-create score rows for items.
        $items = PerformanceIndicatorItem::query()->where('template_id', $resolved['templateId'])->get(['id']);
        foreach ($items as $it) {
            PerformanceReviewScore::query()->create([
                'review_id' => $review->id,
                'item_id' => $it->id,
            ]);
        }

        // Notify company admin users about the new performance review
        $this->notifyCompanyAdminsPerformance($review->company_id, new PerformanceReviewCreatedNotification($review));

        return response()->json(['success' => true, 'data' => ['id' => $review->id]], 201);
    }

    public function showReview(Request $request, int $id): JsonResponse
    {
        $review = PerformanceReview::query()
            ->with([
                'employee:id,name,email',
                'manager:id,name,email',
                'cycle:id,name,status,period_start,period_end',
                'template:id,name,department,designation,is_active',
                'template.items',
                'scores',
            ])
            ->findOrFail($id);

        if (! $this->canAccessReview($request, $review)) {
            return $this->forbidden();
        }

        $auth = $request->user();
        $isOwner = $review->user_id === $auth->id;
        $isManager = $review->manager_user_id !== null && $review->manager_user_id === $auth->id;
        $isAdmin = $this->hasAnyPermission($request, ['performance.manage']);

        $items = $review->template?->items?->sortBy(['section', 'sort_order', 'id'])->values() ?? collect();
        $scoreByItem = $review->scores?->keyBy('item_id') ?? collect();

        $payloadItems = $items->map(function (PerformanceIndicatorItem $i) use ($scoreByItem) {
            $s = $scoreByItem->get($i->id);
            return [
                'id' => $i->id,
                'section' => $i->section,
                'title' => $i->title,
                'description' => $i->description ?? '',
                'weight' => $i->weight !== null ? (float) $i->weight : null,
                'ratingScaleMin' => (int) $i->rating_scale_min,
                'ratingScaleMax' => (int) $i->rating_scale_max,
                'sortOrder' => (int) $i->sort_order,
                'scores' => [
                    'selfScore' => $s?->self_score !== null ? (float) $s->self_score : null,
                    'managerScore' => $s?->manager_score !== null ? (float) $s->manager_score : null,
                    'finalScore' => $s?->final_score !== null ? (float) $s->final_score : null,
                    'selfComment' => $s?->self_comment ?? '',
                    'managerComment' => $s?->manager_comment ?? '',
                    'finalComment' => $s?->final_comment ?? '',
                ],
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $review->id,
                'status' => $review->status,
                'cycle' => $review->cycle ? [
                    'id' => $review->cycle->id,
                    'name' => $review->cycle->name,
                    'status' => $review->cycle->status,
                    'periodStart' => $review->cycle->period_start?->toDateString(),
                    'periodEnd' => $review->cycle->period_end?->toDateString(),
                ] : null,
                'employee' => $review->employee ? ['id' => $review->employee->id, 'name' => $review->employee->name, 'email' => $review->employee->email] : null,
                'manager' => $review->manager ? ['id' => $review->manager->id, 'name' => $review->manager->name, 'email' => $review->manager->email] : null,
                'template' => $review->template ? [
                    'id' => $review->template->id,
                    'name' => $review->template->name,
                    'department' => $review->template->department ?? '',
                    'designation' => $review->template->designation ?? '',
                ] : null,
                'notes' => [
                    'selfNote' => $review->self_note ?? '',
                    'managerNote' => $review->manager_note ?? '',
                    'finalNote' => $review->final_note ?? '',
                ],
                'totals' => [
                    'selfTotalScore' => $review->self_total_score !== null ? (float) $review->self_total_score : null,
                    'managerTotalScore' => $review->manager_total_score !== null ? (float) $review->manager_total_score : null,
                    'finalTotalScore' => $review->final_total_score !== null ? (float) $review->final_total_score : null,
                ],
                'leaveFrequency' => $this->calculateLeaveFrequencyMetrics($review),
                'items' => $payloadItems,
                'permissions' => [
                    'isOwner' => $isOwner,
                    'isManager' => $isManager,
                    'isAdmin' => $isAdmin,
                ],
            ],
        ]);
    }

    public function updateReviewSelf(Request $request, int $id): JsonResponse
    {
        $review = PerformanceReview::query()->with('template.items')->with('scores')->findOrFail($id);
        $user = $request->user();
        if ($review->user_id !== $user->id) {
            return $this->forbidden();
        }
        if ($review->status !== 'draft') {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'PERF_REVIEW_LOCKED', 'message' => 'Review is not editable.'],
            ], 422);
        }

        $v = $request->validate([
            'selfNote' => ['nullable', 'string', 'max:5000'],
            'scores' => ['required', 'array'],
            'scores.*.itemId' => ['required', 'integer'],
            'scores.*.score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'scores.*.comment' => ['nullable', 'string', 'max:3000'],
        ]);

        $items = $review->template?->items?->keyBy('id') ?? collect();
        foreach ($v['scores'] as $row) {
            $itemId = (int) $row['itemId'];
            /** @var PerformanceIndicatorItem|null $item */
            $item = $items->get($itemId);
            if (! $item) {
                continue;
            }
            $scoreVal = $row['score'] ?? null;
            if ($scoreVal !== null) {
                $scoreVal = (float) $scoreVal;
                if ($item->section === 'behavioral') {
                    $min = (int) $item->rating_scale_min;
                    $max = (int) $item->rating_scale_max;
                    if ($scoreVal < $min || $scoreVal > $max) {
                        return response()->json([
                            'success' => false,
                            'error' => ['code' => 'VALIDATION_ERROR', 'message' => 'Behavioral score out of range.'],
                        ], 422);
                    }
                }
            }
            PerformanceReviewScore::query()
                ->where('review_id', $review->id)
                ->where('item_id', $itemId)
                ->update([
                    'self_score' => $scoreVal,
                    'self_comment' => isset($row['comment']) ? trim((string) $row['comment']) : null,
                ]);
        }

        if (array_key_exists('selfNote', $v)) {
            $review->self_note = $v['selfNote'] !== null ? trim((string) $v['selfNote']) : null;
        }

        $review->refresh();
        $review->self_total_score = $this->computeHybridTotal($review, 'self');
        $review->save();

        return response()->json(['success' => true]);
    }

    public function submitReview(Request $request, int $id): JsonResponse
    {
        $review = PerformanceReview::query()->with('cycle')->findOrFail($id);
        $user = $request->user();
        if ($review->user_id !== $user->id) {
            return $this->forbidden();
        }
        if ($review->status !== 'draft') {
            return response()->json(['success' => false, 'error' => ['code' => 'PERF_REVIEW_LOCKED', 'message' => 'Review is not editable.']], 422);
        }
        if ($review->cycle && $review->cycle->status !== 'active') {
            return response()->json(['success' => false, 'error' => ['code' => 'PERF_CYCLE_NOT_ACTIVE', 'message' => 'Cycle is not active.']], 422);
        }
        $review->update(['status' => 'submitted']);

        // Notify company admin users about the submitted review
        $this->notifyCompanyAdminsPerformance($review->company_id, new PerformanceReviewSubmittedNotification($review));

        return response()->json(['success' => true]);
    }

    public function managerUpdate(Request $request, int $id): JsonResponse
    {
        $review = PerformanceReview::query()->with('template.items')->with('scores')->findOrFail($id);
        $user = $request->user();
        if ($review->manager_user_id === null || $review->manager_user_id !== $user->id) {
            return $this->forbidden();
        }
        if ($review->status !== 'submitted') {
            return response()->json(['success' => false, 'error' => ['code' => 'PERF_REVIEW_LOCKED', 'message' => 'Review is not in submitted status.']], 422);
        }

        $v = $request->validate([
            'managerNote' => ['nullable', 'string', 'max:5000'],
            'scores' => ['required', 'array'],
            'scores.*.itemId' => ['required', 'integer'],
            'scores.*.score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'scores.*.comment' => ['nullable', 'string', 'max:3000'],
        ]);

        $items = $review->template?->items?->keyBy('id') ?? collect();
        foreach ($v['scores'] as $row) {
            $itemId = (int) $row['itemId'];
            $item = $items->get($itemId);
            if (! $item) {
                continue;
            }
            $scoreVal = $row['score'] ?? null;
            if ($scoreVal !== null) {
                $scoreVal = (float) $scoreVal;
                if ($item->section === 'behavioral') {
                    $min = (int) $item->rating_scale_min;
                    $max = (int) $item->rating_scale_max;
                    if ($scoreVal < $min || $scoreVal > $max) {
                        return response()->json(['success' => false, 'error' => ['code' => 'VALIDATION_ERROR', 'message' => 'Behavioral score out of range.']], 422);
                    }
                }
            }
            PerformanceReviewScore::query()->where('review_id', $review->id)->where('item_id', $itemId)->update([
                'manager_score' => $scoreVal,
                'manager_comment' => isset($row['comment']) ? trim((string) $row['comment']) : null,
            ]);
        }

        if (array_key_exists('managerNote', $v)) {
            $review->manager_note = $v['managerNote'] !== null ? trim((string) $v['managerNote']) : null;
        }

        $review->refresh();
        $review->manager_total_score = $this->computeHybridTotal($review, 'manager');
        $review->save();

        return response()->json(['success' => true]);
    }

    public function managerComplete(Request $request, int $id): JsonResponse
    {
        $review = PerformanceReview::query()->findOrFail($id);
        $user = $request->user();
        if ($review->manager_user_id === null || $review->manager_user_id !== $user->id) {
            return $this->forbidden();
        }
        if ($review->status !== 'submitted') {
            return response()->json(['success' => false, 'error' => ['code' => 'PERF_REVIEW_LOCKED', 'message' => 'Review is not in submitted status.']], 422);
        }
        $review->update(['status' => 'manager_reviewed']);

        // Notify company admin users about the manager-reviewed review
        $this->notifyCompanyAdminsPerformance($review->company_id, new PerformanceReviewManagerReviewedNotification($review));

        return response()->json(['success' => true]);
    }

    public function adminFinalUpdate(Request $request, int $id): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'performance.manage')) {
            return $forbidden;
        }
        $review = PerformanceReview::query()->with('template.items')->findOrFail($id);
        if (! in_array($review->status, ['manager_reviewed', 'finalized'], true)) {
            return response()->json(['success' => false, 'error' => ['code' => 'PERF_REVIEW_LOCKED', 'message' => 'Review is not ready for final scoring.']], 422);
        }

        $v = $request->validate([
            'finalNote' => ['nullable', 'string', 'max:5000'],
            'scores' => ['required', 'array'],
            'scores.*.itemId' => ['required', 'integer'],
            'scores.*.score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'scores.*.comment' => ['nullable', 'string', 'max:3000'],
        ]);

        $items = $review->template?->items?->keyBy('id') ?? collect();
        foreach ($v['scores'] as $row) {
            $itemId = (int) $row['itemId'];
            $item = $items->get($itemId);
            if (! $item) {
                continue;
            }
            $scoreVal = $row['score'] ?? null;
            if ($scoreVal !== null) {
                $scoreVal = (float) $scoreVal;
                if ($item->section === 'behavioral') {
                    $min = (int) $item->rating_scale_min;
                    $max = (int) $item->rating_scale_max;
                    if ($scoreVal < $min || $scoreVal > $max) {
                        return response()->json(['success' => false, 'error' => ['code' => 'VALIDATION_ERROR', 'message' => 'Behavioral score out of range.']], 422);
                    }
                }
            }
            PerformanceReviewScore::query()->where('review_id', $review->id)->where('item_id', $itemId)->update([
                'final_score' => $scoreVal,
                'final_comment' => isset($row['comment']) ? trim((string) $row['comment']) : null,
            ]);
        }

        if (array_key_exists('finalNote', $v)) {
            $review->final_note = $v['finalNote'] !== null ? trim((string) $v['finalNote']) : null;
        }

        $review->refresh();
        $review->final_total_score = $this->computeHybridTotal($review, 'final');
        $review->save();

        return response()->json(['success' => true]);
    }

    public function finalize(Request $request, int $id): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'performance.manage')) {
            return $forbidden;
        }
        $review = PerformanceReview::query()->findOrFail($id);
        if ($review->status !== 'manager_reviewed') {
            return response()->json(['success' => false, 'error' => ['code' => 'PERF_REVIEW_LOCKED', 'message' => 'Review is not manager reviewed.']], 422);
        }
        $review->update(['status' => 'finalized']);

        // Fire notification to employee and manager
        if ($review->employee) {
            $review->employee->notify(new PerformanceReviewFinalizedNotification($review));
        }
        if ($review->manager) {
            $review->manager->notify(new PerformanceReviewFinalizedNotification($review));
        }

        return response()->json(['success' => true]);
    }

    // -------------------------
    // Helpers
    // -------------------------
    private function canManagePerformance(Request $request): bool
    {
        return $this->hasAnyPermission($request, ['performance.manage']);
    }

    private function canAccessReview(Request $request, PerformanceReview $review): bool
    {
        $user = $request->user();
        if ($this->hasAnyPermission($request, ['performance.manage'])) {
            return true;
        }
        $isOwner = $review->user_id === $user->id;
        $isManager = $review->manager_user_id !== null && $review->manager_user_id === $user->id;
        return $isOwner || $isManager;
    }

    private function computeHybridTotal(PerformanceReview $review, string $kind): float
    {
        $review->loadMissing(['template.items', 'scores']);
        $items = $review->template?->items ?? collect();
        $scores = $review->scores?->keyBy('item_id') ?? collect();

        $kpiSumWeight = 0.0;
        $kpiSum = 0.0;
        $behList = [];

        foreach ($items as $item) {
            $s = $scores->get($item->id);
            $val = null;
            if ($kind === 'self') $val = $s?->self_score;
            if ($kind === 'manager') $val = $s?->manager_score;
            if ($kind === 'final') $val = $s?->final_score;
            if ($val === null) {
                continue;
            }
            $v = (float) $val;
            if ($item->section === 'kpi') {
                $w = (float) ($item->weight ?? 0);
                if ($w > 0) {
                    $kpiSumWeight += $w;
                    $kpiSum += $v * $w;
                }
            } else {
                // Behavioral uses rating scale; convert to 0..100.
                $min = (float) $item->rating_scale_min;
                $max = (float) $item->rating_scale_max;
                $den = max(1.0, $max - $min);
                $behList[] = (($v - $min) / $den) * 100.0;
            }
        }

        $kpiAvg = $kpiSumWeight > 0 ? ($kpiSum / $kpiSumWeight) : 0.0;
        $behAvg = $behList !== [] ? (array_sum($behList) / count($behList)) : 0.0;

        return round(($kpiAvg * self::KPI_WEIGHT_GLOBAL) + ($behAvg * self::BEHAVIOR_WEIGHT_GLOBAL), 2);
    }

    /**
     * Calculate leave frequency metrics for a performance review period.
     * Provides absenteeism score and breakdown of approved leaves.
     */
    private function calculateLeaveFrequencyMetrics(PerformanceReview $review): ?array
    {
        if (!$review->cycle || !$review->cycle->period_start || !$review->cycle->period_end) {
            return null;
        }

        // Count approved leave days in the review cycle
        $approvedLeaves = LeaveRequest::query()
            ->when($review->company_id !== null, function ($query) use ($review) {
                $query->where('company_id', $review->company_id);
            }, function ($query) {
                $query->whereNull('company_id');
            })
            ->where('user_id', $review->user_id)
            ->where('status', 'approved')
            ->whereDate('date_from', '<=', $review->cycle->period_end)
            ->whereDate('date_to', '>=', $review->cycle->period_start)
            ->get(['id', 'date_from', 'date_to', 'leave_type', 'days']);

        $totalApproveDays = (float) $approvedLeaves->sum('days');

        // Calculate cycle period length (in days)
        $startDate = $review->cycle->period_start;
        $endDate = $review->cycle->period_end;
        $periodLength = $startDate->diffInDays($endDate) + 1; // inclusive of both start and end date

        // Calculate absenteeism percentage
        $absenteeismPercentage = $periodLength > 0 ? round(($totalApproveDays / $periodLength) * 100, 2) : 0.0;

        // Group leaves by type
        $leavesByType = [];
        foreach ($approvedLeaves as $leave) {
            $type = (string) $leave->leave_type;
            if (!isset($leavesByType[$type])) {
                $leavesByType[$type] = 0;
            }
            $leavesByType[$type] += (float) $leave->days;
        }

        return [
            'totalApproveDays' => $totalApproveDays,
            'periodDays' => $periodLength,
            'absenteeismPercentage' => $absenteeismPercentage,
            'leaveCount' => count($approvedLeaves),
            'leavesByType' => $leavesByType,
        ];
    }

    /**
     * Dispatch a notification to all active owner/admin users of a company.
     */
    private function notifyCompanyAdminsPerformance(?int $companyId, object $notification): void
    {
        if ($companyId === null || $companyId <= 0) {
            return;
        }

        $adminIds = CompanyUser::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->whereIn('role', ['owner', 'admin'])
            ->pluck('user_id');

        if ($adminIds->isEmpty()) {
            return;
        }

        User::query()->whereIn('id', $adminIds)->each(function (User $admin) use ($notification): void {
            try {
                $admin->notify(clone $notification);
            } catch (\Throwable) {
                // best-effort
            }
        });
    }
}
