<?php

namespace App\Support;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\User;
use Illuminate\Http\Request;

final class TenantContextResolver
{
    public function resolve(Request $request, User $user): array
    {
        $requestedCompanyId = $this->requestedCompanyId($request);
        $requestedCompanyUuid = $this->requestedCompanyUuid($request);
        $requestedCompanyCode = $this->requestedCompanyCode($request);

        $membershipQuery = CompanyUser::query()
            ->with('company')
            ->where('user_id', $user->id)
            ->where('status', 'active');

        if ($requestedCompanyId !== null) {
            $membership = (clone $membershipQuery)
                ->where('company_id', $requestedCompanyId)
                ->first();

            if (! $membership) {
                return ['error' => 'TENANT_FORBIDDEN'];
            }

            return ['membership' => $membership, 'company' => $membership->company];
        }

        if ($requestedCompanyUuid !== null) {
            $company = Company::query()->where('uuid', $requestedCompanyUuid)->first();
            if (! $company) {
                return ['error' => 'TENANT_FORBIDDEN'];
            }

            $membership = (clone $membershipQuery)
                ->where('company_id', $company->id)
                ->first();

            if (! $membership) {
                return ['error' => 'TENANT_FORBIDDEN'];
            }

            return ['membership' => $membership, 'company' => $company];
        }

        if ($requestedCompanyCode !== null) {
            $company = Company::query()->where('code', $requestedCompanyCode)->first();
            if (! $company) {
                return ['error' => 'TENANT_FORBIDDEN'];
            }

            $membership = (clone $membershipQuery)
                ->where('company_id', $company->id)
                ->first();

            if (! $membership) {
                return ['error' => 'TENANT_FORBIDDEN'];
            }

            return ['membership' => $membership, 'company' => $company];
        }

        $membership = $membershipQuery
            ->orderBy('company_id')
            ->first();

        if (! $membership) {
            return ['error' => 'TENANT_MEMBERSHIP_REQUIRED'];
        }

        return ['membership' => $membership, 'company' => $membership->company];
    }

    private function requestedCompanyId(Request $request): ?int
    {
        $raw = $request->header('X-Company-Id');
        if ($raw === null || $raw === '') {
            return null;
        }

        return ctype_digit((string) $raw) ? (int) $raw : null;
    }

    private function requestedCompanyCode(Request $request): ?string
    {
        $raw = trim((string) $request->header('X-Company-Code', ''));

        return $raw !== '' ? $raw : null;
    }

    private function requestedCompanyUuid(Request $request): ?string
    {
        $raw = trim((string) $request->header('X-Company-UUID', ''));

        return $raw !== '' ? $raw : null;
    }
}
