<?php

namespace App\Http\Controllers\Api\Attendance;

use App\Models\AttendanceRecord;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceTimesheetController extends BaseAttendanceController
{
    private function timesheetWorkedMinutesSql(): string
    {
        return DB::connection()->getDriverName() === 'mysql'
            ? '(CASE WHEN attendance_records.check_in_at IS NOT NULL AND attendance_records.check_out_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, attendance_records.check_in_at, attendance_records.check_out_at) - COALESCE(attendance_records.break_minutes, 0) ELSE -1 END)'
            : '(CASE WHEN attendance_records.check_in_at IS NOT NULL AND attendance_records.check_out_at IS NOT NULL THEN (strftime(\'%s\', attendance_records.check_out_at) - strftime(\'%s\', attendance_records.check_in_at)) / 60.0 - COALESCE(attendance_records.break_minutes, 0) ELSE -1 END)';
    }

    private function timesheetProjectLabelSql(): string
    {
        return DB::connection()->getDriverName() === 'mysql'
            ? "TRIM(CONCAT(COALESCE(employee_profiles.team, 'General'), ' Ops'))"
            : "trim(COALESCE(employee_profiles.team, 'General') || ' Ops')";
    }

    private function timesheetProjectFilterSql(): string
    {
        return DB::connection()->getDriverName() === 'mysql'
            ? "LOWER(CONCAT(COALESCE(employee_profiles.team, 'General'), ' ops'))"
            : "lower(trim(COALESCE(employee_profiles.team, 'General') || ' ops'))";
    }

    public function timesheetsIndex(Request $request): JsonResponse
    {
        $forbidden = $this->ensureHcmAdmin($request);
        if ($forbidden) {
            return $forbidden;
        }

        $validated = $request->validate([
            'dateFrom' => ['nullable', 'date'],
            'dateTo'   => ['nullable', 'date'],
            'project'  => ['nullable', 'string', 'max:100'],
            'sort'     => ['nullable', 'string', 'in:employee_asc,employee_desc,date_desc,date_asc,worked_desc,worked_asc'],
            'page'     => ['nullable', 'integer', 'min:1'],
            'perPage'  => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $tz            = $this->tz();
        $dateTo        = $validated['dateTo'] ?? Carbon::now($tz)->toDateString();
        $dateFrom      = $validated['dateFrom'] ?? Carbon::parse($dateTo, $tz)->subDays(29)->toDateString();
        $projectFilter = strtolower(trim((string) ($validated['project'] ?? '')));
        $sort          = $validated['sort'] ?? 'date_desc';
        $perPage       = min(200, (int) ($validated['perPage'] ?? 50));

        if (Carbon::parse($dateTo, $tz)->lt(Carbon::parse($dateFrom, $tz))) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors'  => [
                    'dateTo' => ['The dateTo field must be a date after or equal to dateFrom.'],
                ],
            ], 422);
        }

        $projectLabelExpr = $this->timesheetProjectLabelSql();
        $activeCompanyId  = $this->activeCompanyId($request);

        $projects = AttendanceRecord::query()
            ->join('users', 'users.id', '=', 'attendance_records.user_id')
            ->leftJoin('employee_profiles', 'employee_profiles.user_id', '=', 'users.id')
            ->whereBetween('attendance_records.work_date', [$dateFrom, $dateTo])
            ->when($activeCompanyId, function ($q, $cid): void {
                $q->where(function ($inner) use ($cid): void {
                    $inner->where('attendance_records.company_id', $cid)->orWhereNull('attendance_records.company_id');
                });
            })
            ->selectRaw('DISTINCT ' . $projectLabelExpr . ' as project')
            ->orderBy('project')
            ->pluck('project')
            ->values();

        $workedExpr = $this->timesheetWorkedMinutesSql();

        $query = AttendanceRecord::query()
            ->join('users', 'users.id', '=', 'attendance_records.user_id')
            ->leftJoin('employee_profiles', 'employee_profiles.user_id', '=', 'users.id')
            ->whereBetween('attendance_records.work_date', [$dateFrom, $dateTo])
            ->when($activeCompanyId, function ($q, $cid): void {
                $q->where(function ($inner) use ($cid): void {
                    $inner->where('attendance_records.company_id', $cid)->orWhereNull('attendance_records.company_id');
                });
            })
            ->select('attendance_records.*');

        if ($projectFilter !== '') {
            $query->whereRaw($this->timesheetProjectFilterSql() . ' LIKE ?', ['%' . $projectFilter . '%']);
        }

        match ($sort) {
            'employee_asc'  => $query->orderBy('users.name')->orderByDesc('attendance_records.work_date')->orderBy('attendance_records.id'),
            'employee_desc' => $query->orderByDesc('users.name')->orderByDesc('attendance_records.work_date')->orderBy('attendance_records.id'),
            'date_asc'      => $query->orderBy('attendance_records.work_date')->orderBy('users.name')->orderBy('attendance_records.id'),
            'worked_desc'   => $query->orderByRaw($workedExpr . ' DESC')->orderByDesc('attendance_records.work_date')->orderBy('users.name'),
            'worked_asc'    => $query->orderByRaw($workedExpr . ' ASC')->orderByDesc('attendance_records.work_date')->orderBy('users.name'),
            default         => $query->orderByDesc('attendance_records.work_date')->orderBy('users.name')->orderBy('attendance_records.id'),
        };

        $paginator = $query
            ->with(['user:id,name', 'user.employeeProfile:id,user_id,team'])
            ->paginate($perPage);

        $mapped = $paginator->getCollection()->map(function (AttendanceRecord $rec) use ($tz) {
            $user    = $rec->user;
            $team    = (string) ($user?->employeeProfile?->team ?: 'General');
            $project = $team . ' Ops';
            $net     = $this->netProductionMinutes(
                $rec->check_in_at,
                $rec->check_out_at,
                (int) $rec->break_minutes,
                false,
            );
            $worked = $net !== null ? round($net / 60, 2) : 0.0;

            return [
                'employeeName'  => (string) ($user?->name ?: 'Unknown'),
                'date'          => $rec->work_date->toDateString(),
                'dateLabel'     => $rec->work_date->copy()->timezone($tz)->format('d M Y'),
                'project'       => $project,
                'assignedHours' => 8.0,
                'workedHours'   => $worked,
            ];
        });
        $paginator->setCollection($mapped);

        return response()->json([
            'success' => true,
            'data'    => $paginator->items(),
            'meta'    => [
                'dateFrom' => $dateFrom,
                'dateTo'   => $dateTo,
                'projects' => $projects,
                'pagination' => [
                    'page'       => $paginator->currentPage(),
                    'perPage'    => $paginator->perPage(),
                    'total'      => $paginator->total(),
                    'totalPages' => $paginator->lastPage(),
                ],
            ],
        ]);
    }
}
