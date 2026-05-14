<?php

namespace App\Services;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class PackageFeatureCatalogRuntimeService
{
    /**
     * @var array<string, array{title: string, description: string, order: int}>
     */
    private const MODULE_META = [
        'employee' => [
            'title' => 'Employee Management',
            'description' => 'Direktori karyawan, profil employee, dan kontrol kuota aktif.',
            'order' => 10,
        ],
        'attendance' => [
            'title' => 'Attendance & Presence',
            'description' => 'Absensi harian, jadwal shift, kalender kerja, dan lembur.',
            'order' => 20,
        ],
        'leave' => [
            'title' => 'Leave Management',
            'description' => 'Pengajuan cuti, approval workflow, dan kalender hari libur.',
            'order' => 30,
        ],
        'payroll' => [
            'title' => 'Payroll & Compensation',
            'description' => 'Run gaji bulanan, komponen kompensasi, THR, dan slip gaji.',
            'order' => 40,
        ],
        'performance' => [
            'title' => 'Performance Management',
            'description' => 'Appraisal, goal tracking, dan riwayat performa karyawan.',
            'order' => 50,
        ],
        'training' => [
            'title' => 'Training & Development',
            'description' => 'Manajemen pelatihan, trainer, dan pengembangan SDM.',
            'order' => 60,
        ],
        'assets' => [
            'title' => 'Asset Management',
            'description' => 'Lifecycle aset perusahaan: penugasan, pengembalian, monitoring.',
            'order' => 70,
        ],
        'platform' => [
            'title' => 'Platform & Tools',
            'description' => 'Sistem internal: notifikasi, FAQ, catatan, dan billing dashboard.',
            'order' => 80,
        ],
        'governance' => [
            'title' => 'Governance & Compliance',
            'description' => 'Kepatuhan: pajak, benefit BPJS, data privacy, SPT Masa PPh21.',
            'order' => 85,
        ],
        'tickets' => [
            'title' => 'Tickets & Support',
            'description' => 'Helpdesk internal: tiket support dan issue tracking.',
            'order' => 90,
        ],
        'lifecycle' => [
            'title' => 'Employee Lifecycle',
            'description' => 'Mutasi karyawan: promosi, resign, terminasi, dan settlement.',
            'order' => 95,
        ],
        'custom' => [
            'title' => 'Add-ons (Unclassified)',
            'description' => 'Fitur tambahan yang masih dalam evaluasi atau setup khusus.',
            'order' => 999,
        ],
    ];

    /**
     * @var array<string, array<string, string|bool>>
     */
    private const FEATURE_META = [
        'max_employees' => [
            'name' => 'Maximum Employees',
            'description' => 'Batasi jumlah employee aktif yang bisa dikelola dalam paket ini.',
            'requiresLimit' => true,
            'limitLabel' => 'Jumlah employee',
            'limitPlaceholder' => 'Contoh: 50',
            'limitSuffix' => 'org',
        ],
        'employee_management' => ['name' => 'Employee Directory'],
        'employee_document_center' => ['name' => 'Document Center'],
        'employee_lifecycle' => ['name' => 'Lifecycle Tracking'],
        'attendance' => ['name' => 'Attendance Dashboard'],
        'attendance_shift_scheduling' => ['name' => 'Shift Scheduling'],
        'leave_management' => ['name' => 'Leave Requests'],
        'leave_approval_flow' => ['name' => 'Approval Workflow'],
        'holiday_calendar' => ['name' => 'Holiday Calendar'],
        'payroll' => ['name' => 'Payroll Run'],
        'payroll_components' => ['name' => 'Compensation Components'],
        'payroll_thr' => ['name' => 'THR Management'],
        'performance' => ['name' => 'Performance'],
        'goal_tracking' => ['name' => 'Goal Tracking'],
        'performance_goal_tracking' => ['name' => 'Advanced Goal Tracking'],
        'training' => ['name' => 'Training'],
        'asset_management' => ['name' => 'Asset Management'],
        'tickets' => ['name' => 'Tickets'],
        'notifications' => ['name' => 'Notifications'],
        'trial_billing_dashboard' => ['name' => 'Trial Billing Dashboard'],
        'tax_governance' => ['name' => 'Tax Governance'],
        'allowance_governance' => ['name' => 'Allowance Governance'],
        'bpjs_governance' => ['name' => 'BPJS Governance'],
        'spt_masa_pph21' => ['name' => 'SPT Masa PPh 21'],
        'overtime' => ['name' => 'Overtime'],
        'calendar_events' => ['name' => 'Calendar Events'],
        'promotion' => ['name' => 'Promotion'],
        'resignation' => ['name' => 'Resignation'],
        'termination' => ['name' => 'Termination'],
        'salary_components' => ['name' => 'Salary Components'],
        'data_privacy' => ['name' => 'Data Privacy'],
        'notes' => ['name' => 'Notes'],
        'faq' => ['name' => 'FAQ'],
    ];

    /**
     * @return array{
     *   groups: array<int, array<string, mixed>>,
     *   mvp_feature_codes: array<int, string>,
     *   addon_feature_codes: array<int, string>,
     *   all_feature_codes: array<int, string>
     * }
     */
    public function build(): array
    {
        $routeFeatureCodes = $this->discoverFeatureCodesFromRoutes();
        $docsFeatureCodes = $this->discoverFeatureCodesFromRuntimeDocs();
        $docsMvpFeatureCodes = $this->discoverMvpFeatureCodesFromRuntimeDocs();

        $allFeatureCodes = collect($routeFeatureCodes)
            ->merge($docsFeatureCodes)
            ->map(fn (string $code): string => trim($code))
            ->filter(fn (string $code): bool => $code !== '')
            ->unique()
            ->values()
            ->all();

        $allFeatureLookup = array_fill_keys($allFeatureCodes, true);

        $mvpFeatureCodes = collect($docsMvpFeatureCodes)
            ->map(fn (string $code): string => trim($code))
            ->filter(fn (string $code): bool => $code !== '' && isset($allFeatureLookup[$code]))
            ->unique()
            ->values()
            ->all();

        $mvpLookup = array_fill_keys($mvpFeatureCodes, true);
        $addonFeatureCodes = collect($allFeatureCodes)
            ->filter(fn (string $code): bool => ! isset($mvpLookup[$code]))
            ->values()
            ->all();

        $groups = collect($allFeatureCodes)
            ->groupBy(fn (string $code): string => $this->resolveModuleForFeatureCode($code))
            ->map(function ($codes, string $module): array {
                return $this->buildGroup($module, $codes->values()->all());
            })
            ->filter(fn (array $group): bool => ! empty($group['features']))
            ->sortBy(fn (array $group): int => (int) ($group['order'] ?? 999))
            ->map(function (array $group): array {
                unset($group['order']);

                return $group;
            })
            ->values()
            ->all();

        return [
            'groups' => $groups,
            'mvp_feature_codes' => $mvpFeatureCodes,
            'addon_feature_codes' => $addonFeatureCodes,
            'all_feature_codes' => $allFeatureCodes,
        ];
    }

    /**
     * @return array{
     *   route_feature_codes: array<int, string>,
     *   docs_feature_codes: array<int, string>,
     *   catalog_feature_codes: array<int, string>,
     *   route_only_feature_codes: array<int, string>,
     *   docs_only_feature_codes: array<int, string>,
     *   custom_module_feature_codes: array<int, string>,
     *   unknown_feature_meta_codes: array<int, string>,
     *   has_drift: bool,
     *   counts: array<string, int>
     * }
     */
    public function healthcheck(): array
    {
        $routeFeatureCodes = $this->discoverFeatureCodesFromRoutes();
        $docsFeatureCodes = $this->discoverFeatureCodesFromRuntimeDocs();
        $catalogFeatureCodes = collect($routeFeatureCodes)
            ->merge($docsFeatureCodes)
            ->map(fn (string $code): string => trim($code))
            ->filter(fn (string $code): bool => $code !== '')
            ->unique()
            ->values()
            ->all();

        $routeLookup = array_fill_keys($routeFeatureCodes, true);
        $docsLookup = array_fill_keys($docsFeatureCodes, true);

        $routeOnlyFeatureCodes = collect($routeFeatureCodes)
            ->filter(fn (string $code): bool => ! isset($docsLookup[$code]))
            ->values()
            ->all();

        $packageOnlyCodes = ['max_employees'];

        $docsOnlyFeatureCodes = collect($docsFeatureCodes)
            ->filter(fn (string $code): bool => ! isset($routeLookup[$code]))
            ->filter(fn (string $code): bool => ! in_array($code, $packageOnlyCodes, true))
            ->values()
            ->all();

        $customModuleFeatureCodes = collect($catalogFeatureCodes)
            ->filter(fn (string $code): bool => $this->resolveModuleForFeatureCode($code) === 'custom')
            ->values()
            ->all();

        $unknownFeatureMetaCodes = collect($catalogFeatureCodes)
            ->filter(fn (string $code): bool => ! isset(self::FEATURE_META[$code]))
            ->values()
            ->all();

        return [
            'route_feature_codes' => $routeFeatureCodes,
            'docs_feature_codes' => $docsFeatureCodes,
            'catalog_feature_codes' => $catalogFeatureCodes,
            'route_only_feature_codes' => $routeOnlyFeatureCodes,
            'docs_only_feature_codes' => $docsOnlyFeatureCodes,
            'custom_module_feature_codes' => $customModuleFeatureCodes,
            'unknown_feature_meta_codes' => $unknownFeatureMetaCodes,
            'has_drift' => $routeOnlyFeatureCodes !== [] || $docsOnlyFeatureCodes !== [],
            'counts' => [
                'route' => count($routeFeatureCodes),
                'docs' => count($docsFeatureCodes),
                'catalog' => count($catalogFeatureCodes),
                'route_only' => count($routeOnlyFeatureCodes),
                'docs_only' => count($docsOnlyFeatureCodes),
                'custom_module' => count($customModuleFeatureCodes),
                'unknown_meta' => count($unknownFeatureMetaCodes),
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function discoverFeatureCodesFromRoutes(): array
    {
        $codes = [];

        foreach (Route::getRoutes() as $route) {
            $uri = trim((string) $route->uri(), '/');

            $inferredCodes = $this->inferFeatureCodesFromRouteUri($uri);
            foreach ($inferredCodes as $inferredCode) {
                $codes[] = $inferredCode;
            }

            foreach ($route->gatherMiddleware() as $middleware) {
                if (! is_string($middleware)) {
                    continue;
                }

                if (preg_match('/^hcm\.(?:api|web)\.feature:([a-z0-9_]+)$/', $middleware, $matches) !== 1) {
                    continue;
                }

                $codes[] = trim((string) ($matches[1] ?? ''));
            }
        }

        return collect($codes)
            ->filter(fn (string $code): bool => $code !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function inferFeatureCodesFromRouteUri(string $uri): array
    {
        if ($uri === '') {
            return [];
        }

        $uri = ltrim($uri, '/');

        $mapping = [
            'v1/hcm/allowance-governance' => 'allowance_governance',
            'v1/hcm/bpjs-governance' => 'bpjs_governance',
            'v1/hcm/spt-masa' => 'spt_masa_pph21',
            'v1/hcm/overtime-requests' => 'overtime',
            'v1/hcm/overtime-types' => 'overtime',
            'v1/hcm/calendar/events' => 'calendar_events',
            'v1/hcm/promotions' => 'promotion',
            'v1/hcm/resignations' => 'resignation',
            'v1/hcm/terminations' => 'termination',
            'v1/hcm/salary-components' => 'payroll_components',
            'v1/hcm/shifts' => 'attendance_shift_scheduling',
            'v1/hcm/leave-requests' => 'leave_approval_flow',
            'v1/hcm/payroll-items' => 'payroll_components',
            'v1/hcm/payroll/thr' => 'payroll_thr',
            'v1/hcm/performance/goal-types' => 'goal_tracking',
            'v1/hcm/performance/goals' => 'goal_tracking',
            'v1/hcm/performance/reviews' => 'performance_goal_tracking',
            'v1/hcm/data-privacy' => 'data_privacy',
            'v1/hcm/notes' => 'notes',
            'v1/hcm/faqs' => 'faq',
            'v1/hcm/notifications' => 'notifications',
            'v1/saas/companies/billing-overview' => 'trial_billing_dashboard',
            'v1/hcm/tax-governance' => 'tax_governance',
        ];

        $codes = [];

        foreach ($mapping as $prefix => $featureCode) {
            if (Str::startsWith($uri, $prefix)) {
                $codes[] = $featureCode;
            }
        }

        if (Str::startsWith($uri, 'v1/hcm/promotions')
            || Str::startsWith($uri, 'v1/hcm/resignations')
            || Str::startsWith($uri, 'v1/hcm/terminations')) {
            $codes[] = 'employee_lifecycle';
        }

        return collect($codes)
            ->map(fn (string $code): string => trim($code))
            ->filter(fn (string $code): bool => $code !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function discoverFeatureCodesFromRuntimeDocs(): array
    {
        $lines = $this->readRuntimeClassificationLines();

        return $this->extractFeatureCodeBullets($lines);
    }

    /**
     * @return array<int, string>
     */
    private function discoverMvpFeatureCodesFromRuntimeDocs(): array
    {
        $lines = $this->readRuntimeClassificationLines();
        if ($lines === []) {
            return [];
        }

        $start = $this->findLineIndexContaining($lines, '## Kategori 2 - MVP Package');
        $end = $this->findLineIndexContaining($lines, '## Kategori 3 - Add-ons');
        if ($start === null) {
            return [];
        }

        $sliceEnd = $end !== null && $end > $start ? $end : count($lines);
        $mvpLines = array_slice($lines, $start, $sliceEnd - $start);

        return $this->extractFeatureCodeBullets($mvpLines);
    }

    /**
     * @return array<int, string>
     */
    private function readRuntimeClassificationLines(): array
    {
        $candidates = [
            base_path('docs/features/RUNTIME-FEATURE-CLASSIFICATION.md'),
            base_path('../docs/features/RUNTIME-FEATURE-CLASSIFICATION.md'),
        ];

        $path = null;
        foreach ($candidates as $candidate) {
            if (is_file($candidate) && is_readable($candidate)) {
                $path = $candidate;
                break;
            }
        }

        if ($path === null) {
            return [];
        }

        $lines = @file($path, FILE_IGNORE_NEW_LINES);

        return is_array($lines) ? $lines : [];
    }

    /**
     * @param  array<int, string>  $lines
     * @return array<int, string>
     */
    private function extractFeatureCodeBullets(array $lines): array
    {
        $codes = [];

        foreach ($lines as $line) {
            if (preg_match('/^\s*[-*]\s+([a-z][a-z0-9_]+)\s*$/', $line, $matches) !== 1) {
                continue;
            }

            $codes[] = trim((string) ($matches[1] ?? ''));
        }

        return collect($codes)
            ->filter(fn (string $code): bool => $code !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $lines
     */
    private function findLineIndexContaining(array $lines, string $needle): ?int
    {
        foreach ($lines as $index => $line) {
            if (Str::contains($line, $needle)) {
                return $index;
            }
        }

        return null;
    }

    private function resolveModuleForFeatureCode(string $code): string
    {
        if ($code === 'max_employees' || Str::startsWith($code, 'employee_')) {
            return 'employee';
        }

        if (Str::startsWith($code, 'attendance')) {
            return 'attendance';
        }

        if ($code === 'holiday_calendar' || Str::startsWith($code, 'leave_')) {
            return 'leave';
        }

        if (Str::startsWith($code, 'payroll')) {
            return 'payroll';
        }

        if ($code === 'performance' || $code === 'goal_tracking' || Str::startsWith($code, 'performance_')) {
            return 'performance';
        }

        if ($code === 'training') {
            return 'training';
        }

        if ($code === 'asset_management' || Str::startsWith($code, 'asset_')) {
            return 'assets';
        }

        if ($code === 'tickets') {
            return 'tickets';
        }

        if (in_array($code, ['notifications', 'trial_billing_dashboard', 'notes', 'faq'], true)) {
            return 'platform';
        }

        if (in_array($code, ['allowance_governance', 'bpjs_governance', 'spt_masa_pph21', 'data_privacy', 'tax_governance'], true)) {
            return 'governance';
        }

        if (in_array($code, ['promotion', 'resignation', 'termination'], true)) {
            return 'lifecycle';
        }

        if ($code === 'calendar_events') {
            return 'attendance';
        }

        if ($code === 'overtime') {
            return 'attendance';
        }

        if ($code === 'salary_components') {
            return 'payroll';
        }

        return 'custom';
    }

    /**
     * @param  array<int, string>  $codes
     * @return array<string, mixed>
     */
    private function buildGroup(string $module, array $codes): array
    {
        $meta = self::MODULE_META[$module] ?? self::MODULE_META['custom'];

        $features = collect($codes)
            ->sort()
            ->map(fn (string $code): array => $this->buildFeatureItem($code))
            ->values()
            ->all();

        return [
            'module' => $module,
            'title' => $meta['title'],
            'description' => $meta['description'],
            'order' => $meta['order'],
            'features' => $features,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildFeatureItem(string $code): array
    {
        $meta = self::FEATURE_META[$code] ?? [];
        $name = isset($meta['name']) ? trim((string) $meta['name']) : '';
        $description = isset($meta['description']) ? trim((string) $meta['description']) : '';

        return [
            'code' => $code,
            'name' => $name !== '' ? $name : Str::headline($code),
            'description' => $description,
            'requiresLimit' => (bool) ($meta['requiresLimit'] ?? false),
            'limitLabel' => isset($meta['limitLabel']) ? trim((string) $meta['limitLabel']) : null,
            'limitPlaceholder' => isset($meta['limitPlaceholder']) ? trim((string) $meta['limitPlaceholder']) : null,
            'limitSuffix' => isset($meta['limitSuffix']) ? trim((string) $meta['limitSuffix']) : null,
        ];
    }
}
