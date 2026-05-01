<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ChecksPermissions;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class HcmGlobalSearchController extends Controller
{
    use ChecksPermissions;

    /**
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:1', 'max:120'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $user = $request->user();
        if (! $user) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'AUTH_UNAUTHENTICATED',
                    'message' => 'Unauthenticated.',
                ],
            ], 401);
        }

        $query = trim((string) ($validated['q'] ?? ''));
        $queryLower = mb_strtolower($query);
        $limit = (int) ($validated['limit'] ?? 8);
        $companyId = $this->activeCompanyId($request);

        $isGlobalAdmin = $user->isGlobalHcmAdmin();
        $isAdmin = $isGlobalAdmin || ($companyId ? $user->isHcmAdminForCompany($companyId) : $user->isHcmAdmin());
        $permissions = $user->permissionsForContext($companyId);

        $catalog = $this->buildCatalog(
            $isGlobalAdmin,
            $isAdmin,
            array_fill_keys(array_keys($permissions), true)
        );

        $tokens = array_values(array_filter(preg_split('/\s+/u', $queryLower) ?: []));
        $matches = [];

        foreach ($catalog as $item) {
            $haystack = mb_strtolower(implode(' ', [
                (string) ($item['label'] ?? ''),
                (string) ($item['description'] ?? ''),
                (string) ($item['path'] ?? ''),
                (string) ($item['section'] ?? ''),
            ]));

            if (! $this->matchesTokens($tokens, $haystack)) {
                continue;
            }

            $score = 0;
            $label = mb_strtolower((string) ($item['label'] ?? ''));
            $path = mb_strtolower((string) ($item['path'] ?? ''));

            if ($label === $queryLower) {
                $score += 120;
            }
            if (str_starts_with($label, $queryLower)) {
                $score += 40;
            }
            if (str_contains($path, $queryLower)) {
                $score += 20;
            }

            $item['score'] = $score;
            $matches[] = $item;
        }

        usort($matches, static function (array $a, array $b): int {
            $scoreA = (int) ($a['score'] ?? 0);
            $scoreB = (int) ($b['score'] ?? 0);

            if ($scoreA !== $scoreB) {
                return $scoreB <=> $scoreA;
            }

            return strcmp((string) ($a['label'] ?? ''), (string) ($b['label'] ?? ''));
        });

        $total = count($matches);
        $items = array_slice($matches, 0, $limit);

        $items = array_map(static function (array $item): array {
            unset($item['score']);

            return $item;
        }, $items);

        return response()->json([
            'success' => true,
            'data' => [
                'query' => $query,
                'total' => $total,
                'limit' => $limit,
                'items' => $items,
            ],
        ]);
    }

    /**
     * @param  array<int, string>  $tokens
     */
    private function matchesTokens(array $tokens, string $haystack): bool
    {
        foreach ($tokens as $token) {
            if (! str_contains($haystack, $token)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, bool>  $permissions
     * @return array<int, array<string, string>>
     */
    private function buildCatalog(bool $isGlobalAdmin, bool $isAdmin, array $permissions): array
    {
        $sections = config('hcm_portal_hub.sections', []);
        $catalog = [];

        foreach ($sections as $section) {
            $sectionTitle = trim((string) ($section['title'] ?? ''));
            $items = is_array($section['items'] ?? null) ? $section['items'] : [];

            foreach ($items as $item) {
                $routeName = trim((string) ($item['route'] ?? ''));
                if ($routeName === '' || ! Route::has($routeName)) {
                    continue;
                }

                if (! $this->canAccessRoute($routeName, $isGlobalAdmin, $isAdmin, $permissions)) {
                    continue;
                }

                $href = (string) route($routeName);
                $path = (string) (parse_url($href, PHP_URL_PATH) ?? '/');

                $catalog[] = [
                    'routeName' => $routeName,
                    'section' => $sectionTitle,
                    'label' => trim((string) ($item['label'] ?? '')),
                    'description' => trim((string) ($item['description'] ?? '')),
                    'path' => $path,
                    'href' => $href,
                ];
            }
        }

        return $catalog;
    }

    /**
     * @param  array<string, bool>  $permissions
     */
    private function canAccessRoute(string $routeName, bool $isGlobalAdmin, bool $isAdmin, array $permissions): bool
    {
        $globalOnlyRoutes = [
            'companies',
            'saas.packages',
            'saas.subscriptions',
            'saas.domains',
            'saas.transactions',
            'saas.invoices',
            'saas.payments',
            'saas.reports',
            'saas.reminders',
            'localization-settings',
            'cronjob',
        ];

        if (in_array($routeName, $globalOnlyRoutes, true)) {
            return $isGlobalAdmin;
        }

        if ($isAdmin) {
            return true;
        }

        $employeeRoutes = [
            'employee-dashboard',
            'attendance-employee',
            'leaves-employee',
            'overtime-employee',
            'payslip',
            'tickets-employee',
        ];

        if (in_array($routeName, $employeeRoutes, true)) {
            return true;
        }

        $routePermissions = [
            'index' => ['dashboard.view'],
            'employees' => ['employee.view'],
            'employees-grid' => ['employee.view'],
            'departments' => ['department.view'],
            'designations' => ['designation.view'],
            'policy' => ['policy.view'],
            'employee-report' => ['report.view'],
            'holidays' => ['holiday.view'],
            'leaves' => ['leave.view'],
            'leave-settings' => ['leave.settings'],
            'leave-type' => ['leave.type'],
            'leave-report' => ['report.view'],
            'attendance-admin' => ['attendance.admin', 'attendance.view'],
            'timesheets' => ['timesheet.view'],
            'schedule-timing' => ['schedule.view'],
            'shift-master' => ['schedule.manage'],
            'overtime-master' => ['overtime.type.manage'],
            'overtime' => ['overtime.view'],
            'attendance-report' => ['report.view'],
            'payroll' => ['payroll.view'],
            'payroll-overtime' => ['payroll.view'],
            'payroll-deduction' => ['payroll.view'],
            'payroll-run' => ['payroll.view'],
            'payroll-run-history' => ['payroll.view'],
            'payroll-thr' => ['payroll.thr.manage', 'payroll.view'],
            'payroll-pkwt-compensation' => ['payroll.pkwt.manage', 'payroll.view'],
            'employee-salary' => ['payroll.view'],
            'payslip-report' => ['report.view'],
            'promotion' => ['promotion.view'],
            'resignation' => ['resignation.view'],
            'termination' => ['termination.view'],
            'performance-indicator' => ['performance.view'],
            'performance-appraisal' => ['performance.view'],
            'performance-review' => ['performance.view'],
            'goal-type' => ['goal.view'],
            'goal-tracking' => ['goal.view'],
            'training' => ['training.view'],
            'training-type' => ['training.view'],
            'trainers' => ['trainer.view', 'training.view'],
            'tickets-admin' => ['ticket.view'],
            'tickets-grid' => ['ticket.view'],
            'ticket-master' => ['ticket.category.manage', 'ticket.view'],
        ];

        $requiredPermissions = $routePermissions[$routeName] ?? [];
        if ($requiredPermissions === []) {
            return false;
        }

        foreach ($requiredPermissions as $permission) {
            if (! empty($permissions[$permission])) {
                return true;
            }
        }

        return false;
    }
}
