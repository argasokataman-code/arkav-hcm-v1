<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HcmLeaveTypeSetting;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Holiday;
use App\Models\HolidayCalendar;
use App\Models\LeaveLedger;
use App\Models\LeavePolicy;
use App\Models\LeavePolicyAssignment;
use App\Models\LeaveRequest;
use App\Models\LeaveRequestBreakdown;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\Hcm\LeaveLedgerService;
use App\Services\Hcm\LeaveWorkingDayCalculator;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HcmLeaveRequestController extends Controller
{
    private function activeCompanyId(Request $request): ?int
    {
        $value = $request->attributes->get('activeCompanyId');

        return is_numeric($value) ? (int) $value : null;
    }

    private function applyTenantScope(Builder $query, ?int $companyId): Builder
    {
        if (! $companyId) {
            return $query;
        }

        return $query->where(function (Builder $inner) use ($companyId): void {
            $inner->where('company_id', $companyId)->orWhereNull('company_id');
        });
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
            'userId' => ['nullable', 'integer', 'exists:users,id'],
        ]);

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

        if (! $request->user()->isHcmAdmin() || $scope === 'me') {
            $meta['balanceSummary'] = $this->buildUserBalanceSummary((int) $request->user()->id);
        }
        $meta['holidays'] = $this->buildLeaveHolidayMeta();
        $meta['filters'] = [
            'leaveType' => $validated['leaveType'] ?? null,
            'status' => $validated['status'] ?? null,
            'dateFrom' => $validated['dateFrom'] ?? null,
            'dateTo' => $validated['dateTo'] ?? null,
            'userId' => $request->user()->isHcmAdmin() ? ($validated['userId'] ?? null) : null,
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
            'userId' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $scope = $validated['scope'] ?? null;
        $query = $this->applyTenantScope(LeaveRequest::query()->with('user:id,name,email')->orderByDesc('id'), $this->activeCompanyId($request));
        $this->applyIndexFilters($query, $request, $validated, $scope);

        $isAdminScope = $request->user()->isHcmAdmin() && $scope !== 'me';
        $headers = $isAdminScope
            ? ['Employee', 'Email', 'Leave Type', 'Date From', 'Date To', 'Days', 'Status', 'Notes']
            : ['Leave Type', 'Date From', 'Date To', 'Days', 'Status', 'Notes'];

        $filename = 'leave-requests-'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($query, $headers, $isAdminScope): void {
            $handle = fopen('php://output', 'wb');
            if (! $handle) {
                return;
            }

            fputcsv($handle, $headers);

            foreach ($query->cursor() as $row) {
                /** @var LeaveRequest $row */
                $line = [
                    $row->leave_type,
                    $row->date_from?->toDateString() ?? '',
                    $row->date_to?->toDateString() ?? '',
                    (string) ((float) $row->days),
                    $row->status,
                    $row->notes ?? '',
                ];

                if ($isAdminScope) {
                    array_unshift($line, $row->user?->email ?? '—');
                    array_unshift($line, $row->user?->name ?? '—');
                }

                fputcsv($handle, $line);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function applyIndexFilters($query, Request $request, array $validated, ?string $scope): void
    {
        if ($scope === 'me' || ! $request->user()->isHcmAdmin()) {
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
        if ($request->user()->isHcmAdmin() && $scope !== 'me' && ! empty($validated['userId'])) {
            $query->where('user_id', (int) $validated['userId']);
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
            'userId' => ['nullable', 'integer', 'exists:users,id'],
            'leaveType' => ['required', 'string', 'max:100'],
            'dateFrom' => ['required', 'date'],
            'dateTo' => ['required', 'date', 'after_or_equal:dateFrom'],
            'days' => ['nullable', 'numeric', 'min:0.5', 'max:365'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $isAdmin = $request->user()->isHcmAdmin();
        if (isset($validated['userId']) && ! $isAdmin) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'AUTH_FORBIDDEN',
                    'message' => 'Only admin can create leave for other users.',
                ],
            ], 403);
        }

        $userId = (isset($validated['userId']) && $isAdmin) ? (int) $validated['userId'] : $request->user()->id;
        $user = User::query()->findOrFail($userId);

        $from = \Carbon\Carbon::parse($validated['dateFrom']);
        $to = \Carbon\Carbon::parse($validated['dateTo']);
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

        return response()->json(['success' => true, 'data' => ['id' => $r->id]], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $companyId = $this->activeCompanyId($request);
        $r = $this->applyTenantScope(LeaveRequest::query(), $companyId)->whereKey($id)->firstOrFail();

        if ($r->user_id !== $request->user()->id) {
            if (! $request->user()->isHcmAdmin()) {
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
                'notes' => ['nullable', 'string', 'max:2000'],
            ]);

            DB::transaction(function () use ($r, $validated, $companyId): void {
                $r = $this->applyTenantScope(LeaveRequest::query()->lockForUpdate(), $companyId)->whereKey($r->id)->firstOrFail();
                $fromStatus = (string) $r->status;
                $toStatus = (string) $validated['status'];

                $r->update([
                    'status' => $toStatus,
                    'notes' => $validated['notes'] ?? $r->notes,
                ]);

                if (! Schema::hasTable('leave_types') || ! Schema::hasTable('leave_ledger')) {
                    return;
                }

                if ($fromStatus !== 'approved' && $toStatus === 'approved') {
                    $this->syncApprovedLeaveBalance($r->fresh(), true);
                }

                if ($fromStatus === 'approved' && $toStatus !== 'approved') {
                    $this->syncApprovedLeaveBalance($r->fresh(), false);
                }
            });

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

    public function destroy(Request $request, int $id): JsonResponse
    {
        $companyId = $this->activeCompanyId($request);
        $r = $this->applyTenantScope(LeaveRequest::query(), $companyId)->whereKey($id)->firstOrFail();
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

    /**
     * Enabled leave type labels for request forms (any authenticated user).
     * Full settings remain admin-only at {@see \App\Http\Controllers\Api\HcmLeaveSettingController::index}.
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

    private function syncApprovedLeaveBalance(LeaveRequest $request, bool $isApproved): void
    {
        $leaveType = $this->resolveLeaveType($request->leave_type);
        if (! $leaveType || ! $leaveType->is_active || ! $leaveType->deduct_from_balance) {
            return;
        }

        $requestRefPrefix = 'leave_request:'.$request->id.':';
        $currentNet = (float) LeaveLedger::query()
            ->where('employee_id', (int) $request->user_id)
            ->where('leave_type_id', (int) $leaveType->id)
            ->where('reference_id', 'like', $requestRefPrefix.'%')
            ->sum('amount');

        $targetNet = $isApproved ? (-1 * abs((float) $request->days)) : 0.0;
        $delta = round($targetNet - $currentNet, 2);
        if (abs($delta) < 0.01) {
            return;
        }

        $policy = $this->resolvePolicyForEmployee((int) $request->user_id, (int) $leaveType->id, $request->date_from?->toDateString() ?? now()->toDateString());

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

    private function nextRequestLedgerReferenceId(int $leaveRequestId, string $event): string
    {
        $prefix = 'leave_request:'.$leaveRequestId.':'.$event;
        $count = LeaveLedger::query()
            ->where('reference_id', 'like', $prefix.'%')
            ->count();

        return $prefix.':'.($count + 1);
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
}
