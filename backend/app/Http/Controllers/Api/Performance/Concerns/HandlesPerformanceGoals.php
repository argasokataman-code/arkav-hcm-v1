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

trait HandlesPerformanceGoals
{    private function activeCompanyId(Request $request): ?int
    {
        $value = $request->attributes->get('activeCompanyId');

        return is_numeric($value) ? (int) $value : null;
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
}
