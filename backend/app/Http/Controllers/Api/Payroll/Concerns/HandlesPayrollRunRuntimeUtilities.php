<?php

namespace App\Http\Controllers\Api\Payroll\Concerns;

use App\Models\HcmPayrollPeriod;
use App\Models\HcmPayrollRun;
use App\Models\HcmTermination;
use App\Models\User;
use App\Services\Reconciliation\Exceptions\ExportReconciliationException;
use App\Services\Reconciliation\ReconciliationGateService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

trait HandlesPayrollRunRuntimeUtilities
{
    private function isPrimarySuperAdminCodeOne(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        $primaryEmail = strtolower(trim((string) config('hcm.admin_email', 'qa.login@example.com')));
        $userEmail = strtolower(trim((string) ($user->email ?? '')));

        return $userEmail !== '' && $userEmail === $primaryEmail;
    }

    private function userIdentifierExists(mixed $identifier, ?int $companyId = null): bool
    {
        $query = User::query();

        if ($companyId !== null) {
            $query->where(function (Builder $q) use ($companyId): void {
                $q->whereHas('companyMemberships', fn (Builder $mem) => $mem->where('company_id', $companyId))
                  ->orWhereHas('employeeProfile', fn (Builder $prof) => $prof->where('company_id', $companyId));
            });
        }

        if ($this->isNumericUserIdentifier($identifier)) {
            return $query->whereKey((int) $identifier)->exists();
        }

        if (is_string($identifier) && Str::isUuid($identifier)) {
            return $query->where('uuid', $identifier)->exists();
        }

        return false;
    }

    /**
     * @param  array<int, mixed>  $identifiers
     * @return Collection<int, int>
     */
    private function resolveUserIdsFromIdentifiers(array $identifiers, ?int $companyId = null)
    {
        $numericIds = collect($identifiers)
            ->filter(fn (mixed $identifier): bool => $this->isNumericUserIdentifier($identifier))
            ->map(fn (mixed $identifier): int => (int) $identifier)
            ->unique()
            ->values();

        $uuids = collect($identifiers)
            ->filter(fn (mixed $identifier): bool => is_string($identifier) && Str::isUuid($identifier))
            ->map(fn (string $identifier): string => strtolower($identifier))
            ->unique()
            ->values();

        if ($numericIds->isEmpty() && $uuids->isEmpty()) {
            return collect();
        }

        $users = User::query()
            ->when($companyId !== null, fn ($q) => $q->whereHas('companyMemberships', fn (Builder $qMem) => $qMem->where('company_id', $companyId)))
            ->where(function (Builder $query) use ($numericIds, $uuids): void {
                if ($numericIds->isNotEmpty()) {
                    $query->whereIn('id', $numericIds->all());
                }

                if ($uuids->isNotEmpty()) {
                    $method = $numericIds->isNotEmpty() ? 'orWhereIn' : 'whereIn';
                    $query->{$method}('uuid', $uuids->all());
                }
            })
            ->get(['id', 'uuid']);

        $usersById = $users->keyBy(fn (User $user): string => (string) $user->id);
        $usersByUuid = $users->keyBy(fn (User $user): string => strtolower((string) $user->uuid));

        return collect($identifiers)
            ->map(function (mixed $identifier) use ($usersById, $usersByUuid): ?int {
                if ($this->isNumericUserIdentifier($identifier)) {
                    return $usersById->get((string) ((int) $identifier))?->id;
                }

                if (is_string($identifier) && Str::isUuid($identifier)) {
                    return $usersByUuid->get(strtolower($identifier))?->id;
                }

                return null;
            })
            ->filter(fn (?int $identifier): bool => $identifier !== null)
            ->map(fn (int $identifier): int => (int) $identifier)
            ->unique()
            ->values();
    }

    private function isNumericUserIdentifier(mixed $identifier): bool
    {
        return is_int($identifier)
            || (is_string($identifier) && $identifier !== '' && ctype_digit($identifier));
    }

    private function exportOnlyPayrollGatewayDisabledResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'PAYROLL_GATEWAY_DISABLED_EXPORT_ONLY',
                'message' => 'Flow payment gateway payroll dimatikan. Gunakan export payroll lalu tandai pembayaran selesai secara manual setelah settlement di luar aplikasi.',
            ],
        ], 410);
    }

    private function canUseMockCheckout(Request $request): bool
    {
        if ($this->isNgrokRuntime($request)) {
            return false;
        }

        if ($this->shouldForceLocalMockCheckout($request)) {
            return true;
        }

        return (bool) config('app.mock_payments_enabled');
    }

    private function shouldForceLocalMockCheckout(Request $request): bool
    {
        return app()->environment(['local', 'testing']) && ! $this->isNgrokRuntime($request);
    }

    private function isNgrokRuntime(Request $request): bool
    {
        $hosts = [];

        $requestHost = strtolower((string) $request->getHost());
        if ($requestHost !== '') {
            $hosts[] = $requestHost;
        }

        $forwardedHost = strtolower(trim((string) $request->header('X-Forwarded-Host', '')));
        if ($forwardedHost !== '') {
            foreach (explode(',', $forwardedHost) as $host) {
                $host = trim($host);
                if ($host !== '') {
                    $hosts[] = $host;
                }
            }
        }

        $appUrlHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
        if ($appUrlHost !== '') {
            $hosts[] = $appUrlHost;
        }

        foreach ($hosts as $host) {
            if (str_contains($host, 'ngrok')) {
                return true;
            }
        }

        return false;
    }

    /**
     * M4 — Guard: block finalizing a monthly payroll run when there are approved
     * terminations whose termination_date falls on/before this period's end and
     * they still have no settlement_payroll_period_id linked.
     *
     * This forces the HR flow to link/finalize the settlement period for any
     * approved terminations before locking the monthly run for that period.
     */
    private function unsettledTerminationBlocker(HcmPayrollRun $run, ?int $companyId): ?JsonResponse
    {
        $period = HcmPayrollPeriod::query()->find($run->hcm_payroll_period_id);
        if ($period === null) {
            return null;
        }

        $year = (int) $period->period_year;
        $month = (int) $period->period_month;
        if ($year <= 0 || $month <= 0) {
            return null;
        }

        $periodEnd = Carbon::createFromDate($year, $month, 1)->endOfMonth()->toDateString();

        $query = HcmTermination::query()
            ->where('status', 'approved')
            ->whereNull('settlement_payroll_period_id')
            ->whereDate('termination_date', '<=', $periodEnd);

        $scopeCompanyId = $companyId ?? $run->company_id;
        if ($scopeCompanyId !== null) {
            $query->where(function ($query) use ($scopeCompanyId): void {
                $query->where('company_id', $scopeCompanyId)->orWhereNull('company_id');
            });
        }

        $pendingCount = (int) $query->count();
        if ($pendingCount === 0) {
            return null;
        }

        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'PAYROLL_UNSETTLED_TERMINATIONS',
                'message' => 'Cannot finalize monthly payroll: there are approved terminations without a linked settlement period.',
                'meta' => [
                    'pendingCount' => $pendingCount,
                    'periodEnd' => $periodEnd,
                ],
            ],
        ], 422);
    }

    private function applyTenantScope(Builder $query, ?int $companyId): Builder
    {
        if ($companyId === null) {
            return $query;
        }

        return $query->where(function ($query) use ($companyId): void {
            $query->where('company_id', $companyId)->orWhereNull('company_id');
        });
    }

    private function guardPayrollReconciliation(Request $request, HcmPayrollRun $run, string $action): ?JsonResponse
    {
        if (! (bool) config('hcm.export_reconciliation.enabled', true)) {
            return null;
        }

        if (! (bool) config(sprintf('hcm.export_reconciliation.enforce.payroll_run.%s', $action), true)) {
            return null;
        }

        $reconciliation = $request->input('reconciliation', []);
        $filterPayload = is_array($reconciliation['filterPayload'] ?? null) ? $reconciliation['filterPayload'] : [];
        $datasetChecksum = isset($reconciliation['datasetChecksum']) ? (string) $reconciliation['datasetChecksum'] : null;
        $strictChecksum = (bool) ($reconciliation['strictChecksum'] ?? config('hcm.export_reconciliation.strict_checksum', false));

        try {
            app(ReconciliationGateService::class)->assertCanProceed(
                $this->activeCompanyId($request),
                'payroll_run',
                $action,
                (string) $run->id,
                $filterPayload,
                $datasetChecksum,
                $strictChecksum,
            );
        } catch (ExportReconciliationException $exception) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => $exception->errorCode(),
                    'message' => $exception->getMessage(),
                ],
            ], $exception->status());
        }

        return null;
    }
}
