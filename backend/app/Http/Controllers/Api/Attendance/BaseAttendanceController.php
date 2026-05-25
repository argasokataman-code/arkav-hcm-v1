<?php

namespace App\Http\Controllers\Api\Attendance;

use App\Http\Controllers\Api\Concerns\ChecksPermissions;
use App\Http\Controllers\Api\Concerns\EnsuresHcmAdmin;
use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\Company;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

abstract class BaseAttendanceController extends Controller
{
    use ChecksPermissions;
    use EnsuresHcmAdmin;

    protected const TARGET_DAILY_MINUTES = 8 * 60;
    protected const OVERTIME_THRESHOLD_MINUTES = 8 * 60;
    protected const EARLY_PUNCH_OUT_REVIEW_MINUTES = 4 * 60;
    protected const SELFIE_MAX_BYTES = 5 * 1024 * 1024;

    /** @var array<string, string> */
    protected const SELFIE_ALLOWED_MIME_TO_EXT = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    protected function tz(): string
    {
        $fallback = (string) config('app.timezone', 'UTC');

        $request = request();
        if ($request instanceof Request) {
            $activeCompany = $request->attributes->get('activeCompany');
            $companyTimezone = is_object($activeCompany)
                ? trim((string) ($activeCompany->timezone ?? ''))
                : '';

            if ($this->isValidTimezone($companyTimezone)) {
                return $companyTimezone;
            }

            $activeCompanyId = $request->attributes->get('activeCompanyId');
            if (is_numeric($activeCompanyId) && (int) $activeCompanyId > 0) {
                $resolvedTimezone = Company::query()
                    ->where('id', (int) $activeCompanyId)
                    ->value('timezone');

                if ($this->isValidTimezone(is_string($resolvedTimezone) ? trim($resolvedTimezone) : null)) {
                    return trim((string) $resolvedTimezone);
                }
            }
        }

        return $this->isValidTimezone($fallback) ? $fallback : 'UTC';
    }

    protected function isValidTimezone(?string $timezone): bool
    {
        if (! is_string($timezone) || trim($timezone) === '') {
            return false;
        }

        try {
            new \DateTimeZone($timezone);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    protected function activeCompanyId(Request $request): ?int
    {
        $value = $request->attributes->get('activeCompanyId');

        return is_numeric($value) ? (int) $value : null;
    }

    protected function applyTenantScope(Builder $query, ?int $companyId): Builder
    {
        if (! $companyId) {
            return $query;
        }

        return $query->where(function (Builder $inner) use ($companyId): void {
            $inner->where('company_id', $companyId)->orWhereNull('company_id');
        });
    }

    protected function expectedCheckIn(string $dateYmd): Carbon
    {
        return Carbon::parse($dateYmd . ' 09:00:00', $this->tz());
    }

    protected function netProductionMinutes(
        ?Carbon $in,
        ?Carbon $out,
        int $breakMinutes,
        bool $useNowForOpenShift,
    ): ?int {
        if (! $in) {
            return null;
        }
        $end = $out;
        if (! $end && $useNowForOpenShift) {
            $end = Carbon::now($this->tz());
        }
        if (! $end) {
            return null;
        }
        $mins = $in->diffInMinutes($end);

        return max(0, $mins - $breakMinutes);
    }

    /**
     * @return array{label: string, hours: float|null, badge: string}
     */
    protected function formatProduction(?int $netMinutes): array
    {
        if ($netMinutes === null) {
            return ['label' => '-', 'hours' => null, 'badge' => 'secondary'];
        }
        $hrs = round($netMinutes / 60, 2);
        $label = sprintf('%.2f Hrs', $hrs);
        $badge = $netMinutes >= static::TARGET_DAILY_MINUTES ? 'success' : 'danger';

        return ['label' => $label, 'hours' => $hrs, 'badge' => $badge];
    }

    protected function formatTime(?Carbon $dt): string
    {
        if (! $dt) {
            return '-';
        }

        return $dt->copy()->timezone($this->tz())->format('h:i A');
    }

    /**
     * @return array{0: int|null, 1: string, 2: string}
     */
    protected function overtimeForDisplay(?int $netMinutes): array
    {
        if ($netMinutes === null) {
            return [null, '-', 'secondary'];
        }
        $ot = max(0, $netMinutes - static::OVERTIME_THRESHOLD_MINUTES);
        if ($ot <= 0) {
            return [0, '-', 'secondary'];
        }

        return [$ot, $ot . ' Min', 'success'];
    }

    /**
     * Determine whether an employee is still within the correction submission window.
     */
    protected function isCorrectionEligible(AttendanceRecord $rec, string $statusLabel, int $windowDays, string $tz): bool
    {
        if ($statusLabel !== 'Needs Review') {
            return false;
        }
        $corrStatus = (string) ($rec->correction_status ?? 'none');
        if (in_array($corrStatus, ['requested', 'approved', 'dismissed'], true)) {
            return false;
        }
        if ($windowDays <= 0) {
            return true;
        }
        $oldestAllowed = Carbon::now($tz)->subDays($windowDays)->startOfDay();

        return Carbon::parse($rec->work_date->toDateString(), $tz)->startOfDay()->gte($oldestAllowed);
    }

    protected function userBelongsToActiveCompany(int $userId, ?int $companyId): bool
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

    protected function userNotInCompanyResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code'    => 'USER_NOT_IN_COMPANY',
                'message' => 'User not found in active company context.',
            ],
        ], 404);
    }

    protected function applyIdentifierScope(Builder $query, string $identifier, bool $hasUuidColumn): Builder
    {
        if ($hasUuidColumn && Str::isUuid($identifier)) {
            return $query->where('uuid', $identifier);
        }

        if (ctype_digit($identifier)) {
            return $query->whereKey((int) $identifier);
        }

        return $query->whereRaw('1 = 0');
    }
}
