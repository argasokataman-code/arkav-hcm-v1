<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HcmSalaryComponent;
use App\Models\OvertimeRequest;
use App\Models\User;
use App\Services\Hcm\OvertimePayCalculator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HcmOvertimeRequestController extends Controller
{
    public function __construct(
        private readonly OvertimePayCalculator $calculator
    ) {}

    public function index(Request $request): JsonResponse
    {
        $activeCompanyId = $this->activeCompanyId($request);
        if (! $activeCompanyId) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TENANT_CONTEXT_REQUIRED',
                    'message' => 'Active company context is required.',
                ],
            ], 422);
        }

        $validated = $request->validate([
            'scope' => ['nullable', 'string', 'in:me'],
            'page' => ['nullable', 'integer', 'min:1'],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:100'],
            'workDate' => ['nullable', 'date'],
            'dateFrom' => ['nullable', 'date'],
            'dateTo' => ['nullable', 'date'],
            'status' => ['nullable', 'string', Rule::in(['pending', 'approved', 'declined'])],
        ]);

        $scope = $validated['scope'] ?? null;
        $perPage = min(100, (int) ($validated['perPage'] ?? 20));

        $query = OvertimeRequest::query()
            ->with(['user:id,name,email', 'overtimeType:id,code,name', 'salaryComponent:id,code,name'])
            ->orderByDesc('id');

        // Tenant isolation: overtime requests are scoped by the requester's active company.
        $query->whereHas('user.employeeProfile', function ($p) use ($activeCompanyId): void {
            $p->where('company_id', $activeCompanyId);
        });

        $user = $request->user();
        if (! $this->canViewTeamOvertime($request)) {
            $query->where('user_id', $user->id);
        } elseif ($scope === 'me') {
            $query->where('user_id', $user->id);
        }

        $this->applyOvertimeRequestListFilters($query, $validated);

        $meta = [];
        if ($this->canViewTeamOvertime($request) && $scope !== 'me') {
            $summaryQuery = OvertimeRequest::query();
            $summaryQuery->whereHas('user.employeeProfile', function ($p) use ($activeCompanyId): void {
                $p->where('company_id', $activeCompanyId);
            });
            $this->applyOvertimeRequestListFilters($summaryQuery, $validated);
            $summaryRow = $summaryQuery
                ->selectRaw(
                    'COUNT(DISTINCT user_id) as distinct_users, '.
                    'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as pending, '.
                    'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as declined, '.
                    'SUM(CASE WHEN status = ? THEN minutes ELSE 0 END) as approved_minutes',
                    ['pending', 'declined', 'approved']
                )
                ->first();
            $meta['summary'] = [
                'distinctUsers' => (int) ($summaryRow->distinct_users ?? 0),
                'pending' => (int) ($summaryRow->pending ?? 0),
                'declined' => (int) ($summaryRow->declined ?? 0),
                'approvedMinutes' => (int) ($summaryRow->approved_minutes ?? 0),
            ];
        }

        $paginator = $query->paginate($perPage);
        $mapped = $paginator->getCollection()->map(function (OvertimeRequest $r) {
            return [
                'id' => $r->id,
                'userId' => $r->user_id,
                'employeeName' => $r->user?->name ?? '—',
                'email' => $r->user?->email ?? '—',
                'workDate' => $r->work_date->toDateString(),
                'minutes' => $r->minutes,
                'projectName' => $r->project_name ?? '',
                'overtimeTypeId' => $r->hcm_overtime_type_id,
                'overtimeTypeName' => $r->overtimeType?->name ?? '',
                'requestType' => $r->request_type ?? 'employee_request',
                'status' => $r->status,
                'policyNote' => $r->policy_note ?? '',
                'notes' => $r->notes ?? '',
                'salaryComponentId' => $r->hcm_salary_component_id,
                'salaryComponentCode' => $r->salaryComponent?->code,
                'salaryComponentName' => $r->salaryComponent?->name,
            ];
        });
        $paginator->setCollection($mapped);

        $meta['pagination'] = [
            'page' => $paginator->currentPage(),
            'perPage' => $paginator->perPage(),
            'total' => $paginator->total(),
            'totalPages' => $paginator->lastPage(),
        ];

        return response()->json(['success' => true, 'data' => $paginator->items(), 'meta' => $meta]);
    }

    private function activeCompanyId(Request $request): ?int
    {
        $value = $request->attributes->get('activeCompanyId');

        return is_numeric($value) ? (int) $value : null;
    }

    private function canViewTeamOvertime(Request $request): bool
    {
        $user = $request->user();
        $companyId = $this->activeCompanyId($request);
        if (! $user || ! $companyId) {
            return false;
        }

        return $user->hasPermissionForCompany('overtime.view', $companyId)
            || $user->hasPermissionForCompany('overtime.approve', $companyId)
            || $user->hasPermissionForCompany('attendance.admin', $companyId);
    }

    private function canManageOvertime(Request $request): bool
    {
        $user = $request->user();
        $companyId = $this->activeCompanyId($request);
        if (! $user || ! $companyId) {
            return false;
        }

        return $user->hasPermissionForCompany('overtime.approve', $companyId)
            || $user->hasPermissionForCompany('attendance.admin', $companyId);
    }

    public function store(Request $request): JsonResponse
    {
        $actor = $request->user();
        $typeExists = Rule::exists('hcm_overtime_types', 'id');
        if (! $this->canManageOvertime($request)) {
            $typeExists = Rule::exists('hcm_overtime_types', 'id')->where('is_active', true);
        }

        $validated = $request->validate([
            'userId' => ['nullable', 'integer', 'exists:users,id'],
            'overtimeTypeId' => ['nullable', 'integer', $typeExists],
            'requestType' => ['nullable', 'in:employee_request,company_assignment,missed_log_correction'],
            'workDate' => ['required', 'date'],
            'minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'status' => ['nullable', 'in:pending,approved,declined'],
            'projectName' => ['nullable', 'string', 'max:200'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'policyNote' => ['nullable', 'string', 'max:500'],
        ]);

        $userId = isset($validated['userId']) ? (int) $validated['userId'] : $actor->id;
        $requestType = $validated['requestType'] ?? 'employee_request';
        if ($userId !== $actor->id && ! $this->canManageOvertime($request)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'AUTH_FORBIDDEN', 'message' => 'Cannot create overtime for another user.'],
            ], 403);
        }
        if (! $this->canManageOvertime($request) && $requestType !== 'employee_request') {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'AUTH_FORBIDDEN', 'message' => 'Only admin can submit company/correction overtime.'],
            ], 403);
        }
        User::query()->findOrFail($userId);
        $status = $this->canManageOvertime($request) ? ($validated['status'] ?? 'pending') : 'pending';

        $otComp = HcmSalaryComponent::resolveForOvertimePay();

        $r = OvertimeRequest::query()->create([
            'user_id' => $userId,
            'hcm_overtime_type_id' => $validated['overtimeTypeId'] ?? null,
            'hcm_salary_component_id' => $otComp?->id,
            'request_type' => $requestType,
            'work_date' => $validated['workDate'],
            'minutes' => $validated['minutes'],
            'project_name' => $validated['projectName'] ?? null,
            'status' => $status,
            'approved_by_user_id' => $status === 'pending' ? null : $actor->id,
            'approved_at' => $status === 'pending' ? null : now(),
            'notes' => $validated['notes'] ?? null,
            'policy_note' => $validated['policyNote'] ?? null,
        ]);

        return response()->json(['success' => true, 'data' => ['id' => $r->id]], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $r = OvertimeRequest::query()->findOrFail($id);

        $actor = $request->user();
        if ($r->user_id !== $actor->id) {
            if (! $this->canManageOvertime($request)) {
                return response()->json([
                    'success' => false,
                    'error' => ['code' => 'AUTH_FORBIDDEN', 'message' => 'Forbidden.'],
                ], 403);
            }
            $validated = $request->validate([
                'status' => ['required', 'in:pending,approved,declined'],
                'notes' => ['nullable', 'string', 'max:2000'],
            ]);
            $r->update([
                'status' => $validated['status'],
                'notes' => $validated['notes'] ?? $r->notes,
                'approved_by_user_id' => $validated['status'] === 'pending' ? null : $actor->id,
                'approved_at' => $validated['status'] === 'pending' ? null : now(),
            ]);

            return response()->json(['success' => true]);
        }

        $typeExists = Rule::exists('hcm_overtime_types', 'id');
        if (! $this->canManageOvertime($request)) {
            $typeExists = Rule::exists('hcm_overtime_types', 'id')->where('is_active', true);
        }

        $validated = $request->validate([
            'overtimeTypeId' => ['nullable', 'integer', $typeExists],
            'requestType' => ['nullable', 'in:employee_request,company_assignment,missed_log_correction'],
            'workDate' => ['sometimes', 'date'],
            'minutes' => ['sometimes', 'integer', 'min:1', 'max:1440'],
            'projectName' => ['nullable', 'string', 'max:200'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'policyNote' => ['nullable', 'string', 'max:500'],
        ]);

        if ($r->status !== 'pending') {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'OT_NOT_EDITABLE', 'message' => 'Only pending requests can be edited.'],
            ], 422);
        }

        $payload = [];
        if (array_key_exists('overtimeTypeId', $validated)) {
            $payload['hcm_overtime_type_id'] = $validated['overtimeTypeId'];
        }
        if (array_key_exists('requestType', $validated)) {
            if (! $this->canManageOvertime($request) && $validated['requestType'] !== 'employee_request') {
                return response()->json([
                    'success' => false,
                    'error' => ['code' => 'AUTH_FORBIDDEN', 'message' => 'Only admin can set company/correction overtime.'],
                ], 403);
            }
            $payload['request_type'] = $validated['requestType'];
        }
        if (isset($validated['workDate'])) {
            $payload['work_date'] = $validated['workDate'];
        }
        if (isset($validated['minutes'])) {
            $payload['minutes'] = $validated['minutes'];
        }
        if (array_key_exists('projectName', $validated)) {
            $payload['project_name'] = $validated['projectName'];
        }
        if (array_key_exists('notes', $validated)) {
            $payload['notes'] = $validated['notes'];
        }
        if (array_key_exists('policyNote', $validated)) {
            $payload['policy_note'] = $validated['policyNote'];
        }

        if ($r->status === 'pending' && $r->user_id === $actor->id) {
            $payload['hcm_salary_component_id'] = HcmSalaryComponent::resolveForOvertimePay()?->id;
        }

        if ($payload !== []) {
            $r->update($payload);
        }

        return response()->json(['success' => true]);
    }

    public function calculate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'baseMonthlySalary' => ['required', 'numeric', 'min:0'],
            'fixedAllowance' => ['nullable', 'numeric', 'min:0'],
            'minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'dayType' => ['required', 'in:workday,holiday'],
            'weeklyWorkDays' => ['nullable', 'integer', 'in:5,6'],
        ]);

        $result = $this->calculator->calculate(
            (float) $validated['baseMonthlySalary'],
            (float) ($validated['fixedAllowance'] ?? 0),
            (int) $validated['minutes'],
            (string) $validated['dayType'],
            (int) ($validated['weeklyWorkDays'] ?? 5),
        );

        $otComp = HcmSalaryComponent::resolveForOvertimePay();
        $result['salaryComponent'] = $otComp ? [
            'id' => $otComp->id,
            'code' => $otComp->code,
            'name' => $otComp->name,
        ] : null;

        return response()->json(['success' => true, 'data' => $result]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $r = OvertimeRequest::query()->findOrFail($id);
        if ($r->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'FORBIDDEN', 'message' => 'Cannot delete another user overtime.'],
            ], 403);
        }
        if ($r->status !== 'pending') {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'OT_NOT_DELETABLE', 'message' => 'Only pending can be deleted.'],
            ], 422);
        }
        $r->delete();

        return response()->json(['success' => true]);
    }

    /**
     * @param  Builder<OvertimeRequest>  $query
     */
    private function applyOvertimeRequestListFilters(Builder $query, array $validated): void
    {
        if (! empty($validated['workDate'] ?? null)) {
            $query->whereDate('work_date', $validated['workDate']);
        } else {
            if (! empty($validated['dateFrom'] ?? null)) {
                $query->whereDate('work_date', '>=', $validated['dateFrom']);
            }
            if (! empty($validated['dateTo'] ?? null)) {
                $query->whereDate('work_date', '<=', $validated['dateTo']);
            }
        }
        if (! empty($validated['status'] ?? null)) {
            $query->where('status', $validated['status']);
        }
    }
}
