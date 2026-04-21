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

        // Global Super Admin (developer / platform maintainer) can access any
        // company without requiring an explicit `company_users` membership.
        // We synthesize a virtual membership so downstream middleware + scopes
        // still work, but with unrestricted reach across tenants.
        if ($user->isGlobalHcmAdmin()) {
            $resolved = $this->resolveForGlobalAdmin(
                $user,
                $requestedCompanyId,
                $requestedCompanyUuid,
                $requestedCompanyCode,
            );

            if ($resolved !== null) {
                return $resolved;
            }
        }

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

    /**
     * Resolve tenant context for a Global Super Admin (developer / platform
     * maintainer). Global admins are not bound to `company_users` membership,
     * so they can target any company by header, fall back to any existing
     * active membership, or ultimately to the first company in the system.
     */
    private function resolveForGlobalAdmin(
        User $user,
        ?int $requestedCompanyId,
        ?string $requestedCompanyUuid,
        ?string $requestedCompanyCode,
    ): ?array {
        $company = null;

        if ($requestedCompanyId !== null) {
            $company = Company::query()->find($requestedCompanyId);
        } elseif ($requestedCompanyUuid !== null) {
            $company = Company::query()->where('uuid', $requestedCompanyUuid)->first();
        } elseif ($requestedCompanyCode !== null) {
            $company = Company::query()->where('code', $requestedCompanyCode)->first();
        }

        if ($company === null && $requestedCompanyId === null
            && $requestedCompanyUuid === null && $requestedCompanyCode === null) {
            $membership = CompanyUser::query()
                ->with('company')
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->orderBy('company_id')
                ->first();

            if ($membership && $membership->company) {
                return ['membership' => $membership, 'company' => $membership->company];
            }

            $company = Company::query()->orderBy('id')->first();
        }

        if ($company === null) {
            return ['error' => 'TENANT_FORBIDDEN'];
        }

        $membership = CompanyUser::query()
            ->with('company')
            ->where('user_id', $user->id)
            ->where('company_id', $company->id)
            ->where('status', 'active')
            ->first();

        if ($membership) {
            return ['membership' => $membership, 'company' => $company];
        }

        // Synthesize a virtual membership for the global admin so downstream
        // middleware that reads membership->role does not explode. This is an
        // in-memory object, NOT persisted to `company_users`.
        $virtualMembership = new CompanyUser([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'role' => 'super_admin',
            'status' => 'active',
        ]);
        $virtualMembership->setRelation('company', $company);

        return ['membership' => $virtualMembership, 'company' => $company];
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
