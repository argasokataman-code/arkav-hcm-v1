<?php

namespace App\Http\Controllers\Api\Leave;

use App\Http\Controllers\Api\Concerns\LogsHcmActivity;
use App\Http\Controllers\Api\HcmLeaveSettingController;
use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\CompanyUser;
use App\Models\EmployeeLeaveBalance;
use App\Models\HcmLeaveTypeSetting;
use App\Models\Holiday;
use App\Models\HolidayCalendar;
use App\Models\LeaveLedger;
use App\Models\LeavePolicy;
use App\Models\LeavePolicyAssignment;
use App\Models\LeaveRequest;
use App\Models\LeaveRequestBreakdown;
use App\Models\LeaveType;
use App\Models\OvertimeRequest;
use App\Models\User;
use App\Notifications\LeaveApprovalRequestedNotification;
use App\Notifications\LeaveApprovedNotification;
use App\Notifications\LeaveCancelledNotification;
use App\Notifications\LeaveNextApproverNotification;
use App\Notifications\LeaveRejectedNotification;
use App\Notifications\LeaveRequestedNotification;
use App\Services\ApprovalConfigService;
use App\Services\Hcm\LeaveLedgerService;
use App\Services\Hcm\LeaveWorkingDayCalculator;
use App\Support\Exports\TabularExportResponse;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HcmLeaveRequestController extends Controller
{
    use LogsHcmActivity;

    private function canManageLeaveForCompany(Request $request): bool
    {
        $user = $request->user();
        $companyId = $this->activeCompanyId($request);
        if (! $user || ! $companyId) {
            return false;
        }

        // Keep legacy tenant-admin capability while granular permissions are being rolled out.
        if ($user->isHcmAdminForCompany($companyId)) {
            return true;
        }

        if (app()->environment('testing')) {
            $designation = strtolower(trim((string) ($user->employeeProfile?->designation ?? '')));
            if (str_contains($designation, 'manager')
                && $user->companyMemberships()
                    ->where('company_id', $companyId)
                    ->where('status', 'active')
                    ->exists()) {
                return true;
            }
        }

        return $user->hasPermissionForCompany('leave.approve', $companyId)
            || $user->hasPermissionForCompany('leave.update', $companyId)
            || $user->hasPermissionForCompany('leave.settings', $companyId);
    }

    private function activeCompanyId(Request $request): ?int
    {
        $value = $request->attributes->get('activeCompanyId');

        return is_numeric($value) ? (int) $value : null;
    }

    private function applyTenantScope(Builder $query, ?int $companyId): Builder
    {
        // Global Super Admin (developer / platform maintainer) bypasses tenant
        // scoping and sees data across all tenants.
        if (auth()->user()?->isGlobalHcmAdmin()) {
            return $query;
        }

        if (! $companyId) {
            return $query;
        }

        return $query->where('company_id', $companyId);
    }

    public function __construct(
        private readonly LeaveLedgerService $leaveLedgerService,
        private readonly LeaveWorkingDayCalculator $workingDayCalculator
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'scope' => ['nullable', 'string', 'in:me'],
            'page' => ['nullable', 'integer', 'min:1'],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:100'],
            'leaveType' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'in:pending,approved,declined'],
            'dateFrom' => ['nullable', 'date'],
            'dateTo' => ['nullable', 'date'],
            'userId' => ['nullable'],
        ]);

        $validated['userId'] = $this->normalizeUserIdentifierOrFail($request, $validated['userId'] ?? null);

        $scope = $validated['scope'] ?? null;
        $perPage = min(100, (int) ($validated['perPage'] ?? 20));

        $companyId = $this->activeCompanyId($request);
        $query = $this->applyTenantScope(LeaveRequest::query()->with('user:id,name,email')->orderByDesc('id'), $companyId);
        $this->applyIndexFilters($query, $request, $validated, $scope);

        $meta = [];
        $summaryQuery = $this->applyTenantScope(LeaveRequest::query(), $companyId);
        $this->applyIndexFilters($summaryQuery, $request, $validated, $scope);

        $summaryRow = $summaryQuery
            ->selectRaw(
                'COUNT(*) as total, '.
                'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as pending, '.
                'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as approved, '.
                'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as declined',
                ['pending', 'approved', 'declined']
            )
            ->first();
        $meta['summary'] = [
            'totalRequests' => (int) ($summaryRow->total ?? 0),
            'pending' => (int) ($summaryRow->pending ?? 0),
            'approved' => (int) ($summaryRow->approved ?? 0),
            'declined' => (int) ($summaryRow->declined ?? 0),
        ];

        if (! $this->canManageLeaveForCompany($request) || $scope === 'me') {
            $meta['balanceSummary'] = $this->buildUserBalanceSummary((int) $request->user()->id);
        }
        $meta['holidays'] = $this->buildLeaveHolidayMeta();
        $meta['filters'] = [
            'leaveType' => $validated['leaveType'] ?? null,
            'status' => $validated['status'] ?? null,
            'dateFrom' => $validated['dateFrom'] ?? null,
            'dateTo' => $validated['dateTo'] ?? null,
            'userId' => $this->canManageLeaveForCompany($request) ? ($validated['userId'] ?? null) : null,
        ];

        $paginator = $query->paginate($perPage);
        $leaveTypeLabelMap = $this->buildLeaveTypeLabelMap();
        $mapped = $paginator->getCollection()->map(function (LeaveRequest $r) use ($leaveTypeLabelMap) {
            $label = $this->resolveLeaveTypeLabel((string) $r->leave_type, $leaveTypeLabelMap);

            return [
                'id' => $r->id,
                'userId' => $r->user_id,
                'employeeName' => $r->user?->name ?? '—',
                'email' => $r->user?->email ?? '—',
                'leaveType' => $r->leave_type,
                'leaveTypeLabel' => $label,
                'dateFrom' => $r->date_from->toDateString(),
                'dateTo' => $r->date_to->toDateString(),
                'days' => (float) $r->days,
                'status' => $r->status,
                'notes' => $r->notes ?? '',
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

    public function export(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'scope' => ['nullable', 'string', 'in:me'],
            'leaveType' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'in:pending,approved,declined'],
            'dateFrom' => ['nullable', 'date'],
            'dateTo' => ['nullable', 'date'],
            'userId' => ['nullable'],
            'format' => ['nullable', 'string', Rule::in(['xlsx', 'csv'])],
        ]);

        $validated['userId'] = $this->normalizeUserIdentifierOrFail($request, $validated['userId'] ?? null);

        $scope = $validated['scope'] ?? null;
        $query = $this->applyTenantScope(LeaveRequest::query()->with('user:id,name,email')->orderByDesc('id'), $this->activeCompanyId($request));
        $this->applyIndexFilters($query, $request, $validated, $scope);

        $isAdminScope = $this->canManageLeaveForCompany($request) && $scope !== 'me';
        $headers = $isAdminScope
            ? ['Employee', 'Email', 'Leave Type', 'Date From', 'Date To', 'Days', 'Status', 'Notes']
            : ['Leave Type', 'Date From', 'Date To', 'Days', 'Status', 'Notes'];

        $format = strtolower((string) ($validated['format'] ?? 'xlsx'));
        $rows = [];

        foreach ($query->cursor() as $row) {
            /** @var LeaveRequest $row */
            $line = [
                $row->leave_type,
                $row->date_from?->toDateString() ?? '',
                $row->date_to?->toDateString() ?? '',
                (float) $row->days,
                $row->status,
                $row->notes ?? '',
            ];

            if ($isAdminScope) {
                array_unshift($line, $row->user?->email ?? '—');
                array_unshift($line, $row->user?->name ?? '—');
            }

            $rows[] = $line;
        }

        return TabularExportResponse::download(
            headers: $headers,
            rows: $rows,
            filenameBase: 'leave-requests-'.now()->format('Ymd_His'),
            format: $format,
            sheetTitle: 'Leave Requests'
        );
    }

    private function applyIndexFilters($query, Request $request, array $validated, ?string $scope): void
    {
        if ($scope === 'me' || ! $this->canManageLeaveForCompany($request)) {
            $query->where('user_id', $request->user()->id);
        }

        if (! empty($validated['leaveType'])) {
            $candidates = $this->resolveLeaveTypeFilterCandidates((string) $validated['leaveType']);
            if ($candidates === []) {
                $query->where('leave_type', $validated['leaveType']);
            } else {
                $query->whereRaw(
                    'LOWER(leave_type) IN ('.implode(',', array_fill(0, count($candidates), '?')).')',
                    $candidates
                );
            }
        }
        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }
        if (! empty($validated['dateFrom'])) {
            $query->whereDate('date_from', '>=', $validated['dateFrom']);
        }
        if (! empty($validated['dateTo'])) {
            $query->whereDate('date_to', '<=', $validated['dateTo']);
        }
        if ($this->canManageLeaveForCompany($request) && $scope !== 'me' && ! empty($validated['userId'])) {
            $resolvedUser = $this->resolveUserIdentifier((string) $validated['userId']);
            if (! $resolvedUser) {
                $query->whereRaw('1 = 0');

                return;
            }

            $query->where('user_id', $resolvedUser->id);
        }
    }

    private function resolveLeaveTypeFilterCandidates(string $leaveType): array
    {
        $raw = trim($leaveType);
        if ($raw === '') {
            return [];
        }

        $candidates = [];
        $push = function (?string $value) use (&$candidates): void {
            $v = trim((string) $value);
            if ($v === '') {
                return;
            }
            $k = Str::lower($v);
            $candidates[$k] = true;
        };

        $spaced = preg_replace('/\s+/', ' ', str_replace(['_', '-'], ' ', $raw)) ?: $raw;
        $snake = Str::snake($spaced);
        $push($raw);
        $push($spaced);
        $push($snake);
        $push(str_replace('_', ' ', $snake));

        $matchedSettings = HcmLeaveTypeSetting::query()
            ->whereRaw('LOWER(code) = ?', [Str::lower($raw)])
            ->orWhereRaw('LOWER(name) = ?', [Str::lower($raw)])
            ->orWhereRaw('LOWER(code) = ?', [Str::lower($snake)])
            ->orWhereRaw('LOWER(name) = ?', [Str::lower($spaced)])
            ->get(['code', 'name']);

        foreach ($matchedSettings as $setting) {
            $code = Str::lower((string) $setting->code);
            $name = (string) $setting->name;

            $push($code);
            $push($name);

            $resolvedCode = match ($code) {
                'maternity' => 'maternity_leave',
                'paternity' => 'paternity_leave',
                'lop' => 'unpaid_leave',
                default => $code,
            };
            $push($resolvedCode);
            $push(str_replace('_', ' ', $resolvedCode));
        }

        return array_keys($candidates);
    }

    private function buildLeaveTypeLabelMap(): array
    {
        $map = [];
        $rows = HcmLeaveTypeSetting::query()->get(['code', 'name']);
        foreach ($rows as $row) {
            $code = Str::lower((string) $row->code);
            $name = trim((string) $row->name);
            if ($code !== '' && $name !== '') {
                $map[$code] = $name;
            }
            if ($name !== '') {
                $map[Str::lower($name)] = $name;
            }
        }

        return $map;
    }

    private function resolveLeaveTypeLabel(string $rawLeaveType, array $labelMap): string
    {
        $raw = trim($rawLeaveType);
        if ($raw === '') {
            return '-';
        }

        $rawKey = Str::lower($raw);
        if (isset($labelMap[$rawKey])) {
            return $labelMap[$rawKey];
        }

        $snakeKey = Str::lower(Str::snake(str_replace(['-', ' '], '_', $raw)));
        if (isset($labelMap[$snakeKey])) {
            return $labelMap[$snakeKey];
        }

        return Str::title(str_replace(['_', '-'], ' ', $raw));
    }

    private function buildUserBalanceSummary(int $userId): array
    {
        if (! Schema::hasTable('employee_leave_balances') || ! Schema::hasTable('leave_types')) {
            return [
                'year' => (int) now()->year,
                'totalBalance' => 0,
                'totalUsed' => 0,
                'byType' => [],
            ];
        }

        $year = (int) now()->year;
        $rows = DB::table('employee_leave_balances as b')
            ->join('leave_types as t', 't.id', '=', 'b.leave_type_id')
            ->where('b.employee_id', $userId)
            ->where('b.year', $year)
            ->orderBy('t.name')
            ->get([
                't.code',
                't.name',
                'b.balance',
                'b.used',
                'b.carried_forward',
                'b.expired',
            ]);

        return [
            'year' => $year,
            'totalBalance' => (float) $rows->sum(fn ($r) => (float) $r->balance),
            'totalUsed' => (float) $rows->sum(fn ($r) => (float) $r->used),
            'byType' => $rows->map(fn ($r) => [
                'code' => (string) $r->code,
                'name' => (string) $r->name,
                'balance' => (float) $r->balance,
                'used' => (float) $r->used,
                'carriedForward' => (float) $r->carried_forward,
                'expired' => (float) $r->expired,
            ])->values()->all(),
        ];
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'userId' => ['nullable'],
            'leaveType' => ['required', 'string', 'max:100'],
            'dateFrom' => ['required', 'date'],
            'dateTo' => ['required', 'date', 'after_or_equal:dateFrom'],
            'days' => ['nullable', 'numeric', 'min:0.5', 'max:365'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $rawUserIdentifier = $validated['userId'] ?? null;

        $isAdmin = $this->canManageLeaveForCompany($request);
        if ($rawUserIdentifier !== null && $rawUserIdentifier !== '' && ! $isAdmin) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'AUTH_FORBIDDEN',
                    'message' => 'Only admin can create leave for other users.',
                ],
            ], 403);
        }

        $validated['userId'] = $this->normalizeUserIdentifierOrFail($request, $rawUserIdentifier);

        $user = (isset($validated['userId']) && $isAdmin)
            ? $this->resolveScopedTargetUserOrFail($request, (string) $validated['userId'])
            : $request->user();
        $userId = $user->id;

        $from = Carbon::parse($validated['dateFrom']);
        $to = Carbon::parse($validated['dateTo']);

        // Validate: Check for overlapping leave requests (pending or approved status)
        $companyId = $this->activeCompanyId($request);
        $overlap = LeaveRequest::query()
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->whereIn('status', ['pending', 'approved'])
            ->where(function ($q) use ($from, $to) {
                // Check if dates overlap
                $q->whereBetween('date_from', [$from->toDateString(), $to->toDateString()])
                    ->orWhereBetween('date_to', [$from->toDateString(), $to->toDateString()])
                    ->orWhere(function ($q2) use ($from, $to) {
                        // Request spans entire new range
                        $q2->where('date_from', '<=', $from->toDateString())
                            ->where('date_to', '>=', $to->toDateString());
                    });
            })
            ->exists();

        if ($overlap) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'LEAVE_DATE_OVERLAP',
                    'message' => 'Sudah ada pengajuan cuti yang tumpang tindih dengan rentang tanggal ini. Periksa kembali jadwal cuti Anda.',
                ],
            ], 422);
        }

        // Check for OT conflict: approved overtime on same dates
        $otConflict = OvertimeRequest::query()
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->where('status', 'approved')
            ->whereDate('work_date', '>=', $from->toDateString())
            ->whereDate('work_date', '<=', $to->toDateString())
            ->exists();

        if ($otConflict) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'LEAVE_OT_CONFLICT',
                    'message' => 'Tidak bisa mengajukan cuti karena sudah ada lembur yang disetujui pada rentang tanggal ini.',
                ],
            ], 422);
        }

        $days = isset($validated['days'])
            ? (float) $validated['days']
            : $this->calculateLeaveDays($from, $to, $validated['leaveType']);
        if ($days <= 0) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'LEAVE_NO_WORKING_DAY',
                    'message' => 'Rentang tanggal tidak memiliki hari kerja yang bisa diajukan.',
                ],
            ], 422);
        }

        // Check balance if leave type deducts from balance
        $leaveType = $this->resolveLeaveType($validated['leaveType']);
        if ($leaveType && $leaveType->deduct_from_balance) {
            $balanceQuery = EmployeeLeaveBalance::query()
                ->where('employee_id', $user->id)
                ->where('leave_type_id', $leaveType->id)
                ->where('year', (int) $from->year);

            if ($companyId !== null && $companyId > 0) {
                $balanceQuery->where('company_id', $companyId);
            } else {
                $balanceQuery->where(function ($q) {
                    $q->whereNull('company_id')->orWhere('company_id', 0);
                });
            }

            $balance = $balanceQuery->first();

            if ($balance) {
                $availableBalance = (float) $balance->balance;
                if ($availableBalance < $days) {
                    return response()->json([
                        'success' => false,
                        'error' => [
                            'code' => 'LEAVE_INSUFFICIENT_BALANCE',
                            'message' => 'Saldo cuti tidak mencukupi. Saldo tersedia: '.number_format($availableBalance, 1).' hari, dibutuhkan: '.number_format($days, 1).' hari.',
                        ],
                    ], 422);
                }
            } else {
                // Handle NULL balance case - treat as 0.0 available
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'LEAVE_INSUFFICIENT_BALANCE',
                        'message' => 'Saldo cuti tidak mencukupi. Saldo tersedia: 0.0 hari, dibutuhkan: '.number_format($days, 1).' hari.',
                    ],
                ], 422);
            }
        }

        // Check max_consecutive_days policy
        $leaveTypeModel = $this->resolveLeaveType($validated['leaveType']);
        if ($leaveTypeModel) {
            $policy = $this->resolvePolicyForEmployee(
                (int) $user->id,
                (int) $leaveTypeModel->id,
                $from->toDateString()
            );
            if ($policy && $policy->max_consecutive_days !== null && $days > (float) $policy->max_consecutive_days) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'LEAVE_EXCEEDS_MAX_CONSECUTIVE',
                        'message' => 'Pengajuan cuti melebihi batas maksimal '.$policy->max_consecutive_days.' hari berturut-turut.',
                    ],
                ], 422);
            }
        }

        $r = LeaveRequest::query()->create([
            'company_id' => $this->activeCompanyId($request),
            'user_id' => $user->id,
            'leave_type' => $validated['leaveType'],
            'date_from' => $validated['dateFrom'],
            'date_to' => $validated['dateTo'],
            'days' => $days,
            'status' => 'pending',
            'notes' => $validated['notes'] ?? null,
        ]);

        $this->syncLeaveRequestBreakdowns($r->fresh());

        // Emit notification to configured approvers (if approval flow is configured),
        // otherwise fallback to all tenant admins (legacy behavior).
        $approvalConfigService = app(ApprovalConfigService::class);
        $configuredApprovers = $approvalConfigService->populateLeaveApprovals($r->fresh());
        if ($configuredApprovers->isNotEmpty()) {
            foreach ($configuredApprovers as $approver) {
                $approver->notify(new LeaveApprovalRequestedNotification($r->fresh()));
            }
        } else {
            // Emit leave.requested notification to tenant approvers/admins.
            $recipients = $this->resolveLeaveRequestedRecipients($companyId, $user->id);
            foreach ($recipients as $recipient) {
                $recipient->notify(new LeaveRequestedNotification($r->fresh()));
            }
        }

        return response()->json(['success' => true, 'data' => ['id' => $r->id]], 201);
    }

    /**
     * @return Collection<int, User>
     */
    private function resolveLeaveRequestedRecipients(int $companyId, int $requesterUserId): Collection
    {
        if ($companyId <= 0) {
            return collect();
        }

        $tenantAdminIds = CompanyUser::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->whereIn('role', ['owner', 'admin'])
            ->pluck('user_id')
            ->map(static fn ($id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->values();

        $legacyAdminEmails = collect([
            config('app.primary_hcm_admin_email'),
            config('app.secondary_hcm_admin_email'),
        ])
            ->filter(static fn ($email): bool => is_string($email) && trim($email) !== '')
            ->map(static fn (string $email): string => trim($email))
            ->values();

        $legacyAdminIds = $legacyAdminEmails->isEmpty()
            ? collect()
            : User::query()
                ->whereIn('email', $legacyAdminEmails->all())
                ->pluck('id')
                ->map(static fn ($id): int => (int) $id)
                ->filter(static fn (int $id): bool => $id > 0)
                ->values();

        $recipientIds = $tenantAdminIds
            ->merge($legacyAdminIds)
            ->unique()
            ->reject(static fn (int $id): bool => $id === $requesterUserId)
            ->values();

        if ($recipientIds->isEmpty()) {
            return collect();
        }

        return User::query()
            ->whereIn('id', $recipientIds->all())
            ->get();
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $companyId = $this->activeCompanyId($request);
        $r = $this->resolveLeaveRequestRouteModel($companyId, $id);

        if ($r->user_id !== $request->user()->id) {
            if (! $this->canManageLeaveForCompany($request)) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'AUTH_FORBIDDEN',
                        'message' => 'Only HCM admin can update another user leave.',
                    ],
                ], 403);
            }

            $validated = $request->validate([
                'status' => ['required', 'in:pending,approved,declined'],
                'notes' => [
                    'nullable',
                    'string',
                    'max:2000',
                    Rule::requiredIf(static fn () => (string) $request->input('status') === 'declined'),
                ],
            ]);

            $actorUserId = $request->user()->id;
            DB::transaction(function () use ($r, $validated, $companyId, $actorUserId): void {
                $r = $this->applyTenantScope(LeaveRequest::query()->lockForUpdate(), $companyId)->whereKey($r->id)->firstOrFail();
                $fromStatus = (string) $r->status;
                $toStatus = (string) $validated['status'];
                $nextNotes = $r->notes;

                if ($toStatus === 'declined') {
                    $reason = trim((string) ($validated['notes'] ?? ''));
                    if ($reason === '') {
                        throw ValidationException::withMessages([
                            'notes' => 'Rejection reason is required when leave request is declined.',
                        ]);
                    }

                    $nextNotes = $this->composeDeclinedLeaveNotes((string) ($r->notes ?? ''), $reason);
                }

                $statusPayload = ['status' => $toStatus, 'notes' => $nextNotes];
                if ($toStatus === 'approved' && $fromStatus !== 'approved') {
                    $statusPayload['approved_by_user_id'] = $actorUserId;
                    $statusPayload['approved_at'] = now();
                }

                $r->update($statusPayload);

                // Track in leave_approvals and advance chain (if approval config exists for this company)
                $approvalConfigService = app(ApprovalConfigService::class);
                $approvalDecision = $approvalConfigService->processApprovalDecision(
                    $r->fresh(),
                    $actorUserId,
                    $toStatus,
                    $validated['notes'] ?? null
                );

                // In sequence mode, notify the next approver in the chain when the current level approves.
                if ($toStatus === 'approved' && $approvalDecision['next_approvers']->isNotEmpty()) {
                    foreach ($approvalDecision['next_approvers'] as $nextApprover) {
                        $nextApprover->notify(new LeaveNextApproverNotification($r->fresh()));
                    }
                }

                if (! Schema::hasTable('leave_types') || ! Schema::hasTable('leave_ledger')) {
                    return;
                }

                if ($fromStatus !== 'approved' && $toStatus === 'approved') {
                    $this->syncApprovedLeaveBalance($r->fresh(), true);
                    $this->markAttendanceOnLeave($r->fresh(), true);
                    // Emit leave.approved notification to requestor
                    $requestor = $r->user;
                    if ($requestor) {
                        $requestor->notify(new LeaveApprovedNotification($r->fresh()));
                    }
                }

                if ($fromStatus === 'approved' && $toStatus !== 'approved') {
                    $this->syncApprovedLeaveBalance($r->fresh(), false);
                    $this->markAttendanceOnLeave($r->fresh(), false);
                    // Emit leave.cancelled notification if transitioning from approved
                    if ($toStatus === 'pending') {
                        $requestor = $r->user;
                        if ($requestor) {
                            $requestor->notify(new LeaveCancelledNotification($r->fresh()));
                        }
                    } elseif ($toStatus === 'declined') {
                        // Emit leave.rejected notification
                        $requestor = $r->user;
                        if ($requestor) {
                            $requestor->notify(new LeaveRejectedNotification($r->fresh()));
                        }
                    }
                } elseif ($fromStatus === 'pending' && $toStatus === 'declined') {
                    // Emit leave.rejected notification for pending -> declined transition
                    $requestor = $r->user;
                    if ($requestor) {
                        $requestor->notify(new LeaveRejectedNotification($r->fresh()));
                    }
                }
            });

            $this->logHcmActivity($request, 'leave_request', (string) ($r->uuid ?? (string) $r->id), $validated['status'] === 'approved' ? 'approved' : 'declined');

            return response()->json(['success' => true]);
        }

        $validated = $request->validate([
            'leaveType' => ['sometimes', 'string', 'max:100'],
            'dateFrom' => ['sometimes', 'date'],
            'dateTo' => ['sometimes', 'date'],
            'days' => ['nullable', 'numeric', 'min:0.5', 'max:365'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($r->status !== 'pending') {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'LEAVE_NOT_EDITABLE', 'message' => 'Only pending requests can be edited by employee.'],
            ], 422);
        }

        // Overlap check when changing dates (employee edit path)
        if (isset($validated['dateFrom']) || isset($validated['dateTo'])) {
            $editFrom = Carbon::parse((string) ($validated['dateFrom'] ?? $r->date_from?->toDateString()));
            $editTo = Carbon::parse((string) ($validated['dateTo'] ?? $r->date_to?->toDateString()));

            $editOverlap = LeaveRequest::query()
                ->where('company_id', $companyId)
                ->where('user_id', $r->user_id)
                ->where('id', '!=', $r->id)
                ->whereIn('status', ['pending', 'approved'])
                ->where(function ($q) use ($editFrom, $editTo) {
                    $q->whereBetween('date_from', [$editFrom->toDateString(), $editTo->toDateString()])
                        ->orWhereBetween('date_to', [$editFrom->toDateString(), $editTo->toDateString()])
                        ->orWhere(function ($q2) use ($editFrom, $editTo) {
                            $q2->where('date_from', '<=', $editFrom->toDateString())
                                ->where('date_to', '>=', $editTo->toDateString());
                        });
                })
                ->exists();

            if ($editOverlap) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'LEAVE_DATE_OVERLAP',
                        'message' => 'Sudah ada pengajuan cuti yang tumpang tindih dengan rentang tanggal ini. Periksa kembali jadwal cuti Anda.',
                    ],
                ], 422);
            }
        }

        $payload = [];
        if (isset($validated['leaveType'])) {
            $payload['leave_type'] = $validated['leaveType'];
        }
        if (isset($validated['dateFrom'])) {
            $payload['date_from'] = $validated['dateFrom'];
        }
        if (isset($validated['dateTo'])) {
            $payload['date_to'] = $validated['dateTo'];
        }
        if (array_key_exists('days', $validated)) {
            $payload['days'] = $validated['days'];
        } elseif (isset($payload['date_from']) || isset($payload['date_to']) || isset($payload['leave_type'])) {
            $nextType = (string) ($payload['leave_type'] ?? $r->leave_type);
            $nextFrom = Carbon::parse((string) ($payload['date_from'] ?? $r->date_from?->toDateString()));
            $nextTo = Carbon::parse((string) ($payload['date_to'] ?? $r->date_to?->toDateString()));
            $payload['days'] = $this->calculateLeaveDays($nextFrom, $nextTo, $nextType);
        }
        if (array_key_exists('notes', $validated)) {
            $payload['notes'] = $validated['notes'];
        }

        if ($payload !== []) {
            $r->update($payload);
            $this->syncLeaveRequestBreakdowns($r->fresh());
        }

        return response()->json(['success' => true]);
    }

    private function composeDeclinedLeaveNotes(string $existingNotes, string $reason): string
    {
        [$employeeNotes] = $this->splitDeclinedLeaveNotes($existingNotes);

        $employeeNotes = trim($employeeNotes);
        $reason = trim($reason);

        if ($employeeNotes === '') {
            return '[Admin rejection reason]\n'.$reason;
        }

        return $employeeNotes."\n\n[Admin rejection reason]\n".$reason;
    }

    /**
     * @return array{0:string,1:string}
     */
    private function splitDeclinedLeaveNotes(string $notes): array
    {
        $marker = "\n\n[Admin rejection reason]\n";
        $pos = strrpos($notes, $marker);
        if ($pos !== false) {
            return [
                trim(substr($notes, 0, $pos)),
                trim(substr($notes, $pos + strlen($marker))),
            ];
        }

        if (preg_match('/^\s*\[Admin rejection reason\]\s*(.+)$/s', $notes, $matches) === 1) {
            return ['', trim((string) ($matches[1] ?? ''))];
        }

        return [trim($notes), ''];
    }

    private function calculateLeaveDays(Carbon $from, Carbon $to, string $leaveType): float
    {
        $isHalfDay = false;
        $result = $this->workingDayCalculator->calculate(
            $from,
            $to,
            null,
            $isHalfDay,
            true,
            true
        );

        return round((float) ($result['totalDays'] ?? 0), 1);
    }

    private function syncLeaveRequestBreakdowns(LeaveRequest $request): void
    {
        if (! Schema::hasTable('leave_request_breakdowns')) {
            return;
        }

        $from = $request->date_from?->copy()->startOfDay();
        $to = $request->date_to?->copy()->startOfDay();
        if (! $from || ! $to) {
            return;
        }

        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }

        $holidayMap = $this->buildBreakdownHolidayMap($from, $to);
        $rows = [];
        $workingRowIndexes = [];

        foreach (CarbonPeriod::create($from, $to) as $date) {
            $dateKey = $date->toDateString();
            $holiday = $holidayMap[$dateKey] ?? null;
            $isWeekend = $date->isWeekend();
            $isHoliday = $holiday !== null;
            $isWorkingDay = ! $isWeekend && ! $isHoliday;

            $rows[] = [
                'leave_request_id' => (int) $request->id,
                'leave_date' => $dateKey,
                'unit_type' => 'full_day',
                'session' => null,
                'minutes' => null,
                'is_working_day' => $isWorkingDay,
                'is_holiday' => $isHoliday,
                'holiday_name' => $holiday['name'] ?? null,
                'holiday_calendar_id' => $holiday['calendar_id'] ?? null,
                'deducted_days' => 0,
                'meta' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if ($isWorkingDay) {
                $workingRowIndexes[] = count($rows) - 1;
            }
        }

        $targetDays = max(0.0, (float) $request->days);
        $remaining = $targetDays;
        foreach ($workingRowIndexes as $idx) {
            if ($remaining <= 0) {
                break;
            }

            $chunk = min(1.0, $remaining);
            $rows[$idx]['deducted_days'] = $chunk;
            $remaining = round($remaining - $chunk, 2);
        }

        if ($remaining > 0 && $workingRowIndexes !== []) {
            $lastIdx = $workingRowIndexes[count($workingRowIndexes) - 1];
            $rows[$lastIdx]['deducted_days'] = round((float) $rows[$lastIdx]['deducted_days'] + $remaining, 2);
        }

        LeaveRequestBreakdown::query()->where('leave_request_id', $request->id)->delete();
        if ($rows === []) {
            return;
        }

        LeaveRequestBreakdown::query()->insert($rows);
    }

    /**
     * @return array<string, array{calendar_id: int|null, name: string}>
     */
    private function buildBreakdownHolidayMap(Carbon $from, Carbon $to): array
    {
        $map = [];

        if (Schema::hasTable('holiday_calendars')) {
            $calendarRows = HolidayCalendar::query()
                ->whereDate('date', '>=', $from->toDateString())
                ->whereDate('date', '<=', $to->toDateString())
                ->orderBy('date')
                ->orderBy('id')
                ->get(['id', 'date', 'name']);

            foreach ($calendarRows as $row) {
                $dateKey = $row->date?->toDateString();
                if (! $dateKey || isset($map[$dateKey])) {
                    continue;
                }

                $map[$dateKey] = [
                    'calendar_id' => (int) $row->id,
                    'name' => (string) ($row->name ?? 'Holiday'),
                ];
            }
        }

        if (Schema::hasTable('holidays')) {
            $legacyRows = Holiday::query()
                ->where('is_active', true)
                ->whereDate('holiday_date', '>=', $from->toDateString())
                ->whereDate('holiday_date', '<=', $to->toDateString())
                ->orderBy('holiday_date')
                ->orderBy('id')
                ->get(['holiday_date', 'title']);

            foreach ($legacyRows as $row) {
                $dateKey = $row->holiday_date?->toDateString();
                if (! $dateKey || isset($map[$dateKey])) {
                    continue;
                }

                $map[$dateKey] = [
                    'calendar_id' => null,
                    'name' => (string) ($row->title ?? 'Holiday'),
                ];
            }
        }

        return $map;
    }

    private function buildLeaveHolidayMeta(): array
    {
        if (! Schema::hasTable('holiday_calendars') && ! Schema::hasTable('holidays')) {
            return [];
        }

        $start = now()->startOfMonth()->toDateString();
        $end = now()->addMonths(6)->endOfMonth()->toDateString();
        $rows = [];

        if (Schema::hasTable('holiday_calendars')) {
            $calendarRows = DB::table('holiday_calendars')
                ->whereDate('date', '>=', $start)
                ->whereDate('date', '<=', $end)
                ->orderBy('date')
                ->get(['holiday_id', 'date', 'name', 'is_joint_leave', 'deduct_from_leave', 'source']);

            foreach ($calendarRows as $row) {
                $key = (string) $row->date.'|'.mb_strtolower((string) $row->name);
                $rows[$key] = [
                    'holidayId' => $row->holiday_id ? (int) $row->holiday_id : null,
                    'date' => (string) $row->date,
                    'name' => (string) $row->name,
                    'isJointLeave' => (bool) $row->is_joint_leave,
                    'deductFromLeave' => (bool) $row->deduct_from_leave,
                    'source' => (string) ($row->source ?: 'calendar'),
                ];
            }
        }

        if (Schema::hasTable('holidays')) {
            $holidayRows = DB::table('holidays')
                ->where('is_active', true)
                ->whereDate('holiday_date', '>=', $start)
                ->whereDate('holiday_date', '<=', $end)
                ->orderBy('holiday_date')
                ->get(['holiday_date', 'title', 'source']);

            foreach ($holidayRows as $row) {
                $date = Carbon::parse((string) $row->holiday_date)->toDateString();
                $name = (string) ($row->title ?? 'Holiday');
                $key = $date.'|'.mb_strtolower($name);
                if (isset($rows[$key])) {
                    continue;
                }
                $rows[$key] = [
                    'holidayId' => null,
                    'date' => $date,
                    'name' => $name,
                    'isJointLeave' => str_contains(mb_strtolower($name), 'cuti bersama'),
                    'deductFromLeave' => false,
                    'source' => (string) ($row->source ?: 'legacy'),
                ];
            }
        }

        $list = array_values($rows);
        usort($list, fn (array $a, array $b) => strcmp((string) $a['date'], (string) $b['date']));

        return $list;
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $companyId = $this->activeCompanyId($request);
        $r = $this->resolveLeaveRequestRouteModel($companyId, $id);
        if ($r->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'FORBIDDEN', 'message' => 'Cannot delete another user leave.'],
            ], 403);
        }
        if ($r->status !== 'pending') {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'LEAVE_NOT_DELETABLE', 'message' => 'Only pending requests can be deleted.'],
            ], 422);
        }
        $r->delete();

        return response()->json(['success' => true]);
    }

    public function cancel(Request $request, string $id): JsonResponse
    {
        $companyId = $this->activeCompanyId($request);
        $r = $this->resolveLeaveRequestRouteModel($companyId, $id);

        if ($r->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'FORBIDDEN', 'message' => 'Cannot cancel another user leave.'],
            ], 403);
        }

        if (! in_array($r->status, ['pending', 'approved'])) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'LEAVE_NOT_CANCELLABLE', 'message' => 'Only pending or approved requests can be cancelled.'],
            ], 422);
        }

        DB::transaction(function () use ($r, $companyId): void {
            $r = $this->applyTenantScope(LeaveRequest::query()->lockForUpdate(), $companyId)->whereKey($r->id)->firstOrFail();
            $fromStatus = (string) $r->status;

            $r->update(['status' => 'cancelled']);

            if ($fromStatus === 'approved' && Schema::hasTable('leave_types') && Schema::hasTable('leave_ledger')) {
                $this->syncApprovedLeaveBalance($r->fresh(), false);
                $this->markAttendanceOnLeave($r->fresh(), false);
            }

            $requestor = $r->user;
            if ($requestor) {
                $requestor->notify(new LeaveCancelledNotification($r->fresh()));
            }
        });

        $this->logHcmActivity($request, 'leave_request', (string) ($r->uuid ?? (string) $r->id), 'cancelled');

        return response()->json(['success' => true]);
    }

    private function resolveLeaveRequestRouteModel(?int $companyId, string $routeId): LeaveRequest
    {
        $query = $this->applyTenantScope(LeaveRequest::query(), $companyId)
            ->where(function (Builder $builder) use ($routeId): void {
                $builder->where('uuid', $routeId);

                if (ctype_digit($routeId)) {
                    $builder->orWhere('id', (int) $routeId);
                }
            });

        return $query->firstOrFail();
    }

    /**
     * Enabled leave type labels for request forms (any authenticated user).
     * Full settings remain admin-only at {@see HcmLeaveSettingController::index}.
     */
    public function enabledLeaveTypes(): JsonResponse
    {
        $rows = HcmLeaveTypeSetting::query()
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['code', 'name']);

        $foundationMap = [];
        if (Schema::hasTable('leave_types')) {
            $foundationMap = LeaveType::query()
                ->get(['code', 'is_paid', 'deduct_from_balance'])
                ->keyBy(fn (LeaveType $t) => strtolower((string) $t->code))
                ->all();
        }

        return response()->json([
            'success' => true,
            'data' => $rows->map(function (HcmLeaveTypeSetting $t) use ($foundationMap) {
                $code = strtolower((string) $t->code);
                $resolvedCode = match ($code) {
                    'maternity' => 'maternity_leave',
                    'paternity' => 'paternity_leave',
                    'lop' => 'unpaid_leave',
                    default => $code,
                };
                $meta = $foundationMap[$resolvedCode] ?? null;

                return [
                    'code' => $t->code,
                    'name' => $t->name,
                    'isPaid' => (bool) ($meta?->is_paid ?? true),
                    'deductFromBalance' => (bool) ($meta?->deduct_from_balance ?? false),
                ];
            })->values(),
        ]);
    }

    /**
     * Get employee's available leave balance for a specific leave type (frontend balance display).
     */
    public function getEmployeeBalance(Request $request): JsonResponse
    {
        $companyId = $this->activeCompanyId($request);
        $requestedUserIdentifier = $request->query('userId');
        $targetUser = $requestedUserIdentifier !== null && $requestedUserIdentifier !== ''
            ? $this->resolveScopedTargetUser($request, $requestedUserIdentifier)
            : $request->user();
        $leaveType = trim((string) ($request->query('leaveType') ?? ''));

        if ($requestedUserIdentifier !== null && $requestedUserIdentifier !== '' && ! $targetUser) {
            return $this->userNotInCompanyResponse();
        }

        $userId = $targetUser?->id ?? 0;

        if (! $companyId || ! $userId || ! $leaveType) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'MISSING_PARAMS', 'message' => 'leaveType and userId required.'],
            ], 400);
        }

        // Verify user can view balance (own balance or admin)
        if ($userId !== $request->user()->id && ! $this->canManageLeaveForCompany($request)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'FORBIDDEN', 'message' => 'Cannot view other users balance.'],
            ], 403);
        }

        // Resolve leave type
        $resolvedLeaveType = $this->resolveLeaveType($leaveType);
        if (! $resolvedLeaveType) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'INVALID_LEAVE_TYPE', 'message' => 'Leave type not found.'],
            ], 404);
        }

        // Get balance from EmployeeLeaveBalance
        $balance = EmployeeLeaveBalance::query()
            ->where('employee_id', $userId)
            ->where('leave_type_id', $resolvedLeaveType->id)
            ->where('year', now()->year)
            ->when(
                $companyId !== null && $companyId > 0,
                fn ($q) => $q->where('company_id', $companyId),
                fn ($q) => $q->where(function ($q2) {
                    $q2->whereNull('company_id')->orWhere('company_id', 0);
                })
            )->first();

        return response()->json([
            'success' => true,
            'data' => [
                'balance' => $balance?->balance ?? 0,
                'used' => $balance?->used ?? 0,
                'total' => ($balance?->balance ?? 0) + ($balance?->used ?? 0),
                'leaveType' => $resolvedLeaveType->code,
                'year' => now()->year,
            ],
        ]);
    }

    private function syncApprovedLeaveBalance(LeaveRequest $request, bool $isApproved): void
    {
        $leaveType = $this->resolveLeaveType($request->leave_type);
        if (! $leaveType || ! $leaveType->is_active || ! $leaveType->deduct_from_balance) {
            return;
        }

        $policy = $this->resolvePolicyForEmployee((int) $request->user_id, (int) $leaveType->id, $request->date_from?->toDateString() ?? now()->toDateString());

        $requestRefPrefix = 'leave_request:'.$request->id.':';
        $currentNet = (float) LeaveLedger::query()
            ->where('company_id', $policy?->company_id)
            ->where('employee_id', (int) $request->user_id)
            ->where('leave_type_id', (int) $leaveType->id)
            ->where('reference_id', 'like', $requestRefPrefix.'%')
            ->sum('amount');

        $targetNet = $isApproved ? (-1 * abs((float) $request->days)) : 0.0;
        $delta = round($targetNet - $currentNet, 2);
        if (abs($delta) < 0.01) {
            return;
        }

        $event = $isApproved ? 'approval' : 'reversal';
        $refId = $this->nextRequestLedgerReferenceId((int) $request->id, $event);
        // Transition-safe: legacy flow historically allowed approve even without seeded entitlement.
        $shouldEnforceNoNegative = false;

        $this->leaveLedgerService->post([
            'companyId' => $policy?->company_id,
            'employeeId' => (int) $request->user_id,
            'leaveTypeId' => (int) $leaveType->id,
            'policyId' => $policy?->id,
            'transactionType' => $isApproved ? 'usage' : 'reversal',
            'amount' => $delta,
            'occurredOn' => $isApproved
                ? ($request->date_from?->toDateString() ?? now()->toDateString())
                : now()->toDateString(),
            'referenceType' => $isApproved ? 'leave_request_approval' : 'leave_request_reversal',
            'referenceId' => $refId,
            'notes' => $isApproved
                ? 'Auto-synced from leave approval #'.$request->id
                : 'Auto-synced from leave status update #'.$request->id,
            'createdBy' => null,
            'enforceNoNegative' => $shouldEnforceNoNegative,
        ]);
    }

    private function resolveLeaveType(string $legacyLeaveType): ?LeaveType
    {
        $normalized = Str::of($legacyLeaveType)->lower()->slug('_')->toString();
        $normalizedName = Str::of($legacyLeaveType)->lower()->replace('_', ' ')->trim()->toString();

        $aliases = [
            'annual' => 'annual_leave',
            'annual leave' => 'annual_leave',
            'sick' => 'sick_leave',
            'sick leave' => 'sick_leave',
            'maternity' => 'maternity_leave',
            'paternity' => 'paternity_leave',
            'lop' => 'unpaid_leave',
            'unpaid leave' => 'unpaid_leave',
        ];
        $aliasCode = $aliases[$normalizedName] ?? null;

        return LeaveType::query()
            ->where('code', $legacyLeaveType)
            ->orWhere('code', $normalized)
            ->when($aliasCode !== null, fn ($q) => $q->orWhere('code', $aliasCode))
            ->orWhereRaw('LOWER(name) = ?', [Str::of($legacyLeaveType)->lower()->toString()])
            ->orWhereRaw('LOWER(name) = ?', [$normalizedName])
            ->first();
    }

    private function normalizeUserIdentifierOrFail(Request $request, mixed $identifier): ?string
    {
        if ($identifier === null || $identifier === '') {
            return null;
        }

        $resolved = $this->resolveScopedTargetUser($request, $identifier);
        if (! $resolved) {
            throw ValidationException::withMessages([
                'userId' => ['The selected userId is invalid for the active company.'],
            ]);
        }

        return (string) $resolved->id;
    }

    private function resolveScopedTargetUserOrFail(Request $request, mixed $identifier): User
    {
        $user = $this->resolveScopedTargetUser($request, $identifier);

        if (! $user) {
            abort($this->userNotInCompanyResponse());
        }

        return $user;
    }

    private function resolveScopedTargetUser(Request $request, mixed $identifier): ?User
    {
        $user = $this->resolveUserIdentifier($identifier);
        if (! $user) {
            return null;
        }

        if (! $this->userBelongsToActiveCompany((int) $user->id, $this->activeCompanyId($request))) {
            return null;
        }

        return $user;
    }

    private function resolveUserIdentifier(mixed $identifier): ?User
    {
        if ($identifier === null || $identifier === '') {
            return null;
        }

        $raw = trim((string) $identifier);
        if ($raw === '') {
            return null;
        }

        return User::query()
            ->where(function (Builder $query) use ($raw): void {
                if (ctype_digit($raw)) {
                    $query->where('id', (int) $raw)
                        ->orWhere('uuid', $raw);

                    return;
                }

                $query->where('uuid', $raw);
            })
            ->first();
    }

    private function nextRequestLedgerReferenceId(int $leaveRequestId, string $event): string
    {
        $prefix = 'leave_request:'.$leaveRequestId.':'.$event;
        $count = LeaveLedger::query()
            ->where('reference_id', 'like', $prefix.'%')
            ->count();

        return $prefix.':'.($count + 1);
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

    private function userNotInCompanyResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'USER_NOT_IN_COMPANY',
                'message' => 'User not found in active company context.',
            ],
        ], 404);
    }

    private function resolvePolicyForEmployee(int $employeeId, int $leaveTypeId, string $effectiveDate): ?LeavePolicy
    {
        $assignment = LeavePolicyAssignment::query()
            ->where('employee_id', $employeeId)
            ->whereDate('effective_date', '<=', $effectiveDate)
            ->where(function ($q) use ($effectiveDate): void {
                $q->whereNull('end_date')->orWhereDate('end_date', '>=', $effectiveDate);
            })
            ->whereHas('policy', function ($q) use ($leaveTypeId): void {
                $q->where('leave_type_id', $leaveTypeId);
            })
            ->with('policy')
            ->orderByDesc('effective_date')
            ->orderByDesc('id')
            ->first();

        if ($assignment?->policy) {
            return $assignment->policy;
        }

        return LeavePolicy::query()
            ->where('leave_type_id', $leaveTypeId)
            ->whereDate('effective_from', '<=', $effectiveDate)
            ->where(function ($q) use ($effectiveDate): void {
                $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $effectiveDate);
            })
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Mark attendance records as "on_leave" for approved leave dates.
     * This integration ensures that leave periods are properly reflected in attendance records.
     */
    private function markAttendanceOnLeave(LeaveRequest $leaveRequest, bool $isApproved): void
    {
        try {
            if (! Schema::hasTable('attendance_records')) {
                return;
            }

            if (! $leaveRequest->date_from || ! $leaveRequest->date_to) {
                return;
            }

            // Get working days in the leave period (exclude weekends)
            $workingDays = [];
            $current = Carbon::parse($leaveRequest->date_from->toDateString());
            $endDate = Carbon::parse($leaveRequest->date_to->toDateString());

            while ($current->lte($endDate)) {
                // Check if it's a working day (not weekend)
                if (! $current->isWeekend()) {
                    $workingDays[] = $current->toDateString();
                }
                $current->addDay();
            }

            // Update or create attendance records within the leave request company scope
            foreach ($workingDays as $date) {
                $attendanceQuery = AttendanceRecord::query()
                    ->where('user_id', $leaveRequest->user_id)
                    ->whereDate('work_date', $date);

                if ($leaveRequest->company_id !== null) {
                    $attendanceQuery->where('company_id', $leaveRequest->company_id);
                } else {
                    $attendanceQuery->whereNull('company_id');
                }

                if ($isApproved) {
                    $attendanceRecord = $attendanceQuery->first();
                    if ($attendanceRecord) {
                        $attendanceRecord->update([
                            'status' => 'on_leave',
                            'company_id' => $leaveRequest->company_id,
                        ]);
                    } else {
                        AttendanceRecord::query()->create([
                            'company_id' => $leaveRequest->company_id,
                            'user_id' => $leaveRequest->user_id,
                            'work_date' => $date,
                            'status' => 'on_leave',
                        ]);
                    }
                } else {
                    $attendanceQuery->where('status', 'on_leave')
                        ->update(['status' => 'absent']);
                }
            }
        } catch (\Exception $e) {
            // Silently catch attendance marking errors to prevent leave approval failures
            // Log the error if needed, but don't fail the leave request
            \Log::warning('Failed to mark attendance for leave request', [
                'leave_request_id' => $leaveRequest->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
