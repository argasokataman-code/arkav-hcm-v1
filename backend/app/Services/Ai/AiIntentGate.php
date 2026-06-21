<?php

namespace App\Services\Ai;

use App\Models\User;

/**
 * RBAC gate for AI intents.
 *
 * Mirrors the allow/deny matrix defined in:
 *   docs/features/ai-assistant/RBAC-POLICY.md
 *
 * Rules:
 * - Deny by default: any intent not in the registry is denied.
 * - cross-user intents (*.other) require admin role.
 * - cross-tenant intents (saas.*) require global admin role.
 * - self intents only need the user to be authenticated.
 */
class AiIntentGate
{
    /**
     * Intents available to any authenticated user (employee self-service).
     *
     * @var array<string>
     */
    private const SELF_INTENTS = [
        'leave.balance.self',
        'leave.history.self',
        'attendance.today.self',
        'attendance.history.self',
        'payslip.latest.self',
        'payslip.history.self',
        'ticket.status.self',
        'ticket.list.self',
        'profile.info.self',
        'subscription.features.current',
    ];

    /**
     * Intents available to HCM Admin (tenant-scoped) or Global Admin.
     *
     * @var array<string>
     */
    private const ADMIN_INTENTS = [
        'leave.balance.other',
        'leave.history.other',
        'leave.summary.company',
        'attendance.summary.company',
        'payroll.run.status',
        'payroll.run.summary',
        'ticket.list.all',
        'employee.list.company',
        'department.info',
        'general.fallback.company',
    ];

    /**
     * Intents available to Global Admin only.
     *
     * @var array<string>
     */
    private const GLOBAL_ADMIN_INTENTS = [
        'saas.company.summary',
        'saas.billing.summary',
        'saas.tax.monthly',
        'general.fallback.global',
    ];

    /**
     * Check if the given user is allowed to use this intent.
     *
     * @param  int|null  $companyId  Active tenant context
     */
    public function allows(User $user, string $intent, ?int $companyId): bool
    {
        if ($intent === 'unknown' || $intent === '') {
            return false;
        }

        // Global admin can do anything
        if ($user->isGlobalHcmAdmin()) {
            return $this->isKnownIntent($intent);
        }

        // Global-only intents
        if (in_array($intent, self::GLOBAL_ADMIN_INTENTS, true)) {
            return false;
        }

        // Admin intents require tenant admin
        if (in_array($intent, self::ADMIN_INTENTS, true)) {
            if ($companyId !== null) {
                return $user->isHcmAdminForCompany($companyId);
            }

            return $user->isHcmAdmin();
        }

        // Self-service intents: any authenticated user
        if (in_array($intent, self::SELF_INTENTS, true)) {
            return true;
        }

        // Deny anything not registered
        return false;
    }

    public function isKnownIntent(string $intent): bool
    {
        return in_array($intent, array_merge(
            self::SELF_INTENTS,
            self::ADMIN_INTENTS,
            self::GLOBAL_ADMIN_INTENTS,
        ), true);
    }

    /**
     * Return a fallback intent when the classifier returns 'unknown'.
     *
     * This allows admins to still get a useful (broad-context) response
     * instead of an immediate denial.
     */
    public function fallbackIntentFor(User $user, ?int $companyId): ?string
    {
        if ($user->isGlobalHcmAdmin()) {
            return 'general.fallback.global';
        }

        if ($companyId !== null && $user->isHcmAdminForCompany($companyId)) {
            return 'general.fallback.company';
        }

        if ($user->isHcmAdmin()) {
            return 'general.fallback.company';
        }

        return null;
    }

    /**
     * Return a flat list of all intents the user is allowed to use.
     *
     * @return array<string>
     */
    public function allowedIntentsFor(User $user, ?int $companyId): array
    {
        $all = array_merge(
            self::SELF_INTENTS,
            self::ADMIN_INTENTS,
            self::GLOBAL_ADMIN_INTENTS,
        );

        return array_values(array_filter(
            $all,
            fn (string $intent) => $this->allows($user, $intent, $companyId),
        ));
    }
}
