<?php

namespace App\Http\Controllers\Api\Attendance;

use App\Http\Controllers\Api\Concerns\EnsuresHcmAdmin;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\EmployeeProfile;
use App\Models\HcmResignation;
use App\Models\HcmScheduleRoster;
use App\Models\HcmScheduleTiming;
use App\Models\HcmShift;
use App\Models\HcmSmartPlannerSetting;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\Hcm\SmartAttendanceShiftingService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class HcmSmartAttendanceController extends Controller
{
    use EnsuresHcmAdmin;

    private const DEFAULT_RULES = [
        'max_work_days_per_week' => 5,
        'min_days_off_per_week' => 2,
        'min_rest_hours_between_shifts' => 12,
        'max_consecutive_night_shifts' => 3,
        'late_tolerance_minutes' => 5,
        'early_leave_tolerance_minutes' => 5,
        'overtime_threshold_minutes' => 30,
    ];

    public function __construct(private readonly SmartAttendanceShiftingService $service) {}

    public function generate(Request $request): JsonResponse
    {
        $forbidden = $this->ensureHcmAdmin($request);
        if ($forbidden) {
            return $forbidden;
        }

        $companyId = $this->activeCompanyId($request);
        $companyUuid = $this->activeCompanyUuid($request);
        if (! $companyId) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TENANT_CONTEXT_REQUIRED',
                    'message' => 'Active company context is required.',
                ],
            ], 422);
        }

        $validated = $request->validate([
            'weekStart' => ['nullable', 'date'],
            'employeeIds' => ['nullable', 'array'],
            'employeeIds.*' => ['integer'],
            'shiftCategory' => ['nullable', 'string', 'in:office_hour,shifting_24h,hybrid'],
            'rules' => ['nullable', 'array'],
            'rules.max_work_days_per_week' => ['nullable', 'integer', 'min:1', 'max:7'],
            'rules.min_days_off_per_week' => ['nullable', 'integer', 'min:0', 'max:7'],
            'rules.min_rest_hours_between_shifts' => ['nullable', 'integer', 'min:1', 'max:24'],
            'rules.max_consecutive_night_shifts' => ['nullable', 'integer', 'min:1', 'max:7'],
            'rules.illegal_transition_rules' => ['nullable', 'array'],
            'rules.illegal_transition_rules.*' => ['string'],
            'rules.late_tolerance_minutes' => ['nullable', 'integer', 'min:0', 'max:120'],
            'rules.early_leave_tolerance_minutes' => ['nullable', 'integer', 'min:0', 'max:120'],
            'rules.overtime_threshold_minutes' => ['nullable', 'integer', 'min:0', 'max:480'],
            'coverageRequirements' => ['nullable', 'array'],
            'coverageRequirements.*.date' => ['required_with:coverageRequirements', 'date'],
            'coverageRequirements.*.required' => ['required_with:coverageRequirements', 'array'],
            'coverageRequirements.*.required.*.shift_id' => ['required_with:coverageRequirements', 'string'],
            'coverageRequirements.*.required.*.headcount' => ['required_with:coverageRequirements', 'integer', 'min:0'],
        ]);

        $timezone = (string) config('app.timezone');
        $weekStart = isset($validated['weekStart'])
            ? CarbonImmutable::parse((string) $validated['weekStart'], $timezone)->startOfWeek()
            : CarbonImmutable::now($timezone)->startOfWeek();

        $rules = $this->resolveRules(
            $companyId,
            $companyUuid,
            isset($validated['rules']) && is_array($validated['rules']) ? $validated['rules'] : [],
            (string) ($validated['shiftCategory'] ?? 'office_hour')
        );

        $shiftCategory = (string) ($validated['shiftCategory'] ?? 'office_hour');

        $weekEnd = $weekStart->addDays(6);
        $employees = $this->loadEmployees($companyId, $validated['employeeIds'] ?? null, $weekStart, $weekEnd);
        if ($employees->isEmpty()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'NO_EMPLOYEE_IN_SCOPE',
                    'message' => 'No employees found in active company scope for smart scheduling.',
                ],
            ], 422);
        }

        $shifts = $this->loadShifts($companyId, $shiftCategory);

        $result = $this->service->generate(
            $companyId,
            $weekStart,
            $employees,
            $shifts,
            $rules,
            $shiftCategory,
            isset($validated['coverageRequirements']) && is_array($validated['coverageRequirements'])
                ? $validated['coverageRequirements']
                : null,
            $timezone
        );

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    public function settings(Request $request): JsonResponse
    {
        $forbidden = $this->ensureHcmAdmin($request);
        if ($forbidden) {
            return $forbidden;
        }

        $companyId = $this->activeCompanyId($request);
        $companyUuid = $this->activeCompanyUuid($request);
        if (! $companyId) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TENANT_CONTEXT_REQUIRED',
                    'message' => 'Active company context is required.',
                ],
            ], 422);
        }

        $setting = $this->findPlannerSetting($companyId, $companyUuid);

        $rules = self::DEFAULT_RULES;
        if ($setting && is_array($setting->default_rules)) {
            $rules = array_merge($rules, $setting->default_rules);
        }

        $transitions = $setting && is_array($setting->forbidden_transitions)
            ? array_values(array_unique(array_map('strval', $setting->forbidden_transitions)))
            : ['night:morning'];

        return response()->json([
            'success' => true,
            'data' => [
                'defaultRules' => $rules,
                'forbiddenTransitions' => $transitions,
                'transitionCatalog' => $this->transitionCatalog($companyId),
            ],
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $forbidden = $this->ensureHcmAdmin($request);
        if ($forbidden) {
            return $forbidden;
        }

        $companyId = $this->activeCompanyId($request);
        $companyUuid = $this->activeCompanyUuid($request);
        if (! $companyId) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TENANT_CONTEXT_REQUIRED',
                    'message' => 'Active company context is required.',
                ],
            ], 422);
        }

        $validated = $request->validate([
            'defaultRules' => ['nullable', 'array'],
            'defaultRules.max_work_days_per_week' => ['nullable', 'integer', 'min:1', 'max:7'],
            'defaultRules.min_days_off_per_week' => ['nullable', 'integer', 'min:0', 'max:7'],
            'defaultRules.min_rest_hours_between_shifts' => ['nullable', 'integer', 'min:1', 'max:24'],
            'defaultRules.max_consecutive_night_shifts' => ['nullable', 'integer', 'min:1', 'max:7'],
            'defaultRules.late_tolerance_minutes' => ['nullable', 'integer', 'min:0', 'max:120'],
            'defaultRules.early_leave_tolerance_minutes' => ['nullable', 'integer', 'min:0', 'max:120'],
            'defaultRules.overtime_threshold_minutes' => ['nullable', 'integer', 'min:0', 'max:480'],
            'forbiddenTransitions' => ['nullable', 'array'],
            'forbiddenTransitions.*' => ['string', 'regex:/^[a-z_]+:[a-z_]+$/'],
        ]);

        $rules = self::DEFAULT_RULES;
        if (isset($validated['defaultRules']) && is_array($validated['defaultRules'])) {
            $rules = array_merge($rules, $validated['defaultRules']);
        }

        $transitions = isset($validated['forbiddenTransitions']) && is_array($validated['forbiddenTransitions'])
            ? array_values(array_unique(array_map('strval', $validated['forbiddenTransitions'])))
            : ['night:morning'];

        $setting = $this->findPlannerSetting($companyId, $companyUuid);
        $payload = [
            'company_id' => $companyId,
            'company_uuid' => $companyUuid,
            'default_rules' => $rules,
            'forbidden_transitions' => $transitions,
            'updated_by_user_id' => $request->user()?->id,
            'updated_by_user_uuid' => is_string($request->user()?->uuid) ? $request->user()?->uuid : null,
        ];

        if (! $setting || ! $setting->created_by_user_id) {
            $payload['created_by_user_id'] = $request->user()?->id;
        }
        if (! $setting || ! is_string($setting->created_by_user_uuid) || trim($setting->created_by_user_uuid) === '') {
            $payload['created_by_user_uuid'] = is_string($request->user()?->uuid) ? $request->user()?->uuid : null;
        }

        if ($setting) {
            $setting->fill($payload);
            $setting->save();
        } else {
            $setting = HcmSmartPlannerSetting::query()->create($payload);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $setting->id,
                'defaultRules' => $rules,
                'forbiddenTransitions' => $transitions,
            ],
        ]);
    }

    public function publishRoster(Request $request): JsonResponse
    {
        $forbidden = $this->ensureHcmAdmin($request);
        if ($forbidden) {
            return $forbidden;
        }

        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TENANT_CONTEXT_REQUIRED',
                    'message' => 'Active company context is required.',
                ],
            ], 422);
        }

        $validated = $request->validate([
            'weeklySchedule' => ['required', 'array', 'min:1'],
            'weeklySchedule.*.employee_id' => ['required', 'integer'],
            'weeklySchedule.*.assignments' => ['required', 'array'],
            'weeklySchedule.*.assignments.*.date' => ['required', 'date'],
            'weeklySchedule.*.assignments.*.shift_id' => ['required'],
            'weeklySchedule.*.assignments.*.start_time' => ['nullable', 'date_format:H:i'],
            'weeklySchedule.*.assignments.*.end_time' => ['nullable', 'date_format:H:i'],
            'weeklySchedule.*.assignments.*.cross_day' => ['nullable', 'boolean'],
        ]);

        $weekRows = $validated['weeklySchedule'];
        $userIds = collect($weekRows)
            ->pluck('employee_id')
            ->map(fn ($id): int => (int) $id)
            ->filter(fn ($id): bool => $id > 0)
            ->unique()
            ->values();

        if ($userIds->isEmpty()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'NO_EMPLOYEE_IN_SCOPE',
                    'message' => 'No employee IDs found in weekly schedule draft.',
                ],
            ], 422);
        }

        $memberUserIds = CompanyUser::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->whereIn('user_id', $userIds)
            ->pluck('user_id')
            ->map(fn ($id): int => (int) $id)
            ->values();

        if ($memberUserIds->count() !== $userIds->count()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'USER_NOT_IN_COMPANY',
                    'message' => 'One or more users are outside active company scope.',
                ],
            ], 422);
        }

        $shiftMap = HcmShift::query()
            ->where('is_active', true)
            ->where(function ($query) use ($companyId): void {
                $query->where('company_id', $companyId)->orWhereNull('company_id');
            })
            ->get()
            ->keyBy(fn (HcmShift $shift): string => (string) $shift->id);

        $created = 0;
        $updated = 0;
        $offDays = 0;

        DB::transaction(function () use ($weekRows, $companyId, $shiftMap, $request, &$created, &$updated, &$offDays): void {
            foreach ($weekRows as $row) {
                $employeeId = (int) $row['employee_id'];
                $assignments = is_array($row['assignments']) ? $row['assignments'] : [];

                foreach ($assignments as $assignment) {
                    $date = Carbon::parse((string) $assignment['date'])->toDateString();
                    $shiftIdRaw = strtoupper(trim((string) ($assignment['shift_id'] ?? '')));
                    $isOff = $shiftIdRaw === 'OFF';

                    $hcmShiftId = null;
                    $startTime = null;
                    $endTime = null;
                    $crossDay = false;
                    $status = 'off';

                    if (! $isOff) {
                        $shift = $shiftMap->get($shiftIdRaw);
                        if (! $shift) {
                            continue;
                        }
                        $hcmShiftId = (int) $shift->id;
                        $startTime = Carbon::parse((string) $shift->start_time)->format('H:i');
                        $endTime = Carbon::parse((string) $shift->end_time)->format('H:i');
                        $crossDay = ($endTime <= $startTime);
                        $status = 'working';
                    } else {
                        $offDays++;
                    }

                    $roster = HcmScheduleRoster::query()->updateOrCreate(
                        [
                            'company_id' => $companyId,
                            'user_id' => $employeeId,
                            'work_date' => $date,
                        ],
                        [
                            'hcm_shift_id' => $hcmShiftId,
                            'start_time' => $startTime,
                            'end_time' => $endTime,
                            'cross_day' => $crossDay,
                            'roster_status' => $status,
                            'source' => 'planner',
                            'published_by_user_id' => $request->user()?->id,
                            'published_at' => now(),
                        ]
                    );

                    if ($roster->wasRecentlyCreated) {
                        $created++;
                    } else {
                        $updated++;
                    }
                }
            }
        });

        return response()->json([
            'success' => true,
            'data' => [
                'created' => $created,
                'updated' => $updated,
                'offDays' => $offDays,
                'total' => $created + $updated,
            ],
        ]);
    }

    public function rosterIndex(Request $request): JsonResponse
    {
        $forbidden = $this->ensureHcmAdmin($request);
        if ($forbidden) {
            return $forbidden;
        }

        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TENANT_CONTEXT_REQUIRED',
                    'message' => 'Active company context is required.',
                ],
            ], 422);
        }

        $validated = $request->validate([
            'dateFrom' => ['required', 'date'],
            'dateTo' => ['required', 'date', 'after_or_equal:dateFrom'],
            'userIds' => ['nullable', 'array'],
            'userIds.*' => ['integer'],
        ]);

        $query = HcmScheduleRoster::query()
            ->with(['shift:id,name,shift_type'])
            ->where('company_id', $companyId)
            ->whereDate('work_date', '>=', (string) $validated['dateFrom'])
            ->whereDate('work_date', '<=', (string) $validated['dateTo']);

        if (isset($validated['userIds']) && is_array($validated['userIds']) && ! empty($validated['userIds'])) {
            $query->whereIn('user_id', $validated['userIds']);
        }

        $rows = $query
            ->orderBy('work_date')
            ->orderBy('user_id')
            ->get()
            ->map(function (HcmScheduleRoster $row): array {
                return [
                    'id' => $row->id,
                    'userId' => (int) $row->user_id,
                    'workDate' => (string) $row->work_date?->toDateString(),
                    'shiftId' => $row->hcm_shift_id,
                    'shiftName' => $row->shift?->name,
                    'shiftType' => $row->shift?->shift_type,
                    'startTime' => $row->start_time,
                    'endTime' => $row->end_time,
                    'crossDay' => (bool) $row->cross_day,
                    'rosterStatus' => (string) $row->roster_status,
                    'source' => (string) $row->source,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $rows,
        ]);
    }

    public function simulateSwap(Request $request): JsonResponse
    {
        $forbidden = $this->ensureHcmAdmin($request);
        if ($forbidden) {
            return $forbidden;
        }

        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return response()->json(['success' => false, 'error' => ['code' => 'TENANT_CONTEXT_REQUIRED', 'message' => 'Active company context is required.']], 422);
        }

        $validated = $request->validate([
            'userAId' => ['required', 'integer'],
            'userBId' => ['required', 'integer'],
            'swapDateA' => ['required', 'date'],
            'swapDateB' => ['required', 'date'],
            'rules' => ['nullable', 'array'],
            'rules.min_rest_hours_between_shifts' => ['nullable', 'integer', 'min:1', 'max:24'],
            'rules.max_consecutive_night_shifts' => ['nullable', 'integer', 'min:1', 'max:7'],
            'rules.illegal_transition_rules' => ['nullable', 'array'],
            'rules.illegal_transition_rules.*' => ['string'],
        ]);

        $companyUuid = $this->activeCompanyUuid($request);
        $rules = $this->resolveRules($companyId, $companyUuid, $validated['rules'] ?? [], 'shifting_24h');
        $timezone = (string) config('app.timezone');

        $userAId = (int) $validated['userAId'];
        $userBId = (int) $validated['userBId'];
        $swapDateA = (string) $validated['swapDateA'];
        $swapDateB = (string) $validated['swapDateB'];

        // Determine week range covering both swap dates
        $minDate = min($swapDateA, $swapDateB);
        $maxDate = max($swapDateA, $swapDateB);
        $weekStart = CarbonImmutable::parse($minDate, $timezone)->startOfWeek();
        $weekEnd = $weekStart->addDays(13); // cover up to 2 weeks for context

        $employees = $this->loadEmployees($companyId, [$userAId, $userBId], $weekStart, $weekEnd);
        $employeeA = $employees->firstWhere('id', $userAId);
        $employeeB = $employees->firstWhere('id', $userBId);

        if (! $employeeA || ! $employeeB) {
            return response()->json(['success' => false, 'error' => ['code' => 'EMPLOYEE_NOT_FOUND', 'message' => 'One or both employees not found in active tenant.']], 422);
        }

        // Load roster assignments for both employees from schedule roster
        $rosterRows = HcmScheduleRoster::query()
            ->where('company_id', $companyId)
            ->whereIn('user_id', [$userAId, $userBId])
            ->whereDate('work_date', '>=', $weekStart->toDateString())
            ->whereDate('work_date', '<=', $weekEnd->toDateString())
            ->with('shift:id,name,start_time,end_time,shift_type')
            ->get();

        $buildAssignments = function (int $userId) use ($rosterRows): array {
            return $rosterRows
                ->where('user_id', $userId)
                ->map(function (HcmScheduleRoster $r): array {
                    $startTime = $r->start_time ? Carbon::parse((string) $r->start_time)->format('H:i') : null;
                    $endTime = $r->end_time ? Carbon::parse((string) $r->end_time)->format('H:i') : null;

                    return [
                        'date' => (string) $r->work_date?->toDateString(),
                        'shift_id' => $r->hcm_shift_id ? (string) $r->hcm_shift_id : 'OFF',
                        'start_time' => $startTime,
                        'end_time' => $endTime,
                        'cross_day' => (bool) $r->cross_day,
                    ];
                })
                ->values()
                ->all();
        };

        $assignmentsA = collect($buildAssignments($userAId));
        $assignmentsB = collect($buildAssignments($userBId));

        // Fallback: if no roster rows for a date, insert a synthetic entry from schedule timing
        foreach ([$swapDateA => $userAId, $swapDateB => $userBId] as $date => $uid) {
            $assignments = $uid === $userAId ? $assignmentsA : $assignmentsB;
            if ($assignments->firstWhere('date', $date) === null) {
                $timing = HcmScheduleTiming::query()
                    ->where('company_id', $companyId)
                    ->where('user_id', $uid)
                    ->first();
                $syntheticShift = null;
                if ($timing && $timing->hcm_shift_id) {
                    $shift = HcmShift::query()->find($timing->hcm_shift_id);
                    if ($shift) {
                        $syntheticShift = [
                            'date' => $date,
                            'shift_id' => (string) $timing->hcm_shift_id,
                            'start_time' => Carbon::parse((string) $shift->start_time)->format('H:i'),
                            'end_time' => Carbon::parse((string) $shift->end_time)->format('H:i'),
                            'cross_day' => (bool) (Carbon::parse((string) $shift->end_time)->lt(Carbon::parse((string) $shift->start_time))),
                        ];
                    }
                }
                if ($syntheticShift) {
                    if ($uid === $userAId) {
                        $assignmentsA = $assignmentsA->push($syntheticShift);
                    } else {
                        $assignmentsB = $assignmentsB->push($syntheticShift);
                    }
                }
            }
        }

        $result = $this->service->simulateSwap(
            $employeeA,
            $employeeB,
            $swapDateA,
            $swapDateB,
            $assignmentsA,
            $assignmentsB,
            $rules,
            $timezone
        );

        return response()->json(['success' => true, 'data' => $result]);
    }

    public function findReplacement(Request $request): JsonResponse
    {
        $forbidden = $this->ensureHcmAdmin($request);
        if ($forbidden) {
            return $forbidden;
        }

        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return response()->json(['success' => false, 'error' => ['code' => 'TENANT_CONTEXT_REQUIRED', 'message' => 'Active company context is required.']], 422);
        }

        $validated = $request->validate([
            'absentUserId' => ['required', 'integer'],
            'absentDates' => ['required', 'array', 'min:1'],
            'absentDates.*' => ['date'],
            'shiftId' => ['required', 'string'],
            'employeeIds' => ['nullable', 'array'],
            'employeeIds.*' => ['integer'],
            'rules' => ['nullable', 'array'],
        ]);

        $companyUuid = $this->activeCompanyUuid($request);
        $rules = $this->resolveRules($companyId, $companyUuid, $validated['rules'] ?? [], 'shifting_24h');
        $timezone = (string) config('app.timezone');

        $absentDates = array_values(array_map('strval', (array) $validated['absentDates']));
        sort($absentDates);
        $minDate = reset($absentDates);
        $maxDate = end($absentDates);

        $weekStart = CarbonImmutable::parse((string) $minDate, $timezone)->startOfWeek();
        $weekEnd = CarbonImmutable::parse((string) $maxDate, $timezone)->endOfWeek();

        $employees = $this->loadEmployees(
            $companyId,
            isset($validated['employeeIds']) && is_array($validated['employeeIds']) ? $validated['employeeIds'] : null,
            $weekStart,
            $weekEnd
        );

        if ($employees->isEmpty()) {
            return response()->json(['success' => false, 'error' => ['code' => 'NO_EMPLOYEE_IN_SCOPE', 'message' => 'No employees in scope.']], 422);
        }

        // Find the shift template
        $shiftIdRaw = (string) $validated['shiftId'];
        $shift = HcmShift::query()
            ->where('company_id', $companyId)
            ->where('id', $shiftIdRaw)
            ->first();

        if (! $shift) {
            return response()->json(['success' => false, 'error' => ['code' => 'SHIFT_NOT_FOUND', 'message' => 'Shift not found.']], 422);
        }

        $startTime = Carbon::parse((string) $shift->start_time)->format('H:i');
        $endTime = Carbon::parse((string) $shift->end_time)->format('H:i');
        $shiftTemplate = [
            'shift_id' => (string) $shift->id,
            'name' => (string) $shift->name,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'cross_day' => $endTime <= $startTime,
        ];

        // Load current roster for all employees in scope for the week
        $rosterRows = HcmScheduleRoster::query()
            ->where('company_id', $companyId)
            ->whereIn('user_id', $employees->pluck('id'))
            ->whereDate('work_date', '>=', $weekStart->toDateString())
            ->whereDate('work_date', '<=', $weekEnd->toDateString())
            ->with('shift:id,name,start_time,end_time')
            ->get();

        $rosterByUser = [];
        foreach ($rosterRows as $row) {
            $uid = (string) $row->user_id;
            $st = $row->start_time ? Carbon::parse((string) $row->start_time)->format('H:i') : null;
            $et = $row->end_time ? Carbon::parse((string) $row->end_time)->format('H:i') : null;
            $rosterByUser[$uid][] = [
                'date' => (string) $row->work_date?->toDateString(),
                'shift_id' => $row->hcm_shift_id ? (string) $row->hcm_shift_id : 'OFF',
                'start_time' => $st,
                'end_time' => $et,
                'cross_day' => (bool) $row->cross_day,
            ];
        }

        $result = $this->service->findReplacement(
            (int) $validated['absentUserId'],
            $employees,
            $rosterByUser,
            $absentDates,
            $shiftTemplate,
            $rules,
            $timezone
        );

        return response()->json(['success' => true, 'data' => $result]);
    }

    private function activeCompanyId(Request $request): ?int
    {
        $value = $request->attributes->get('activeCompanyId');

        return is_numeric($value) ? (int) $value : null;
    }

    private function activeCompanyUuid(Request $request): ?string
    {
        $value = $request->attributes->get('activeCompanyUuid');
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }

        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return null;
        }

        $uuid = Company::query()->where('id', $companyId)->value('uuid');

        return is_string($uuid) && trim($uuid) !== '' ? trim($uuid) : null;
    }

    private function findPlannerSetting(int $companyId, ?string $companyUuid): ?HcmSmartPlannerSetting
    {
        $legacySetting = HcmSmartPlannerSetting::query()
            ->where('company_id', $companyId)
            ->first();

        if (! $companyUuid) {
            return $legacySetting;
        }

        $uuidSetting = HcmSmartPlannerSetting::query()
            ->where('company_uuid', $companyUuid)
            ->first();

        return $uuidSetting ?: $legacySetting;
    }

    /**
     * @param  array<int,int>|null  $employeeIds
     * @return Collection<int,array{id:int,name:string,jobTitle:string,availability:array<string,mixed>}>
     */
    private function loadEmployees(int $companyId, ?array $employeeIds, ?CarbonImmutable $weekStart = null, ?CarbonImmutable $weekEnd = null)
    {
        $memberUserIds = CompanyUser::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->pluck('user_id')
            ->map(fn ($id): int => (int) $id)
            ->values();

        if ($memberUserIds->isEmpty()) {
            return collect();
        }

        if (is_array($employeeIds) && ! empty($employeeIds)) {
            $allowed = $memberUserIds->intersect(collect($employeeIds)->map(fn ($id): int => (int) $id));
            $memberUserIds = $allowed->values();
        }

        $users = User::query()
            ->with(['employeeProfile' => function ($query) use ($companyId): void {
                $query->where(function ($inner) use ($companyId): void {
                    $inner->where('company_id', $companyId)->orWhereNull('company_id');
                });
            }])
            ->whereIn('id', $memberUserIds)
            ->orderBy('name')
            ->get();

        // Build unavailable_dates from approved leaves + resigned employees
        $weekStartDate = $weekStart?->toDateString();
        $weekEndDate = $weekEnd?->toDateString();
        $leavesByUser = [];
        $resignedUserIds = [];

        if ($weekStartDate && $weekEndDate) {
            LeaveRequest::query()
                ->where('company_id', $companyId)
                ->where('status', 'approved')
                ->whereIn('user_id', $memberUserIds)
                ->where('date_from', '<=', $weekEndDate)
                ->where('date_to', '>=', $weekStartDate)
                ->get()
                ->each(function (LeaveRequest $lr) use (&$leavesByUser, $weekStartDate, $weekEndDate): void {
                    $uid = (int) $lr->user_id;
                    $from = max((string) $weekStartDate, (string) ($lr->date_from?->toDateString() ?? $weekStartDate));
                    $to = min((string) $weekEndDate, (string) ($lr->date_to?->toDateString() ?? $weekEndDate));
                    $d = Carbon::parse($from);
                    while ($d->toDateString() <= $to) {
                        $leavesByUser[$uid][] = $d->toDateString();
                        $d->addDay();
                    }
                });

            $resignedUserIds = HcmResignation::query()
                ->where('company_id', $companyId)
                ->whereIn('user_id', $memberUserIds)
                ->where('status', 'approved')
                ->where('resignation_date', '<=', $weekEndDate)
                ->pluck('user_id')
                ->map(fn ($id): int => (int) $id)
                ->all();
        }

        return $users->map(function (User $user) use ($leavesByUser, $resignedUserIds, $weekStartDate, $weekEndDate): array {
            /** @var EmployeeProfile|null $profile */
            $profile = $user->employeeProfile;
            $userId = (int) $user->id;

            $leaveDates = $leavesByUser[$userId] ?? [];
            $isResigned = in_array($userId, $resignedUserIds, true);

            // Resigned employees: mark the entire week as unavailable
            if ($isResigned && $weekStartDate && $weekEndDate) {
                $d = Carbon::parse($weekStartDate);
                while ($d->toDateString() <= $weekEndDate) {
                    $leaveDates[] = $d->toDateString();
                    $d->addDay();
                }
            }

            $unavailableDates = array_values(array_unique($leaveDates));

            return [
                'id' => $userId,
                'name' => (string) $user->name,
                'jobTitle' => (string) ($profile?->designation ?: 'Employee'),
                'availability' => [
                    'unavailable_dates' => $unavailableDates,
                    'preferred_shifts' => [],
                ],
                'on_leave_dates' => $unavailableDates,
                'is_resigned' => $isResigned,
            ];
        })->values();
    }

    private function loadShifts(int $companyId, string $shiftCategory)
    {
        $query = HcmShift::query()
            ->where('is_active', true)
            ->where(function ($inner) use ($companyId): void {
                $inner->where('company_id', $companyId)->orWhereNull('company_id');
            })
            ->orderBy('sort_order')
            ->orderBy('id');

        if ($shiftCategory === 'office_hour') {
            $query->whereRaw('TIME(start_time) < TIME("12:00:00")');
        }

        return $query->get();
    }

    /**
     * @param  array<string,mixed>  $runtimeRules
     * @return array<string,mixed>
     */
    private function resolveRules(int $companyId, ?string $companyUuid, array $runtimeRules, string $shiftCategory): array
    {
        $rules = self::DEFAULT_RULES;
        $setting = $this->findPlannerSetting($companyId, $companyUuid);
        if ($setting && is_array($setting->default_rules)) {
            $rules = array_merge($rules, $setting->default_rules);
        }

        if ($setting && is_array($setting->forbidden_transitions) && ! empty($setting->forbidden_transitions)) {
            $rules['illegal_transition_rules'] = $this->transitionKeysToLegacyRules($setting->forbidden_transitions);
        } else {
            $rules['illegal_transition_rules'] = ['night_to_morning'];
        }

        $rules = array_merge($rules, $runtimeRules);

        if ($shiftCategory === 'office_hour' && ! isset($runtimeRules['max_consecutive_night_shifts'])) {
            $rules['max_consecutive_night_shifts'] = 1;
        }

        return $rules;
    }

    /**
     * @param  array<int,string>  $keys
     * @return array<int,string>
     */
    private function transitionKeysToLegacyRules(array $keys): array
    {
        $rules = [];
        foreach ($keys as $key) {
            $parts = explode(':', strtolower(trim((string) $key)));
            if (count($parts) !== 2) {
                continue;
            }
            [$from, $to] = $parts;
            $rules[] = $from.'_to_'.$to;
        }

        return array_values(array_unique($rules));
    }

    /**
     * @return array<int,string>
     */
    private function transitionCatalog(int $companyId): array
    {
        $types = HcmShift::query()
            ->where('is_active', true)
            ->where(function ($query) use ($companyId): void {
                $query->where('company_id', $companyId)->orWhereNull('company_id');
            })
            ->pluck('shift_type')
            ->filter(fn ($type): bool => is_string($type) && trim($type) !== '')
            ->map(fn ($type): string => strtolower(trim((string) $type)))
            ->unique()
            ->values()
            ->all();

        if (empty($types)) {
            $types = ['morning', 'afternoon', 'night'];
        }

        $catalog = [];
        foreach ($types as $from) {
            foreach ($types as $to) {
                if ($from === $to) {
                    continue;
                }
                $catalog[] = $from.':'.$to;
            }
        }

        return $catalog;
    }
}
