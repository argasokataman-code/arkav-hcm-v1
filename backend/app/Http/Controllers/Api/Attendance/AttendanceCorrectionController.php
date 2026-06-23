<?php

namespace App\Http\Controllers\Api\Attendance;

use App\Models\AttendanceRecord;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\CompanyUser;
use App\Models\User;
use App\Notifications\AttendanceCorrectionApprovedNotification;
use App\Notifications\AttendanceCorrectionDismissedNotification;
use App\Notifications\AttendanceCorrectionRequestedNotification;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceCorrectionController extends BaseAttendanceController
{
    public function pendingCorrections(Request $request): JsonResponse
    {
        $forbidden = $this->ensureHcmAdmin($request);
        if ($forbidden) {
            return $forbidden;
        }

        $activeCompanyId = $this->activeCompanyId($request);
        if (! $activeCompanyId) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'TENANT_CONTEXT_REQUIRED', 'message' => 'Active company context required.'],
            ], 422);
        }

        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $perPage = min(500, (int) ($validated['perPage'] ?? 100));
        $tz = $this->tz();

        $paginator = AttendanceRecord::query()
            ->where('company_id', $activeCompanyId)
            ->where('correction_status', 'requested')
            ->with('user:id,name')
            ->orderBy('correction_requested_at', 'asc')
            ->orderBy('work_date', 'asc')
            ->paginate($perPage);

        $rows = collect($paginator->items())->map(function (AttendanceRecord $rec) use ($tz) {
            return [
                'recordId' => $rec->id,
                'userId' => $rec->user_id,
                'employeeName' => $rec->user?->name ?? '—',
                'workDate' => $rec->work_date->toDateString(),
                'checkIn' => $this->formatTime($rec->check_in_at),
                'checkInTime24' => $rec->check_in_at ? $rec->check_in_at->copy()->timezone($tz)->format('H:i') : '',
                'checkOut' => $this->formatTime($rec->check_out_at),
                'checkOutTime24' => $rec->check_out_at ? $rec->check_out_at->copy()->timezone($tz)->format('H:i') : '',
                'breakMinutesRaw' => (int) $rec->break_minutes,
                'lateMinutesRaw' => (int) $rec->late_minutes,
                'correctionReason' => (string) ($rec->correction_reason ?? ''),
                'correctionRequestedAt' => $rec->correction_requested_at
                    ? $rec->correction_requested_at->copy()->timezone($tz)->format('d M Y H:i')
                    : null,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => $rows,
            'meta' => [
                'total' => $paginator->total(),
                'pagination' => [
                    'page' => $paginator->currentPage(),
                    'perPage' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'totalPages' => $paginator->lastPage(),
                ],
            ],
        ]);
    }

    public function approveCorrection(Request $request): JsonResponse
    {
        $forbidden = $this->ensureHcmAdmin($request);
        if ($forbidden) {
            return $forbidden;
        }

        $validated = $request->validate(['recordId' => ['required', 'integer']]);
        $activeCompanyId = $this->activeCompanyId($request);

        if (! $activeCompanyId) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'TENANT_CONTEXT_REQUIRED', 'message' => 'Active company context required.'],
            ], 422);
        }

        $rec = AttendanceRecord::query()
            ->where('id', $validated['recordId'])
            ->where('company_id', $activeCompanyId)
            ->first();

        if (! $rec) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'NOT_FOUND', 'message' => 'Attendance record not found.'],
            ], 404);
        }

        if ((string) ($rec->correction_status ?? 'none') !== 'requested') {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'CORRECTION_NOT_PENDING', 'message' => 'No pending correction to approve.'],
            ], 422);
        }

        $tz = $this->tz();
        $rec->correction_status = 'approved';
        $rec->corrected_by_user_id = $request->user()?->id;
        $rec->corrected_at = Carbon::now($tz);
        if ((string) $rec->status === 'needs_review') {
            $rec->status = 'present';
        }
        $rec->save();

        $company = Company::query()->find($activeCompanyId);
        $companyUuid = (string) ($company?->uuid ?? '');
        $employee = User::query()->find($rec->user_id);
        $employee?->notify(new AttendanceCorrectionApprovedNotification(
            companyUuid: $companyUuid,
            workDate: $rec->work_date->toDateString(),
            attendanceRecordId: (int) $rec->id,
        ));

        return response()->json(['success' => true, 'data' => ['recordId' => $rec->id]]);
    }

    public function dismissCorrection(Request $request): JsonResponse
    {
        $forbidden = $this->ensureHcmAdmin($request);
        if ($forbidden) {
            return $forbidden;
        }

        $validated = $request->validate(['recordId' => ['required', 'integer']]);
        $activeCompanyId = $this->activeCompanyId($request);

        if (! $activeCompanyId) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'TENANT_CONTEXT_REQUIRED', 'message' => 'Active company context required.'],
            ], 422);
        }

        $rec = AttendanceRecord::query()
            ->where('id', $validated['recordId'])
            ->where('company_id', $activeCompanyId)
            ->first();

        if (! $rec) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'NOT_FOUND', 'message' => 'Attendance record not found.'],
            ], 404);
        }

        if ((string) ($rec->correction_status ?? 'none') !== 'requested') {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'CORRECTION_NOT_PENDING', 'message' => 'No pending correction to dismiss.'],
            ], 422);
        }

        $tz = $this->tz();
        $rec->correction_status = 'dismissed';
        $rec->corrected_by_user_id = $request->user()?->id;
        $rec->corrected_at = Carbon::now($tz);
        $rec->save();

        $company = Company::query()->find($activeCompanyId);
        $companyUuid = (string) ($company?->uuid ?? '');
        $employee = User::query()->find($rec->user_id);
        $employee?->notify(new AttendanceCorrectionDismissedNotification(
            companyUuid: $companyUuid,
            workDate: $rec->work_date->toDateString(),
            attendanceRecordId: (int) $rec->id,
        ));

        return response()->json(['success' => true, 'data' => ['recordId' => $rec->id]]);
    }

    public function cancelCorrection(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'workDate' => ['required', 'date'],
        ]);

        $user = $request->user();
        $tz = $this->tz();
        $activeCompanyId = $this->activeCompanyId($request);
        $workDate = Carbon::parse($validated['workDate'], $tz)->toDateString();

        $recQuery = AttendanceRecord::query();
        $this->applyTenantScope($recQuery, $activeCompanyId);
        $rec = $recQuery
            ->where('user_id', $user->id)
            ->whereDate('work_date', $workDate)
            ->first();

        if (! $rec) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ATTENDANCE_NOT_FOUND', 'message' => 'Attendance record not found for selected date.'],
            ], 404);
        }

        if ((string) ($rec->correction_status ?? 'none') !== 'requested') {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'CORRECTION_NOT_PENDING', 'message' => 'No pending correction to cancel.'],
            ], 422);
        }

        $rec->correction_status = 'none';
        $rec->correction_reason = null;
        $rec->correction_requested_at = null;
        $rec->corrected_by_user_id = null;
        $rec->corrected_at = null;
        $rec->save();

        return response()->json([
            'success' => true,
            'data' => ['correctionStatus' => $rec->correction_status],
        ]);
    }

    public function requestCorrection(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'workDate' => ['required', 'date'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $user = $request->user();
        $tz = $this->tz();
        $activeCompanyId = $this->activeCompanyId($request);
        $workDate = Carbon::parse($validated['workDate'], $tz)->toDateString();

        $windowDays = (int) (CompanySetting::query()
            ->where('company_id', $activeCompanyId)
            ->where('key', 'attendance_correction_window_days')
            ->value('value') ?? 30);

        if ($windowDays > 0) {
            $oldestAllowed = Carbon::now($tz)->subDays($windowDays)->startOfDay();
            $workDateCarbon = Carbon::parse($workDate, $tz)->startOfDay();
            if ($workDateCarbon->lt($oldestAllowed)) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'CORRECTION_WINDOW_EXCEEDED',
                        'message' => "Koreksi hanya dapat diajukan untuk absensi dalam {$windowDays} hari terakhir.",
                    ],
                ], 422);
            }
        }

        $recQuery = AttendanceRecord::query();
        $this->applyTenantScope($recQuery, $activeCompanyId);
        $rec = $recQuery
            ->where('user_id', $user->id)
            ->whereDate('work_date', $workDate)
            ->first();

        if (! $rec) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ATTENDANCE_NOT_FOUND', 'message' => 'Attendance record not found for selected date.'],
            ], 404);
        }

        if (! $rec->check_in_at || ! $rec->check_out_at) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ATTENDANCE_NOT_COMPLETE', 'message' => 'Correction can be requested after check-out is recorded.'],
            ], 422);
        }

        $existingCorrStatus = (string) ($rec->correction_status ?? 'none');
        if ($existingCorrStatus === 'requested') {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'CORRECTION_ALREADY_PENDING', 'message' => 'Koreksi untuk tanggal ini sudah diajukan dan sedang menunggu persetujuan admin.'],
            ], 422);
        }
        if ($existingCorrStatus === 'dismissed') {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'CORRECTION_ALREADY_REVIEWED', 'message' => 'Koreksi untuk tanggal ini sudah ditinjau oleh admin.'],
            ], 422);
        }

        $rec->correction_status = 'requested';
        $rec->correction_reason = trim((string) $validated['reason']);
        $rec->correction_requested_at = Carbon::now($tz);
        $rec->corrected_by_user_id = null;
        $rec->corrected_at = null;
        $rec->save();

        $company = Company::query()->find($activeCompanyId);
        $companyUuid = (string) ($company?->uuid ?? '');
        $adminUsers = CompanyUser::query()
            ->where('company_id', $activeCompanyId)
            ->whereIn('role', ['owner', 'admin', 'hcm_admin', 'super_admin'])
            ->with('user')
            ->get();

        foreach ($adminUsers as $adminUser) {
            $adminUser->user?->notify(new AttendanceCorrectionRequestedNotification(
                employeeName: (string) $user->name,
                companyUuid: $companyUuid,
                workDate: $workDate,
                reason: $rec->correction_reason,
                attendanceRecordId: (int) $rec->id,
            ));
        }

        return response()->json([
            'success' => true,
            'data' => ['correctionStatus' => $rec->correction_status],
        ]);
    }
}
