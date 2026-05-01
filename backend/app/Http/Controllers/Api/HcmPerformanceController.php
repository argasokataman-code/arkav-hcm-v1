<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ChecksPermissions;
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

class HcmPerformanceController extends Controller
{
    use ChecksPermissions;

    private const KPI_WEIGHT_GLOBAL = 0.7;
    private const BEHAVIOR_WEIGHT_GLOBAL = 0.3;

    protected function forbidden(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'AUTH_FORBIDDEN',
                'message' => 'Forbidden.',
            ],
        ], 403);
    }

    private function activeCompanyId(Request $request): ?int
    {
        $value = $request->attributes->get('activeCompanyId');

        return is_numeric($value) ? (int) $value : null;
    }

    private function userBelongsToActiveCompany(int $userId, ?int $companyId): bool
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

    private function resolveNumericOrUuidModelId(string $modelClass, mixed $identifier): ?int
    {
        if ($identifier === null || $identifier === '') {
            return null;
        }

        $raw = trim((string) $identifier);
        if ($raw === '') {
            return null;
        }

        $record = $modelClass::query()
            ->where(function (Builder $query) use ($raw): void {
                if (ctype_digit($raw)) {
                    $query->where('id', (int) $raw)
                        ->orWhere('uuid', $raw);

                    return;
                }

                $query->where('uuid', $raw);
            })
            ->first();

        return $record ? (int) $record->getKey() : null;
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
    public function goalTypes(Request $request): JsonResponse
    {
        // List goal types can be used by all authenticated users.
        $rows = PerformanceGoalType::query()
            ->orderBy('name')
            ->get()
            ->map(fn (PerformanceGoalType $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'description' => $t->description ?? '',
                'isActive' => (bool) $t->is_active,
            ])->values();

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function storeGoalType(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'performance.manage')) {
            return $forbidden;
        }

        $v = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:5000'],
            'isActive' => ['nullable', 'boolean'],
        ]);

        $t = PerformanceGoalType::query()->create([
            'name' => trim((string) $v['name']),
            'description' => isset($v['description']) ? trim((string) $v['description']) : null,
            'is_active' => (bool) ($v['isActive'] ?? true),
        ]);

        return response()->json(['success' => true, 'data' => ['id' => $t->id]], 201);
    }

    public function updateGoalType(Request $request, int $id): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'performance.manage')) {
            return $forbidden;
        }

        $t = PerformanceGoalType::query()->findOrFail($id);
        $v = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:5000'],
            'isActive' => ['nullable', 'boolean'],
        ]);

        $t->update([
            'name' => trim((string) $v['name']),
            'description' => isset($v['description']) ? trim((string) $v['description']) : null,
            'is_active' => (bool) ($v['isActive'] ?? true),
        ]);

        return response()->json(['success' => true]);
    }

    public function destroyGoalType(Request $request, int $id): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'performance.manage')) {
            return $forbidden;
        }

        PerformanceGoalType::query()->whereKey($id)->delete();
        return response()->json(['success' => true]);
    }

    public function goals(Request $request): JsonResponse
    {
        $v = $request->validate([
            'scope' => ['nullable', 'in:me,team,all'],
            'status' => ['nullable', 'in:active,inactive,completed'],
            'goalTypeId' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:200'],
            'startDate' => ['nullable', 'date'],
            'endDate' => ['nullable', 'date'],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $user = $request->user();
        $activeCompanyId = (int) ($request->attributes->get('activeCompanyId') ?? 0);
        if ($activeCompanyId <= 0) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TENANT_CONTEXT_REQUIRED',
                    'message' => 'Active company context is required.',
                ],
            ], 422);
        }

        $isAdmin = (bool) ($user?->isHcmAdminForCompany($activeCompanyId) ?? false);
        $scope = (string) ($v['scope'] ?? 'me');

        $query = PerformanceGoal::query()
            ->with(['goalType:id,name', 'user:id,name,email', 'manager:id,name,email'])
            ->orderByDesc('id');

        // Tenant isolation: goals are scoped by the goal owner's active company.
        $query->whereHas('user.employeeProfile', function ($p) use ($activeCompanyId): void {
            $p->where('company_id', $activeCompanyId);
        });

        if ($scope === 'all') {
            if (! $isAdmin) {
                return $this->forbidden();
            }
        } elseif ($scope === 'team') {
            $query->where('manager_user_id', $user->id);
        } else {
            $query->where('user_id', $user->id);
        }

        if (! empty($v['status'])) {
            $query->where('status', $v['status']);
        }
        if (! empty($v['goalTypeId'])) {
            $query->where('goal_type_id', (int) $v['goalTypeId']);
        }
        if (! empty($v['q'])) {
            $q = trim((string) $v['q']);
            $query->where(function ($qq) use ($q): void {
                $qq->where('subject', 'like', '%'.$q.'%')
                    ->orWhere('target_achievement', 'like', '%'.$q.'%')
                    ->orWhere('description', 'like', '%'.$q.'%');
            });
        }
        if (! empty($v['startDate'])) {
            $query->whereDate('start_date', '>=', $v['startDate']);
        }
        if (! empty($v['endDate'])) {
            $query->whereDate('end_date', '<=', $v['endDate']);
        }

        $rows = $query->paginate((int) ($v['perPage'] ?? 20));
        $data = $rows->getCollection()->map(fn (PerformanceGoal $g) => [
            'id' => $g->id,
            'goalType' => $g->goalType ? ['id' => $g->goalType->id, 'name' => $g->goalType->name] : null,
            'employee' => $g->user ? ['id' => $g->user->id, 'name' => $g->user->name, 'email' => $g->user->email] : null,
            'manager' => $g->manager ? ['id' => $g->manager->id, 'name' => $g->manager->name, 'email' => $g->manager->email] : null,
            'subject' => $g->subject,
            'targetAchievement' => $g->target_achievement ?? '',
            'startDate' => $g->start_date?->toDateString(),
            'endDate' => $g->end_date?->toDateString(),
            'description' => $g->description ?? '',
            'status' => $g->status,
            'progressPercent' => (int) $g->progress_percent,
            'updatedAt' => $g->updated_at?->toIso8601String(),
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

    public function storeGoal(Request $request): JsonResponse
    {
        $v = $request->validate([
            'goalTypeId' => ['nullable'],
            'userId' => ['nullable'],
            'subject' => ['required', 'string', 'max:200'],
            'targetAchievement' => ['nullable', 'string', 'max:255'],
            'startDate' => ['nullable', 'date'],
            'endDate' => ['nullable', 'date', 'after_or_equal:startDate'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['nullable', 'in:active,inactive,completed'],
            'progressPercent' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        $auth = $request->user();
        $isAdmin = $this->canManagePerformance($request);

        $goalTypeId = $this->resolveNumericOrUuidModelId(PerformanceGoalType::class, $v['goalTypeId'] ?? null);
        if (($v['goalTypeId'] ?? null) !== null && ! $goalTypeId) {
            throw ValidationException::withMessages([
                'goalTypeId' => ['The selected goal type id is invalid.'],
            ]);
        }

        $userId = $auth->id;
        if (isset($v['userId'])) {
            if (! $isAdmin) {
                return $this->forbidden();
            }

            $resolvedUserId = $this->resolveNumericOrUuidModelId(User::class, $v['userId']);
            if (! $resolvedUserId) {
                throw ValidationException::withMessages([
                    'userId' => ['The selected user id is invalid.'],
                ]);
            }

            $userId = $resolvedUserId;
        }

        $profile = EmployeeProfile::query()->where('user_id', $userId)->first();
        $managerUserId = $profile?->manager_user_id;

        $g = PerformanceGoal::query()->create([
            'goal_type_id' => $goalTypeId,
            'user_id' => $userId,
            'manager_user_id' => $managerUserId,
            'subject' => trim((string) $v['subject']),
            'target_achievement' => isset($v['targetAchievement']) ? trim((string) $v['targetAchievement']) : null,
            'start_date' => $v['startDate'] ?? null,
            'end_date' => $v['endDate'] ?? null,
            'description' => isset($v['description']) ? trim((string) $v['description']) : null,
            'status' => (string) ($v['status'] ?? 'active'),
            'progress_percent' => (int) ($v['progressPercent'] ?? 0),
        ]);

        return response()->json(['success' => true, 'data' => ['id' => $g->id]], 201);
    }

    public function updateGoal(Request $request, int $id): JsonResponse
    {
        $g = PerformanceGoal::query()->findOrFail($id);
        $auth = $request->user();
        $isAdmin = $this->canManagePerformance($request);
        $isOwner = $g->user_id === $auth->id;
        $isManager = $g->manager_user_id !== null && $g->manager_user_id === $auth->id;
        if (! $isAdmin && ! $isOwner && ! $isManager) {
            return $this->forbidden();
        }

        $v = $request->validate([
            'goalTypeId' => ['sometimes', 'nullable'],
            'subject' => ['sometimes', 'required', 'string', 'max:200'],
            'targetAchievement' => ['sometimes', 'nullable', 'string', 'max:255'],
            'startDate' => ['sometimes', 'nullable', 'date'],
            'endDate' => ['sometimes', 'nullable', 'date', 'after_or_equal:startDate'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'status' => ['sometimes', 'nullable', 'in:active,inactive,completed'],
            'progressPercent' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100'],
        ]);

        $data = [];
        if (array_key_exists('goalTypeId', $v)) {
            $goalTypeId = $this->resolveNumericOrUuidModelId(PerformanceGoalType::class, $v['goalTypeId']);
            if ($v['goalTypeId'] !== null && ! $goalTypeId) {
                throw ValidationException::withMessages([
                    'goalTypeId' => ['The selected goal type id is invalid.'],
                ]);
            }

            $data['goal_type_id'] = $goalTypeId;
        }
        if (array_key_exists('subject', $v)) {
            $data['subject'] = trim((string) $v['subject']);
        }
        if (array_key_exists('targetAchievement', $v)) {
            $data['target_achievement'] = $v['targetAchievement'] !== null ? trim((string) $v['targetAchievement']) : null;
        }
        if (array_key_exists('startDate', $v)) {
            $data['start_date'] = $v['startDate'];
        }
        if (array_key_exists('endDate', $v)) {
            $data['end_date'] = $v['endDate'];
        }
        if (array_key_exists('description', $v)) {
            $data['description'] = $v['description'] !== null ? trim((string) $v['description']) : null;
        }
        if (array_key_exists('status', $v)) {
            $data['status'] = $v['status'] ?? $g->status;
        }
        if (array_key_exists('progressPercent', $v)) {
            $data['progress_percent'] = (int) $v['progressPercent'];
        }

        if (! empty($data)) {
            $g->update($data);
        }

        return response()->json(['success' => true]);
    }

    public function destroyGoal(Request $request, int $id): JsonResponse
    {
        $g = PerformanceGoal::query()->findOrFail($id);
        $auth = $request->user();
        $isAdmin = $this->canManagePerformance($request);
        if (! $isAdmin && $g->user_id !== $auth->id) {
            return $this->forbidden();
        }

        $g->delete();
        return response()->json(['success' => true]);
    }

    // -------------------------
    // Indicator templates (admin)
    // -------------------------
    public function indicatorTemplates(Request $request): JsonResponse
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

