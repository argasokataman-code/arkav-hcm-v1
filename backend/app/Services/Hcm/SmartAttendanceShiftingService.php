<?php

namespace App\Services\Hcm;

use App\Models\AttendanceRecord;
use App\Models\HcmShift;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class SmartAttendanceShiftingService
{
    /**
     * @param  Collection<int, array{id:int,name:string,jobTitle:string,availability:array<string,mixed>}>  $employees
     * @param  Collection<int, HcmShift>  $shifts
     * @param  array<string,mixed>  $rules
     * @param  array<string,mixed>|null  $coverageInput
     * @return array<string,mixed>
     */
    public function generate(
        int $companyId,
        CarbonImmutable $weekStart,
        Collection $employees,
        Collection $shifts,
        array $rules,
        string $shiftCategory,
        ?array $coverageInput,
        string $timezone
    ): array {
        $weekDates = collect(range(0, 6))->map(fn (int $i): string => $weekStart->addDays($i)->toDateString());

        $templates = $this->normalizeShiftTemplates($shifts);
        if ($templates->isEmpty()) {
            $templates = collect([
                [
                    'shift_id' => 'office_default',
                    'name' => 'Office Hour',
                    'start_time' => '09:00',
                    'end_time' => '18:00',
                    'cross_day' => false,
                    'is_night' => false,
                    'duration_minutes' => 9 * 60,
                ],
            ]);
        }

        $coverage = $this->buildCoverageRequirements(
            $weekDates,
            $templates,
            $employees->count(),
            $shiftCategory,
            (int) ($rules['max_work_days_per_week'] ?? 5),
            $coverageInput
        );
        $historyNightCount = $this->nightHistoryCount($companyId, $employees->pluck('id')->all(), $weekStart, $timezone);

        $state = [];
        foreach ($employees as $employee) {
            $empId = (int) $employee['id'];
            $state[$empId] = [
                'assignments' => [],
                'work_days' => 0,
                'work_streak' => 0,
                'max_work_streak' => 0,
                'night_streak' => 0,
                'max_night_streak' => 0,
                'night_count' => (int) ($historyNightCount[$empId] ?? 0),
                'shift_counts' => [],
                'same_shift_streak' => 0,
                'short_rest_events' => 0,
                'backward_rotation_events' => 0,
                'last_shift_end' => null,
                'last_shift_night' => false,
                'last_shift_id' => null,
                'last_shift_type' => null,
            ];
        }

        $violations = [];
        $unmetCoverage = [];

        foreach ($weekDates as $date) {
            $assignedToday = [];
            $coverageRows = $coverage[$date] ?? [];
            foreach ($coverageRows as $coverageRow) {
                $shift = $templates->firstWhere('shift_id', (string) ($coverageRow['shift_id'] ?? ''));
                if (! $shift) {
                    continue;
                }

                $required = max(0, (int) ($coverageRow['headcount'] ?? 0));
                if ($required <= 0) {
                    continue;
                }

                for ($slot = 0; $slot < $required; $slot++) {
                    $candidate = $this->pickBestCandidate(
                        $employees,
                        $state,
                        $assignedToday,
                        $date,
                        $shift,
                        $rules,
                        $timezone
                    );

                    if (! $candidate) {
                        $unmetCoverage[] = [
                            'date' => $date,
                            'shift_id' => $shift['shift_id'],
                            'required' => $required,
                            'assigned' => $slot,
                        ];

                        $violations[] = [
                            'code' => 'COVERAGE_UNMET',
                            'message' => 'Coverage requirement could not be fully assigned.',
                            'date' => $date,
                            'severity' => 'high',
                        ];
                        break;
                    }

                    $empId = (int) $candidate['id'];
                    $this->applyAssignmentState($state[$empId], $date, $shift, $timezone);
                    $assignedToday[$empId] = true;
                }
            }

            // Employees not assigned are marked as OFF for clarity in output.
            foreach ($employees as $employee) {
                $empId = (int) $employee['id'];
                if (! isset($assignedToday[$empId])) {
                    $state[$empId]['assignments'][] = [
                        'date' => $date,
                        'shift_id' => 'OFF',
                        'start_time' => null,
                        'end_time' => null,
                        'cross_day' => false,
                        'notes' => 'Day off',
                    ];
                    $state[$empId]['night_streak'] = 0;
                    $state[$empId]['work_streak'] = 0;
                    $state[$empId]['last_shift_night'] = false;
                    $state[$empId]['last_shift_id'] = 'OFF';
                    $state[$empId]['last_shift_type'] = null;
                    $state[$empId]['same_shift_streak'] = 0;
                }
            }
        }

        foreach ($employees as $employee) {
            $empId = (int) $employee['id'];
            $minDaysOff = max(0, (int) ($rules['min_days_off_per_week'] ?? 2));
            $offDays = max(0, 7 - (int) $state[$empId]['work_days']);
            if ($offDays < $minDaysOff) {
                $violations[] = [
                    'code' => 'MIN_DAYS_OFF_VIOLATION',
                    'message' => 'Minimum days off requirement is not met.',
                    'employee_id' => (string) $empId,
                    'severity' => 'high',
                ];
            }
        }

        $attendance = $this->analyzeAttendance(
            $companyId,
            $employees,
            collect($state),
            $timezone,
            (int) ($rules['late_tolerance_minutes'] ?? 5),
            (int) ($rules['early_leave_tolerance_minutes'] ?? 5),
            (int) ($rules['overtime_threshold_minutes'] ?? 30)
        );

        $scheduleRows = $employees->map(function (array $employee) use ($state): array {
            $empId = (int) $employee['id'];

            return [
                'employee_id' => (string) $empId,
                'employee_name' => (string) $employee['name'],
                'assignments' => $state[$empId]['assignments'],
            ];
        })->values()->all();

        $fairnessScore = $this->calculateFairnessScore(collect($state));
        $fatigueRiskScore = $this->calculateFatigueRiskScore(collect($state), $attendance['employee_summaries']);
        $recommendations = $this->buildRecommendations($fairnessScore, $fatigueRiskScore, $violations, $attendance['flags'], $employees->count(), $unmetCoverage);

        return [
            'schedule_generation' => [
                'validation_status' => empty($violations) ? 'valid' : 'invalid',
                'weekly_schedule' => $scheduleRows,
                'violations' => $violations,
                'unmet_coverage' => $unmetCoverage,
            ],
            'attendance_analysis' => $attendance,
            'recommendation' => [
                'fairness_score' => $fairnessScore,
                'fatigue_risk_score' => $fatigueRiskScore,
                'improvement_suggestions' => $recommendations,
            ],
            'explanation' => $this->buildExplanation($violations, $fairnessScore, $fatigueRiskScore),
        ];
    }

    /**
     * @param  Collection<int,HcmShift>  $shifts
     * @return Collection<int,array<string,mixed>>
     */
    private function normalizeShiftTemplates(Collection $shifts): Collection
    {
        return $shifts->map(function (HcmShift $shift): array {
            $start = Carbon::parse((string) $shift->start_time);
            $end = Carbon::parse((string) $shift->end_time);
            $crossDay = $end->lessThan($start);
            $duration = $crossDay
                ? (24 * 60 - $start->hour * 60 - $start->minute) + ($end->hour * 60 + $end->minute)
                : (($end->hour * 60 + $end->minute) - ($start->hour * 60 + $start->minute));

            $shiftType = strtolower(trim((string) ($shift->shift_type ?? '')));
            $isNight = $shiftType === 'night' || $crossDay || $start->hour >= 20 || $start->hour < 5;

            return [
                'shift_id' => (string) $shift->id,
                'name' => (string) $shift->name,
                'start_time' => $start->format('H:i'),
                'end_time' => $end->format('H:i'),
                'shift_type' => $shiftType ?: 'custom',
                'cross_day' => $crossDay,
                'is_night' => $isNight,
                'duration_minutes' => max(1, $duration),
            ];
        })->values();
    }

    /**
     * @param  Collection<int,string>  $weekDates
     * @param  Collection<int,array<string,mixed>>  $templates
     * @return array<string,array<int,array<string,mixed>>>
     */
    private function buildCoverageRequirements(
        Collection $weekDates,
        Collection $templates,
        int $employeeCount,
        string $shiftCategory,
        int $maxWorkDaysPerWeek,
        ?array $coverageInput
    ): array {
        $coverage = [];
        $templateIds = $templates->pluck('shift_id')->values();
        $maxWorkDays = max(1, min(7, $maxWorkDaysPerWeek));
        $effectiveDailyNeed = max(1, (int) ceil(max(1, $employeeCount) * ($maxWorkDays / 7)));

        foreach ($weekDates as $date) {
            $coverage[$date] = $this->defaultCoverageRows(
                $templates,
                $templateIds,
                $effectiveDailyNeed,
                $shiftCategory
            );
        }

        if (! is_array($coverageInput)) {
            return $coverage;
        }

        foreach ($coverageInput as $row) {
            if (! is_array($row)) {
                continue;
            }
            $date = (string) ($row['date'] ?? '');
            $required = $row['required'] ?? null;
            if ($date === '' || ! isset($coverage[$date]) || ! is_array($required)) {
                continue;
            }

            $normalized = [];
            foreach ($required as $req) {
                if (! is_array($req)) {
                    continue;
                }
                $shiftId = (string) ($req['shift_id'] ?? $req['shiftId'] ?? '');
                $headcount = max(0, (int) ($req['headcount'] ?? 0));
                if ($shiftId === '') {
                    continue;
                }
                $normalized[] = [
                    'shift_id' => $shiftId,
                    'headcount' => $headcount,
                ];
            }

            if (! empty($normalized)) {
                $coverage[$date] = $normalized;
            }
        }

        return $coverage;
    }

    /**
     * @param  Collection<int,array<string,mixed>>  $templates
     * @param  Collection<int,string>  $templateIds
     * @return array<int,array{shift_id:string,headcount:int}>
     */
    private function defaultCoverageRows(
        Collection $templates,
        Collection $templateIds,
        int $effectiveDailyNeed,
        string $shiftCategory
    ): array {
        $mode = in_array($shiftCategory, ['office_hour', 'shifting_24h', 'hybrid'], true)
            ? $shiftCategory
            : 'hybrid';

        if ($mode === 'office_hour') {
            $dayShift = $templates->first(fn (array $t): bool => ! (bool) ($t['is_night'] ?? false));
            $pickedShiftId = (string) (($dayShift['shift_id'] ?? null) ?: ($templateIds->first() ?: 'office_default'));

            return [[
                'shift_id' => $pickedShiftId,
                'headcount' => max(1, $effectiveDailyNeed),
            ]];
        }

        $ids = $templateIds->all();
        $shiftCount = max(1, count($ids));
        $base = (int) floor($effectiveDailyNeed / $shiftCount);
        $remainder = $effectiveDailyNeed % $shiftCount;

        $rows = [];
        foreach ($ids as $index => $shiftId) {
            $rows[] = [
                'shift_id' => (string) $shiftId,
                'headcount' => max(0, $base + ($index < $remainder ? 1 : 0)),
            ];
        }

        if ($mode === 'shifting_24h') {
            // Keep at least one staff on each shift when possible for 24h operations.
            $nonZeroRows = array_filter($rows, fn (array $r): bool => (int) $r['headcount'] > 0);
            if (count($nonZeroRows) < $shiftCount && $effectiveDailyNeed >= $shiftCount) {
                foreach ($rows as $i => $row) {
                    if ($rows[$i]['headcount'] === 0) {
                        $rows[$i]['headcount'] = 1;
                    }
                }
            }
        }

        return $rows;
    }

    /**
     * @param  int[]  $employeeIds
     * @return array<int,int>
     */
    private function nightHistoryCount(int $companyId, array $employeeIds, CarbonImmutable $weekStart, string $timezone): array
    {
        if (empty($employeeIds)) {
            return [];
        }

        $from = $weekStart->subDays(7)->toDateString();
        $to = $weekStart->subDay()->toDateString();

        $records = AttendanceRecord::query()
            ->where('company_id', $companyId)
            ->whereIn('user_id', $employeeIds)
            ->whereBetween('work_date', [$from, $to])
            ->whereNotNull('check_in_at')
            ->get();

        $counts = [];
        foreach ($records as $record) {
            if (! $record->check_in_at) {
                continue;
            }
            $hour = (int) $record->check_in_at->copy()->timezone($timezone)->format('H');
            if ($hour >= 20 || $hour < 5) {
                $uid = (int) $record->user_id;
                $counts[$uid] = (int) ($counts[$uid] ?? 0) + 1;
            }
        }

        return $counts;
    }

    /**
     * @param  Collection<int,array{id:int,name:string,jobTitle:string,availability:array<string,mixed>}>  $employees
     * @param  array<int,array<string,mixed>>  $state
     * @param  array<int,bool>  $assignedToday
     * @param  array<string,mixed>  $shift
     * @param  array<string,mixed>  $rules
     */
    private function pickBestCandidate(
        Collection $employees,
        array $state,
        array $assignedToday,
        string $date,
        array $shift,
        array $rules,
        string $timezone
    ): ?array {
        $candidates = [];
        foreach ($employees as $employee) {
            $empId = (int) $employee['id'];
            if (isset($assignedToday[$empId])) {
                continue;
            }
            if (! $this->isEmployeeAvailable($employee, $date)) {
                continue;
            }
            if (! $this->isValidAssignment($state[$empId], $date, $shift, $rules, $timezone)) {
                continue;
            }

            $candidates[] = [
                'employee' => $employee,
                'work_days' => (int) $state[$empId]['work_days'],
                'work_streak' => (int) ($state[$empId]['work_streak'] ?? 0),
                'night_count' => (int) $state[$empId]['night_count'],
                'night_streak' => (int) $state[$empId]['night_streak'],
                'target_shift_count' => (int) ($state[$empId]['shift_counts'][$shift['shift_id']] ?? 0),
                'same_shift_streak' => (int) ($state[$empId]['same_shift_streak'] ?? 0),
                'short_rest_events' => (int) ($state[$empId]['short_rest_events'] ?? 0),
                'backward_rotation_events' => (int) ($state[$empId]['backward_rotation_events'] ?? 0),
                'last_shift_match' => (string) ($state[$empId]['last_shift_id'] ?? '') === (string) ($shift['shift_id'] ?? ''),
            ];
        }

        if (empty($candidates)) {
            return null;
        }

        usort($candidates, function (array $a, array $b) use ($shift, $date): int {
            // Prioritize fairness for night shifts, otherwise balance total workload first.
            if ((bool) ($shift['is_night'] ?? false)) {
                if ($a['night_count'] !== $b['night_count']) {
                    return $a['night_count'] <=> $b['night_count'];
                }
            }

            if ($a['work_days'] !== $b['work_days']) {
                return $a['work_days'] <=> $b['work_days'];
            }

            // Favor candidates with healthier spread of consecutive work days.
            if ($a['work_streak'] !== $b['work_streak']) {
                return $a['work_streak'] <=> $b['work_streak'];
            }

            // Spread each employee across available shifts to avoid repetitive, cloned patterns.
            if ($a['target_shift_count'] !== $b['target_shift_count']) {
                return $a['target_shift_count'] <=> $b['target_shift_count'];
            }

            // Prefer candidates with fewer historical risky rotation and short-rest events.
            if ($a['backward_rotation_events'] !== $b['backward_rotation_events']) {
                return $a['backward_rotation_events'] <=> $b['backward_rotation_events'];
            }

            if ($a['short_rest_events'] !== $b['short_rest_events']) {
                return $a['short_rest_events'] <=> $b['short_rest_events'];
            }

            if ((int) $a['last_shift_match'] !== (int) $b['last_shift_match']) {
                return ((int) $a['last_shift_match']) <=> ((int) $b['last_shift_match']);
            }

            if ($a['same_shift_streak'] !== $b['same_shift_streak']) {
                return $a['same_shift_streak'] <=> $b['same_shift_streak'];
            }

            if ($a['night_streak'] !== $b['night_streak']) {
                return $a['night_streak'] <=> $b['night_streak'];
            }

            // Break perfect ties deterministically so assignment does not lock to the same employees pattern.
            $shiftId = (string) ($shift['shift_id'] ?? '');
            $aId = (string) ($a['employee']['id'] ?? '');
            $bId = (string) ($b['employee']['id'] ?? '');
            $aSeed = sprintf('%s|%s|%s', $date, $shiftId, $aId);
            $bSeed = sprintf('%s|%s|%s', $date, $shiftId, $bId);
            $aRank = (int) sprintf('%u', crc32($aSeed));
            $bRank = (int) sprintf('%u', crc32($bSeed));

            return $aRank <=> $bRank;
        });

        return $candidates[0]['employee'] ?? null;
    }

    /** @param array<string,mixed> $employee */
    private function isEmployeeAvailable(array $employee, string $date): bool
    {
        $availability = $employee['availability'] ?? [];
        if (! is_array($availability)) {
            return true;
        }

        $unavailable = $availability['unavailable_dates'] ?? [];
        if (! is_array($unavailable)) {
            return true;
        }

        return ! in_array($date, $unavailable, true);
    }

    /**
     * @param  array<string,mixed>  $state
     * @param  array<string,mixed>  $shift
     * @param  array<string,mixed>  $rules
     */
    private function isValidAssignment(array $state, string $date, array $shift, array $rules, string $timezone): bool
    {
        $maxWorkDays = max(1, (int) ($rules['max_work_days_per_week'] ?? 5));
        if ((int) $state['work_days'] >= $maxWorkDays) {
            return false;
        }

        $maxConsecutiveWorkDays = (int) ($rules['max_consecutive_work_days'] ?? min(5, $maxWorkDays));
        $maxConsecutiveWorkDays = max(1, min(7, $maxConsecutiveWorkDays));
        if ((int) ($state['work_streak'] ?? 0) >= $maxConsecutiveWorkDays) {
            return false;
        }

        $maxConsecutiveNight = max(1, (int) ($rules['max_consecutive_night_shifts'] ?? 3));
        if ((bool) $shift['is_night'] && (int) $state['night_streak'] >= $maxConsecutiveNight) {
            return false;
        }

        if ($this->isIllegalTransition($state, $shift, $rules)) {
            return false;
        }

        $lastShiftEnd = $state['last_shift_end'];
        if ($lastShiftEnd instanceof CarbonImmutable) {
            $start = CarbonImmutable::parse($date.' '.$shift['start_time'], $timezone);
            $restHours = $lastShiftEnd->diffInHours($start, false);
            $requiredRest = max(1, (int) ($rules['min_rest_hours_between_shifts'] ?? 12));
            if ($restHours < $requiredRest) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string,mixed>  $state
     * @param  array<string,mixed>  $shift
     * @param  array<string,mixed>  $rules
     */
    private function isIllegalTransition(array $state, array $shift, array $rules): bool
    {
        $fromType = strtolower(trim((string) ($state['last_shift_type'] ?? '')));
        $toType = strtolower(trim((string) ($shift['shift_type'] ?? '')));

        if ($fromType === '' || $toType === '') {
            return false;
        }

        $illegal = $rules['illegal_transition_rules'] ?? ['night_to_morning'];
        if (! is_array($illegal) || empty($illegal)) {
            return false;
        }

        $transitionSet = [];
        foreach ($illegal as $rule) {
            $normalized = strtolower(trim((string) $rule));
            if ($normalized === '') {
                continue;
            }
            // Backward compatible format: night_to_morning.
            $normalized = str_replace(':', '_to_', $normalized);
            if (str_contains($normalized, '_to_')) {
                $transitionSet[$normalized] = true;
            }
        }

        return isset($transitionSet[$fromType.'_to_'.$toType]);
    }

    /**
     * @param  array<string,mixed>  $state
     * @param  array<string,mixed>  $shift
     */
    private function applyAssignmentState(array &$state, string $date, array $shift, string $timezone): void
    {
        $lastShiftType = strtolower(trim((string) ($state['last_shift_type'] ?? '')));
        $currentShiftType = strtolower(trim((string) ($shift['shift_type'] ?? '')));
        $state['assignments'][] = [
            'date' => $date,
            'shift_id' => $shift['shift_id'],
            'start_time' => $shift['start_time'],
            'end_time' => $shift['end_time'],
            'cross_day' => (bool) $shift['cross_day'],
        ];

        $state['work_days'] = (int) $state['work_days'] + 1;
        $state['work_streak'] = (int) ($state['work_streak'] ?? 0) + 1;
        $state['max_work_streak'] = max((int) ($state['max_work_streak'] ?? 0), (int) $state['work_streak']);
        $isNight = (bool) $shift['is_night'];
        $shiftId = (string) $shift['shift_id'];
        $state['shift_counts'][$shiftId] = (int) ($state['shift_counts'][$shiftId] ?? 0) + 1;
        $state['night_count'] = (int) $state['night_count'] + ($isNight ? 1 : 0);
        $state['night_streak'] = $isNight ? (int) $state['night_streak'] + 1 : 0;
        $state['max_night_streak'] = max((int) $state['max_night_streak'], (int) $state['night_streak']);
        $state['same_shift_streak'] = ((string) ($state['last_shift_id'] ?? '') === $shiftId)
            ? (int) $state['same_shift_streak'] + 1
            : 1;

        $shiftEnd = CarbonImmutable::parse($date.' '.$shift['end_time'], $timezone);
        if ((bool) $shift['cross_day']) {
            $shiftEnd = $shiftEnd->addDay();
        }

        $lastShiftEnd = $state['last_shift_end'] ?? null;
        if ($lastShiftEnd instanceof CarbonImmutable) {
            $currentShiftStart = CarbonImmutable::parse($date.' '.$shift['start_time'], $timezone);
            $restHours = $lastShiftEnd->diffInHours($currentShiftStart, false);
            if ($restHours < 11) {
                $state['short_rest_events'] = (int) ($state['short_rest_events'] ?? 0) + 1;
            }
        }

        if ($this->isBackwardRotation($lastShiftType, $currentShiftType)) {
            $state['backward_rotation_events'] = (int) ($state['backward_rotation_events'] ?? 0) + 1;
        }

        $state['last_shift_end'] = $shiftEnd;
        $state['last_shift_night'] = $isNight;
        $state['last_shift_id'] = $shiftId;
        $state['last_shift_type'] = $currentShiftType !== '' ? $currentShiftType : null;
    }

    private function isBackwardRotation(string $fromType, string $toType): bool
    {
        if ($fromType === '' || $toType === '') {
            return false;
        }

        $rank = [
            'morning' => 1,
            'afternoon' => 2,
            'night' => 3,
        ];

        if (! isset($rank[$fromType], $rank[$toType])) {
            return false;
        }

        return $rank[$toType] < $rank[$fromType];
    }

    /**
     * @param  Collection<int,array{id:int,name:string,jobTitle:string,availability:array<string,mixed>}>  $employees
     * @param  Collection<int,array<string,mixed>>  $state
     * @return array<string,mixed>
     */
    private function analyzeAttendance(
        int $companyId,
        Collection $employees,
        Collection $state,
        string $timezone,
        int $lateTolerance,
        int $earlyLeaveTolerance,
        int $overtimeThresholdMinutes
    ): array {
        $employeeIds = $employees->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $allDates = $state
            ->flatMap(fn (array $emp): array => array_map(fn (array $a): string => (string) ($a['date'] ?? ''), $emp['assignments']))
            ->filter(fn (string $d): bool => $d !== '')
            ->unique()
            ->values();

        if (empty($employeeIds) || $allDates->isEmpty()) {
            return [
                'employee_summaries' => [],
                'flags' => [],
            ];
        }

        $records = AttendanceRecord::query()
            ->where('company_id', $companyId)
            ->whereIn('user_id', $employeeIds)
            ->whereIn('work_date', $allDates->all())
            ->get()
            ->groupBy(fn (AttendanceRecord $r): string => (int) $r->user_id.'|'.$r->work_date->toDateString());

        $summaries = [];
        $flags = [];

        foreach ($employees as $employee) {
            $empId = (int) $employee['id'];
            /** @var array<string,mixed> $empState */
            $empState = $state->get($empId, ['assignments' => []]);
            $summary = [
                'employee_id' => (string) $empId,
                'total_work_days' => 0,
                'late_count' => 0,
                'early_leave_count' => 0,
                'absent_count' => 0,
                'overtime_minutes' => 0,
                'compliance_score' => 100.0,
            ];

            foreach (($empState['assignments'] ?? []) as $assignment) {
                if (! is_array($assignment) || (string) ($assignment['shift_id'] ?? '') === 'OFF') {
                    continue;
                }

                $summary['total_work_days']++;
                $date = (string) ($assignment['date'] ?? '');
                $key = $empId.'|'.$date;
                /** @var AttendanceRecord|null $record */
                $record = $records->get($key)?->first();

                if (! $record || ! $record->check_in_at) {
                    $summary['absent_count']++;
                    $flags[] = [
                        'employee_id' => (string) $empId,
                        'date' => $date,
                        'status' => 'absent',
                    ];

                    continue;
                }

                $expectedStart = CarbonImmutable::parse($date.' '.(string) $assignment['start_time'], $timezone);
                $expectedEnd = CarbonImmutable::parse($date.' '.(string) $assignment['end_time'], $timezone);
                if ((bool) ($assignment['cross_day'] ?? false)) {
                    $expectedEnd = $expectedEnd->addDay();
                }

                $checkIn = $record->check_in_at->copy()->timezone($timezone);
                $checkOut = $record->check_out_at ? $record->check_out_at->copy()->timezone($timezone) : null;

                $lateMinutes = max(0, $expectedStart->diffInMinutes($checkIn, false));
                if ($lateMinutes > $lateTolerance) {
                    $summary['late_count']++;
                    $flags[] = [
                        'employee_id' => (string) $empId,
                        'date' => $date,
                        'status' => 'late',
                        'minutes' => $lateMinutes,
                    ];
                }

                if ($checkOut) {
                    $earlyLeaveMinutes = max(0, $checkOut->diffInMinutes($expectedEnd, false));
                    if ($earlyLeaveMinutes > $earlyLeaveTolerance) {
                        $summary['early_leave_count']++;
                        $flags[] = [
                            'employee_id' => (string) $empId,
                            'date' => $date,
                            'status' => 'early_leave',
                            'minutes' => $earlyLeaveMinutes,
                        ];
                    }

                    $actualWorked = max(0, $checkIn->diffInMinutes($checkOut) - (int) ($record->break_minutes ?? 0));
                    $scheduledMinutes = $expectedStart->diffInMinutes($expectedEnd);
                    $overtime = max(0, $actualWorked - $scheduledMinutes);
                    if ($overtime > $overtimeThresholdMinutes) {
                        $summary['overtime_minutes'] += $overtime;
                        $flags[] = [
                            'employee_id' => (string) $empId,
                            'date' => $date,
                            'status' => 'overtime',
                            'minutes' => $overtime,
                        ];
                    }
                }
            }

            $penalty =
                $summary['late_count'] * 5 +
                $summary['early_leave_count'] * 6 +
                $summary['absent_count'] * 15 +
                ((int) floor(((int) $summary['overtime_minutes']) / 120) * 2);

            $summary['compliance_score'] = (float) max(0, 100 - $penalty);
            $summaries[] = $summary;
        }

        return [
            'employee_summaries' => $summaries,
            'flags' => $flags,
        ];
    }

    /** @param Collection<int,array<string,mixed>> $state */
    private function calculateFairnessScore(Collection $state): float
    {
        if ($state->isEmpty()) {
            return 100.0;
        }

        // Night shift fairness is weighted highest by policy to protect employee wellbeing.
        $nightPenalty = $this->spreadPenalty(
            $state->map(fn (array $s): int => max(0, (int) $s['night_count']))->values(),
            16.0
        );
        $workloadPenalty = $this->spreadPenalty(
            $state->map(fn (array $s): int => max(0, (int) $s['work_days']))->values(),
            12.0
        );
        $rotationPenalty = $this->spreadPenalty(
            $state->map(fn (array $s): int => max(0, (int) ($s['backward_rotation_events'] ?? 0)))->values(),
            10.0
        );

        $weightedPenalty =
            ($nightPenalty * 0.70) +
            ($workloadPenalty * 0.20) +
            ($rotationPenalty * 0.10);

        return (float) max(0, min(100, 100.0 - $weightedPenalty));
    }

    /** @param Collection<int,int> $values */
    private function spreadPenalty(Collection $values, float $multiplier): float
    {
        if ($values->isEmpty()) {
            return 0.0;
        }

        $avg = (float) ($values->avg() ?: 0.0);
        $variance = $values->reduce(function (float $carry, int $v) use ($avg): float {
            $d = $v - $avg;

            return $carry + ($d * $d);
        }, 0.0) / max(1, $values->count());

        return sqrt($variance) * $multiplier;
    }

    /**
     * @param  Collection<int,array<string,mixed>>  $state
     * @param  array<int,array<string,mixed>>  $employeeSummaries
     */
    private function calculateFatigueRiskScore(Collection $state, array $employeeSummaries): float
    {
        $overtimeMinutes = (int) collect($employeeSummaries)->sum(fn (array $s): int => (int) ($s['overtime_minutes'] ?? 0));
        $avgNightStreak = (float) $state->avg(fn (array $s): float => (float) ($s['max_night_streak'] ?? 0));
        $avgWorkStreak = (float) $state->avg(fn (array $s): float => (float) ($s['max_work_streak'] ?? 0));
        $shortRestEvents = (int) $state->sum(fn (array $s): int => (int) ($s['short_rest_events'] ?? 0));
        $backwardRotations = (int) $state->sum(fn (array $s): int => (int) ($s['backward_rotation_events'] ?? 0));

        $risk =
            min(24, $overtimeMinutes / 60.0 * 1.4) +
            min(30, max(0, $avgNightStreak - 1.0) * 9.5) +
            min(20, max(0, $avgWorkStreak - 4.0) * 7.0) +
            min(15, $shortRestEvents * 3.0) +
            min(10, $backwardRotations * 1.2);

        return (float) max(0, min(100, $risk));
    }

    /**
     * @param  array<int,array<string,mixed>>  $violations
     * @param  array<int,array<string,mixed>>  $flags
     * @return array<int,array<string,mixed>>
     */
    private function buildRecommendations(float $fairnessScore, float $fatigueRiskScore, array $violations, array $flags, int $employeeCount = 0, array $unmetCoverage = []): array
    {
        $suggestions = [];

        if ($fairnessScore < 80) {
            $suggestions[] = [
                'title' => 'Rebalance night shift distribution',
                'reason' => 'Night shifts are unevenly distributed across employees.',
                'expected_impact' => 'Improve fairness score and reduce long-term burnout probability.',
                'priority' => 'high',
                'data' => [
                    'fairness_score' => round($fairnessScore, 1),
                    'employee_count' => $employeeCount,
                ],
            ];
        }

        if ($fatigueRiskScore >= 70) {
            $suggestions[] = [
                'title' => 'Reduce consecutive heavy patterns',
                'reason' => 'Fatigue risk is elevated from overtime and/or night streak concentration.',
                'expected_impact' => 'Reduce fatigue risk and attendance violations in the next cycle.',
                'priority' => 'high',
                'data' => [
                    'fatigue_risk_score' => round($fatigueRiskScore, 1),
                    'employee_count' => $employeeCount,
                ],
            ];
        }

        if (! empty($violations)) {
            // Hitung kebutuhan karyawan minimum dari unmet_coverage
            $unmetByDate = collect($unmetCoverage)->groupBy('date');
            $maxUnmetPerDay = (int) ($unmetByDate->map(
                fn ($entries) => $entries->sum(fn ($e) => max(0, (int) $e['required'] - (int) $e['assigned']))
            )->max() ?? 0);
            $minEmployeesNeeded = $maxUnmetPerDay > 0 ? $employeeCount + $maxUnmetPerDay : null;
            $coverageViolationCount = collect($violations)->where('code', 'COVERAGE_UNMET')->count();

            $suggestions[] = [
                'title' => 'Resolve schedule rule violations first',
                'reason' => 'Current plan still has rule/coverage violations.',
                'expected_impact' => 'Move schedule status from invalid to valid and avoid operational risk.',
                'priority' => 'high',
                'data' => [
                    'violation_count' => count($violations),
                    'coverage_violation_count' => $coverageViolationCount,
                    'other_violation_count' => count($violations) - $coverageViolationCount,
                    'employee_count' => $employeeCount,
                    'min_employees_needed' => $minEmployeesNeeded,
                    'unmet_slots' => count($unmetCoverage),
                ],
            ];
        }

        $absentCount = collect($flags)->where('status', 'absent')->count();
        if ($absentCount > 0) {
            $suggestions[] = [
                'title' => 'Trigger attendance recovery workflow',
                'reason' => 'Absence flags were detected on assigned work days.',
                'expected_impact' => 'Improve compliance and shift continuity.',
                'priority' => 'medium',
                'data' => [
                    'absent_count' => $absentCount,
                    'employee_count' => $employeeCount,
                ],
            ];
        }

        if (empty($suggestions)) {
            $suggestions[] = [
                'title' => 'Maintain current schedule pattern with weekly monitoring',
                'reason' => 'No critical fairness/fatigue/rule issue detected.',
                'expected_impact' => 'Preserve schedule stability while keeping alertness through monitoring.',
                'priority' => 'low',
                'data' => [
                    'fairness_score' => round($fairnessScore, 1),
                    'fatigue_risk_score' => round($fatigueRiskScore, 1),
                    'employee_count' => $employeeCount,
                ],
            ];
        }

        return $suggestions;
    }

    /** @param array<int,array<string,mixed>> $violations */
    private function buildExplanation(array $violations, float $fairnessScore, float $fatigueRiskScore): string
    {
        if (empty($violations)) {
            return sprintf(
                'Schedule generated successfully with no hard-rule violations. Fairness score %.1f and fatigue risk score %.1f were computed from shift balance, overtime trend, and night-streak pattern.',
                $fairnessScore,
                $fatigueRiskScore
            );
        }

        return sprintf(
            'Schedule generated with %d violation(s). Prioritize resolving coverage/rest/transition issues before publishing. Current fairness score is %.1f and fatigue risk score is %.1f.',
            count($violations),
            $fairnessScore,
            $fatigueRiskScore
        );
    }

    /**
     * Simulate swapping shifts between two employees on given dates.
     * Returns risk assessment (fatigue, rest gap, illegal transition) for both before and after swap.
     *
     * @param  array<string,mixed>  $employeeA
     * @param  array<string,mixed>  $employeeB
     * @param  Collection<int,array<string,mixed>>  $assignmentsA  Current weekly assignments for employee A
     * @param  Collection<int,array<string,mixed>>  $assignmentsB  Current weekly assignments for employee B
     * @return array<string,mixed>
     */
    public function simulateSwap(
        array $employeeA,
        array $employeeB,
        string $swapDateA,
        string $swapDateB,
        Collection $assignmentsA,
        Collection $assignmentsB,
        array $rules,
        string $timezone
    ): array {
        $minRest = max(1, (int) ($rules['min_rest_hours_between_shifts'] ?? 12));
        $maxNightStreak = max(1, (int) ($rules['max_consecutive_night_shifts'] ?? 3));
        $illegalRules = is_array($rules['illegal_transition_rules'] ?? null)
            ? $rules['illegal_transition_rules']
            : ['night_to_morning'];

        // Snapshot the shift on the swap date for each employee
        $shiftA = $assignmentsA->firstWhere('date', $swapDateA);
        $shiftB = $assignmentsB->firstWhere('date', $swapDateB);

        if (! $shiftA || ! $shiftB) {
            return [
                'swappable' => false,
                'reason' => 'Tidak ada jadwal shift yang terdaftar untuk salah satu atau kedua karyawan pada tanggal yang diminta.',
            ];
        }

        // Build modified assignment lists (swap the two target dates)
        $modifiedA = $assignmentsA->map(function (array $a) use ($swapDateA, $shiftB): array {
            if ($a['date'] === $swapDateA) {
                return array_merge($a, [
                    'shift_id' => $shiftB['shift_id'],
                    'start_time' => $shiftB['start_time'],
                    'end_time' => $shiftB['end_time'],
                    'cross_day' => $shiftB['cross_day'] ?? false,
                ]);
            }

            return $a;
        })->sortBy('date')->values();

        $modifiedB = $assignmentsB->map(function (array $b) use ($swapDateB, $shiftA): array {
            if ($b['date'] === $swapDateB) {
                return array_merge($b, [
                    'shift_id' => $shiftA['shift_id'],
                    'start_time' => $shiftA['start_time'],
                    'end_time' => $shiftA['end_time'],
                    'cross_day' => $shiftA['cross_day'] ?? false,
                ]);
            }

            return $b;
        })->sortBy('date')->values();

        $riskA = $this->assessSwapRisk($modifiedA, $minRest, $maxNightStreak, $illegalRules, $timezone);
        $riskB = $this->assessSwapRisk($modifiedB, $minRest, $maxNightStreak, $illegalRules, $timezone);

        $overallRisk = max($riskA['risk_level'], $riskB['risk_level']);
        $swappable = $overallRisk < 2; // 0=safe, 1=warning, 2=danger

        $employeeAName = (string) ($employeeA['name'] ?? 'Karyawan A');
        $employeeBName = (string) ($employeeB['name'] ?? 'Karyawan B');

        $shiftALabel = $shiftA['shift_id'] === 'OFF' ? 'Libur' : (string) ($shiftA['shift_id']);
        $shiftBLabel = $shiftB['shift_id'] === 'OFF' ? 'Libur' : (string) ($shiftB['shift_id']);

        $warnings = array_merge(
            array_map(fn (string $w): string => $employeeAName.': '.$w, $riskA['warnings']),
            array_map(fn (string $w): string => $employeeBName.': '.$w, $riskB['warnings'])
        );

        return [
            'swappable' => $swappable,
            'overall_risk_level' => $overallRisk,
            'swap_summary' => sprintf(
                '%s (%s, %s) ↔ %s (%s, %s)',
                $employeeAName, $swapDateA, $shiftALabel,
                $employeeBName, $swapDateB, $shiftBLabel
            ),
            'employee_a' => [
                'id' => $employeeA['id'],
                'name' => $employeeAName,
                'original_shift' => $shiftALabel,
                'new_shift' => $shiftBLabel,
                'risk_level' => $riskA['risk_level'],
                'warnings' => $riskA['warnings'],
            ],
            'employee_b' => [
                'id' => $employeeB['id'],
                'name' => $employeeBName,
                'original_shift' => $shiftBLabel,
                'new_shift' => $shiftALabel,
                'risk_level' => $riskB['risk_level'],
                'warnings' => $riskB['warnings'],
            ],
            'warnings' => $warnings,
            'advice' => $this->buildSwapAdvice($swappable, $overallRisk, $employeeAName, $employeeBName, $warnings),
        ];
    }

    /**
     * @param  Collection<int,array<string,mixed>>  $assignments  Sorted by date
     * @param  array<string>  $illegalRules
     * @return array{risk_level:int,warnings:array<string>}
     */
    private function assessSwapRisk(
        Collection $assignments,
        int $minRest,
        int $maxNightStreak,
        array $illegalRules,
        string $timezone
    ): array {
        $warnings = [];
        $riskLevel = 0;
        $nightStreak = 0;

        $sorted = $assignments->filter(fn (array $a): bool => ($a['shift_id'] ?? 'OFF') !== 'OFF')
            ->sortBy('date')
            ->values();

        foreach ($sorted as $i => $cur) {
            $isNight = $this->isNightShift($cur);
            $nightStreak = $isNight ? $nightStreak + 1 : 0;

            if ($nightStreak > $maxNightStreak) {
                $warnings[] = sprintf(
                    'Shift malam berturut-turut %d hari (maks. %d) mulai %s — risiko kelelahan tinggi.',
                    $nightStreak, $maxNightStreak, $cur['date']
                );
                $riskLevel = max($riskLevel, 2);
            }

            if ($i === 0) {
                continue;
            }

            $prev = $sorted[$i - 1];
            $prevEnd = $this->shiftEndDateTime((string) $prev['date'], (string) ($prev['end_time'] ?? ''), (bool) ($prev['cross_day'] ?? false), $timezone);
            $curStart = CarbonImmutable::parse((string) $cur['date'].' '.(string) ($cur['start_time'] ?? '00:00'), $timezone);

            if ($prevEnd && $curStart) {
                $restHours = $prevEnd->diffInMinutes($curStart, false) / 60;
                if ($restHours >= 0 && $restHours < $minRest) {
                    $warnings[] = sprintf(
                        'Jeda istirahat hanya %.1f jam antara %s dan %s (minimum %d jam) — berisiko kelelahan.',
                        $restHours, $prev['date'], $cur['date'], $minRest
                    );
                    $riskLevel = max($riskLevel, 2);
                }
            }

            // Check illegal transitions
            $prevType = $this->shiftTypeLabel($prev);
            $curType = $this->shiftTypeLabel($cur);
            if ($prevType && $curType) {
                $key = $prevType.'_to_'.$curType;
                if (in_array($key, $illegalRules, true)) {
                    $warnings[] = sprintf(
                        'Urutan shift dilarang: %s → %s pada %s → %s.',
                        strtoupper($prevType), strtoupper($curType), $prev['date'], $cur['date']
                    );
                    $riskLevel = max($riskLevel, 2);
                }
            }
        }

        // Count night shifts overall for fatigue warning
        $totalNight = $sorted->filter(fn (array $a): bool => $this->isNightShift($a))->count();
        if ($totalNight >= 4 && $riskLevel < 1) {
            $warnings[] = sprintf(
                '%d shift malam dalam seminggu — pertimbangkan rotasi agar tidak melebihi ambang kelelahan.',
                $totalNight
            );
            $riskLevel = max($riskLevel, 1);
        }

        return ['risk_level' => $riskLevel, 'warnings' => $warnings];
    }

    /** @param array<string,mixed> $assignment */
    private function isNightShift(array $assignment): bool
    {
        $startTime = (string) ($assignment['start_time'] ?? '');
        if ($startTime === '') {
            return false;
        }
        $h = (int) substr($startTime, 0, 2);

        return $h >= 20 || $h < 5;
    }

    /** @param array<string,mixed> $assignment */
    private function shiftTypeLabel(array $assignment): string
    {
        $startTime = (string) ($assignment['start_time'] ?? '');
        if ($startTime === '') {
            return '';
        }
        $h = (int) substr($startTime, 0, 2);
        if ($h >= 20 || $h < 5) {
            return 'night';
        }
        if ($h >= 14) {
            return 'afternoon';
        }

        return 'morning';
    }

    private function shiftEndDateTime(string $date, string $endTime, bool $crossDay, string $timezone): ?CarbonImmutable
    {
        if ($endTime === '') {
            return null;
        }
        $end = CarbonImmutable::parse($date.' '.$endTime, $timezone);
        if ($crossDay) {
            $end = $end->addDay();
        }

        return $end;
    }

    /**
     * @param  array<string>  $warnings
     */
    private function buildSwapAdvice(bool $swappable, int $riskLevel, string $nameA, string $nameB, array $warnings): string
    {
        if ($swappable && $riskLevel === 0) {
            return sprintf(
                'Tukar jadwal antara %s dan %s aman dilakukan. Tidak ada pelanggaran aturan istirahat, transisi shift, atau risiko kelelahan yang terdeteksi.',
                $nameA, $nameB
            );
        }
        if ($swappable && $riskLevel === 1) {
            return sprintf(
                'Tukar jadwal antara %s dan %s bisa dilakukan dengan catatan: %s. Pantau kehadiran pada hari setelah swap.',
                $nameA, $nameB, implode(' ', $warnings)
            );
        }

        return sprintf(
            'Tukar jadwal antara %s dan %s TIDAK DISARANKAN karena: %s. Pertimbangkan pengganti lain atau ubah tanggal swap.',
            $nameA, $nameB, implode(' ', $warnings)
        );
    }

    /**
     * Find the best replacement candidates for an absent employee on given dates.
     *
     * @param  Collection<int,array<string,mixed>>  $employees  All employees in scope
     * @param  array<string,array<string,mixed>>  $rosterByUser  userId => array of assignments for the week
     * @param  array<string>  $absentDates
     * @param  array<string,mixed>  $shiftTemplate  The shift that needs to be covered
     * @return array<string,mixed>
     */
    public function findReplacement(
        int $absentUserId,
        Collection $employees,
        array $rosterByUser,
        array $absentDates,
        array $shiftTemplate,
        array $rules,
        string $timezone
    ): array {
        $minRest = max(1, (int) ($rules['min_rest_hours_between_shifts'] ?? 12));
        $maxWorkDays = max(1, (int) ($rules['max_work_days_per_week'] ?? 5));
        $maxNightStreak = max(1, (int) ($rules['max_consecutive_night_shifts'] ?? 3));
        $illegalRules = is_array($rules['illegal_transition_rules'] ?? null)
            ? $rules['illegal_transition_rules']
            : ['night_to_morning'];

        $candidates = [];

        foreach ($employees as $employee) {
            $empId = (int) $employee['id'];
            if ($empId === $absentUserId) {
                continue;
            }

            $myAssignments = collect($rosterByUser[(string) $empId] ?? [])
                ->filter(fn (array $a): bool => ($a['shift_id'] ?? 'OFF') !== 'OFF')
                ->sortBy('date')
                ->values();

            $currentWorkDays = $myAssignments->count();
            if ($currentWorkDays >= $maxWorkDays) {
                // Already at max work days — skip
                continue;
            }

            // Simulate adding the absent dates
            $issues = [];
            $canCover = true;

            foreach ($absentDates as $date) {
                // Check rest gap with previous assignment
                $prevAssignment = $myAssignments->filter(fn (array $a): bool => $a['date'] < $date)->last();
                if ($prevAssignment) {
                    $prevEnd = $this->shiftEndDateTime(
                        (string) $prevAssignment['date'],
                        (string) ($prevAssignment['end_time'] ?? ''),
                        (bool) ($prevAssignment['cross_day'] ?? false),
                        $timezone
                    );
                    $newStart = CarbonImmutable::parse($date.' '.(string) ($shiftTemplate['start_time'] ?? '00:00'), $timezone);
                    if ($prevEnd && $newStart) {
                        $restHours = $prevEnd->diffInMinutes($newStart, false) / 60;
                        if ($restHours >= 0 && $restHours < $minRest) {
                            $issues[] = sprintf(
                                'Jeda istirahat hanya %.1f jam sebelum %s (minimum %d jam)',
                                $restHours, $date, $minRest
                            );
                            $canCover = false;
                        }
                    }

                    // Illegal transition
                    $prevType = $this->shiftTypeLabel($prevAssignment);
                    $newType = $this->shiftTypeLabel($shiftTemplate);
                    if ($prevType && $newType) {
                        $key = $prevType.'_to_'.$newType;
                        if (in_array($key, $illegalRules, true)) {
                            $issues[] = sprintf('Transisi shift %s → %s dilarang', strtoupper($prevType), strtoupper($newType));
                            $canCover = false;
                        }
                    }
                }

                // Check night streak
                if ($this->isNightShift($shiftTemplate)) {
                    $recentNights = $myAssignments->filter(fn (array $a): bool => $a['date'] < $date && $this->isNightShift($a))->count();
                    if ($recentNights >= $maxNightStreak) {
                        $issues[] = sprintf('Sudah %d shift malam berturut-turut (maks. %d)', $recentNights, $maxNightStreak);
                        $canCover = false;
                    }
                }
            }

            if (! $canCover) {
                continue;
            }

            // Score: lower is better candidate
            $nightCount = $myAssignments->filter(fn (array $a): bool => $this->isNightShift($a))->count();
            $score = $currentWorkDays * 10 + $nightCount * 3;

            $candidates[] = [
                'employee_id' => (string) $empId,
                'employee_name' => (string) ($employee['name'] ?? ''),
                'job_title' => (string) ($employee['jobTitle'] ?? 'Employee'),
                'current_work_days' => $currentWorkDays,
                'current_night_shifts' => $nightCount,
                'available_capacity' => $maxWorkDays - $currentWorkDays,
                'issues' => $issues,
                'score' => $score,
            ];
        }

        usort($candidates, fn (array $a, array $b): int => $a['score'] <=> $b['score']);
        $top = array_slice($candidates, 0, 5);

        return [
            'absent_dates' => $absentDates,
            'shift_to_cover' => $shiftTemplate['shift_id'] ?? 'unknown',
            'candidates_found' => count($top),
            'candidates' => array_map(function (array $c): array {
                $reason = sprintf(
                    'Beban kerja minggu ini: %d hari kerja, %d shift malam. Kapasitas tersisa: %d hari. %s',
                    $c['current_work_days'],
                    $c['current_night_shifts'],
                    $c['available_capacity'],
                    empty($c['issues']) ? 'Tidak ada konflik aturan yang terdeteksi.' : implode('; ', $c['issues'])
                );

                return [
                    'employee_id' => $c['employee_id'],
                    'employee_name' => $c['employee_name'],
                    'job_title' => $c['job_title'],
                    'current_work_days' => $c['current_work_days'],
                    'available_capacity' => $c['available_capacity'],
                    'reason' => $reason,
                ];
            }, $top),
            'message' => empty($top)
                ? 'Tidak ada karyawan yang tersedia dan memenuhi syarat aturan untuk menggantikan di tanggal tersebut. Pertimbangkan menambah karyawan ke scope atau melonggarkan aturan Max Work Days.'
                : sprintf('%d karyawan dapat menggantikan berdasarkan analisis beban kerja dan aturan jadwal minggu ini.', count($top)),
        ];
    }
}
