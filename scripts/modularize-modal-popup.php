<?php
/**
 * Modularize modal-popup.blade.php into domain-grouped partials.
 *
 * Usage: php scripts/modularize-modal-popup.php [--dry-run]
 *
 * Output:
 *   backend/resources/views/components/modals/<domain>.blade.php   (partials)
 *   backend/resources/views/components/modal-popup.blade.php       (thin dispatcher)
 */

$dryRun = in_array('--dry-run', $argv ?? []);

$sourceFile = __DIR__ . '/../backend/resources/views/components/modal-popup.blade.php';
$outputDir  = __DIR__ . '/../backend/resources/views/components/modals';

if (! file_exists($sourceFile)) {
    echo "ERROR: Source file not found: $sourceFile\n";
    exit(1);
}

// ─── Domain map: route-name → group-slug ───────────────────────────────────

$domainMap = [
    // Layout / dashboard shells
    'index'                     => 'layout',
    'layout-horizontal'         => 'layout',
    'layout-detached'           => 'layout',
    'layout-modern'             => 'layout',
    'layout-horizontal-overlay' => 'layout',
    'layout-two-column'         => 'layout',
    'layout-hovered'            => 'layout',
    'layout-box'                => 'layout',
    'layout-horizontal-single'  => 'layout',
    'layout-horizontal-box'     => 'layout',
    'layout-horizontal-sidemenu'=> 'layout',
    'layout-vertical-transparent'=> 'layout',
    'layout-without-header'     => 'layout',
    'layout-rtl'                => 'layout',
    'layout-dark'               => 'layout',

    // HCM — Calendar
    'calendar'                  => 'calendar',

    // HCM — Notes
    'notes'                     => 'notes',

    // HCM — Todo
    'todo'                      => 'todo',
    'todo-list'                 => 'todo',

    // HCM — Email / Communication
    'email'                     => 'email',
    'email-reply'               => 'email',
    'email-settings'            => 'email',
    'call-history'              => 'email',
    'sms-settings'              => 'sms',
    'sms-template'              => 'sms',
    'email-template'            => 'sms',

    // HCM — Employees
    'employees'                 => 'employees',
    'employees-grid'            => 'employees',
    'employee-details'          => 'employees',
    'employee-dashboard'        => 'employees',

    // HCM — Leave
    'leaves'                    => 'leave',
    'leaves-employee'           => 'leave',
    'leave-type'                => 'leave',
    'leave-settings'            => 'leave',

    // HCM — Attendance
    'attendance-admin'          => 'attendance',
    'attendance-employee'       => 'attendance',
    'timesheets'                => 'attendance',
    'schedule-timing'           => 'attendance',
    'overtime'                  => 'attendance',
    'overtime-employee'         => 'attendance',

    // HCM — Payroll
    'payroll'                   => 'payroll',
    'payroll-overtime'          => 'payroll',
    'payroll-deduction'         => 'payroll',
    'tax-employees'             => 'payroll',
    'tax-rates'                 => 'payroll',

    // HCM — Performance
    'performance-indicator'     => 'performance',
    'performance-appraisal'     => 'performance',
    'goal-tracking'             => 'performance',
    'goal-type'                 => 'performance',

    // HCM — Recruitment
    'job-grid'                  => 'recruitment',
    'job-list'                  => 'recruitment',
    'candidates'                => 'recruitment',
    'candidates-grid'           => 'recruitment',
    'candidates-kanban'         => 'recruitment',
    'refferals'                 => 'recruitment',
    'job-details'               => 'recruitment',
    'experience-level'          => 'recruitment',

    // HCM — Training
    'training'                  => 'training',
    'trainers'                  => 'training',
    'training-type'             => 'training',
    'promotion'                 => 'training',

    // HCM — HR Settings
    'departments'               => 'hr-settings',
    'designations'              => 'hr-settings',
    'policy'                    => 'hr-settings',
    'holidays'                  => 'hr-settings',
    'assets'                    => 'hr-settings',
    'asset-categories'          => 'hr-settings',

    // Finance
    'budget-revenues'           => 'finance',
    'budget-expenses'           => 'finance',
    'budgets'                   => 'finance',
    'categories'                => 'finance',
    'taxes'                     => 'finance',
    'provident-fund'            => 'finance',
    'expenses'                  => 'finance',
    'currencies'                => 'finance',
    'payment-gateways'          => 'finance',

    // Billing / Invoices
    'invoices'                  => 'billing',
    'add-invoices'              => 'billing',
    'edit-invoices'             => 'billing',
    'invoice'                   => 'billing',
    'estimates'                 => 'billing',
    'subscription'              => 'billing',
    'packages'                  => 'billing',
    'packages-grid'             => 'billing',
    'pricing'                   => 'billing',
    'purchase-transaction'      => 'billing',

    // CMS
    'pages'                     => 'cms',
    'blogs'                     => 'cms',
    'blog-comments'             => 'cms',
    'blog-tags'                 => 'cms',
    'blog-categories'           => 'cms',
    'blog-2'                    => 'cms',
    'faq'                       => 'cms',
    'testimonials'              => 'cms',

    // CRM — Contacts / Leads
    'contacts'                  => 'crm-contacts',
    'contacts-grid'             => 'crm-contacts',
    'contact-details'           => 'crm-contacts',
    'leads'                     => 'crm-contacts',
    'leads-grid'                => 'crm-contacts',
    'leads-details'             => 'crm-contacts',
    'analytics'                 => 'crm-contacts',
    'activity'                  => 'crm-contacts',

    // CRM — Companies
    'companies-crm'             => 'crm-companies',
    'companies-grid'            => 'crm-companies',
    'company-details'           => 'crm-companies',

    // CRM — Deals
    'deals'                     => 'crm-deals',
    'deals-grid'                => 'crm-deals',
    'deals-details'             => 'crm-deals',
    'pipeline'                  => 'crm-deals',

    // Clients
    'clients'                   => 'clients',
    'clients-grid'              => 'clients',
    'client-details'            => 'clients',

    // Projects / Tasks
    'projects'                  => 'projects',
    'projects-grid'             => 'projects',
    'project-details'           => 'projects',
    'tasks'                     => 'projects',
    'task-board'                => 'projects',
    'task-details'              => 'projects',
    'kanban-view'               => 'projects',

    // Super-admin
    'companies'                 => 'super-admin',
    'domain'                    => 'super-admin',
    'users'                     => 'super-admin',
    'roles-permissions'         => 'super-admin',
    'permission'                => 'super-admin',
    'api-keys'                  => 'super-admin',
    'ban-ip-address'            => 'super-admin',
    'backup'                    => 'super-admin',
    'cronjob'                   => 'super-admin',
    'cronjob-schedule'          => 'super-admin',
    'storage-settings'          => 'super-admin',
    'custom-fields'             => 'super-admin',
    'countries'                 => 'super-admin',
    'states'                    => 'super-admin',
    'cities'                    => 'super-admin',
];

// ─── Parser ────────────────────────────────────────────────────────────────

$content = file_get_contents($sourceFile);
$lines   = explode("\n", $content);
$total   = count($lines);

/**
 * Extracts top-level @if blocks from the file.
 * Returns an array of:
 *   ['condition' => string, 'routes' => string[], 'body' => string, 'raw' => string]
 */
function parseTopLevelBlocks(array $lines): array
{
    $blocks = [];
    $i      = 0;
    $total  = count($lines);

    while ($i < $total) {
        $trimmed = ltrim($lines[$i]);

        // Match @if at line start (top-level)
        if (preg_match('/^@if\s*\(/', $trimmed)) {
            $startLine    = $i;
            $condParts    = [$lines[$i]];
            $depth        = 1;
            $bodyLines    = [];
            $condComplete = false;
            $i++;

            while ($i < $total && $depth > 0) {
                $t = ltrim($lines[$i]);

                // Continue collecting multi-line @if condition until '))'
                if (! $condComplete) {
                    $condParts[] = $lines[$i];
                    // Condition ends when the @if expression's closing ')' found
                    // Simple heuristic: if line ends with )) or )) + comment, condition is done
                    $joined = implode(' ', array_map('trim', $condParts));
                    if (substr_count($joined, '(') <= substr_count($joined, ')')) {
                        $condComplete = true;
                    }
                    $i++;
                    continue;
                }

                if (preg_match('/^@if\b/', $t)) {
                    $depth++;
                } elseif (preg_match('/^@endif/', $t)) {
                    $depth--;
                    if ($depth === 0) {
                        // don't include @endif in body
                        $i++;
                        break;
                    }
                }
                $bodyLines[] = $lines[$i];
                $i++;
            }

            // Build full condition string (first line of @if)
            $fullCondition = $condParts[0];
            // Extract route names from condition
            preg_match_all("/'([^']+)'/", implode(' ', $condParts), $m);
            $routes = $m[1] ?? [];

            // Trim leading/trailing blank lines from body
            $body = rtrim(ltrim(implode("\n", $bodyLines), "\n"));

            $blocks[] = [
                'condition' => $fullCondition,
                'condParts' => $condParts,
                'routes'    => $routes,
                'body'      => $body,
            ];
        } else {
            $i++;
        }
    }

    return $blocks;
}

echo "Parsing $total lines...\n";
$blocks = parseTopLevelBlocks($lines);
echo "Found " . count($blocks) . " top-level @if blocks.\n\n";

// ─── Group blocks by domain ────────────────────────────────────────────────

$grouped = []; // domain => [blocks]

foreach ($blocks as $block) {
    $domain = null;
    foreach ($block['routes'] as $route) {
        if (isset($domainMap[$route])) {
            $domain = $domainMap[$route];
            break;
        }
    }
    if ($domain === null) {
        // Try to detect @if (false) or @if (Request::is(...))
        $cond = trim($block['condParts'][0]);
        if (str_contains($cond, 'false')) {
            $domain = 'super-admin'; // dead block
        } elseif (str_contains($cond, 'Request::is')) {
            // Extract from Request::is pattern
            preg_match_all("/'([^']+)'/", implode(' ', $block['condParts']), $m);
            foreach ($m[1] ?? [] as $r) {
                if (isset($domainMap[$r])) { $domain = $domainMap[$r]; break; }
            }
            if ($domain === null) $domain = 'hr-settings';
        } else {
            $domain = 'misc';
        }
    }

    if (! isset($grouped[$domain])) $grouped[$domain] = [];
    $grouped[$domain][] = $block;
}

echo "Domain groups:\n";
foreach ($grouped as $domain => $domBlocks) {
    echo "  $domain: " . count($domBlocks) . " block(s)\n";
}
echo "\n";

// ─── Write partial files ───────────────────────────────────────────────────

if (! $dryRun) {
    if (! is_dir($outputDir)) {
        mkdir($outputDir, 0775, true);
        echo "Created directory: $outputDir\n";
    }
}

$dispatcherLines = [];

foreach ($grouped as $domain => $domBlocks) {
    $partialPath = "$outputDir/$domain.blade.php";
    $partialView = "components.modals.$domain";

    $parts = [];
    foreach ($domBlocks as $block) {
        // Reconstruct the full @if...@endif block
        $condStr = implode("\n", $block['condParts']);
        $parts[]  = $condStr . "\n" . $block['body'] . "\n@endif";
    }

    $fileContent = implode("\n\n", $parts) . "\n";
    $lineCount   = substr_count($fileContent, "\n");

    if ($dryRun) {
        echo "[DRY-RUN] Would write $partialPath ($lineCount lines)\n";
    } else {
        file_put_contents($partialPath, $fileContent);
        echo "Written: $partialPath ($lineCount lines)\n";
    }

    // Build the @include line for dispatcher
    // We use @include unconditionally because each partial file already has its @if guards
    $dispatcherLines[] = "@include('$partialView')";
}

// ─── Write new modal-popup.blade.php ──────────────────────────────────────

$dispatcherContent = implode("\n", $dispatcherLines) . "\n";

$backupPath = $sourceFile . '.bak';

if ($dryRun) {
    echo "\n[DRY-RUN] New modal-popup.blade.php would be:\n";
    echo $dispatcherContent;
} else {
    // Backup original
    copy($sourceFile, $backupPath);
    echo "\nBackup saved: $backupPath\n";

    file_put_contents($sourceFile, $dispatcherContent);
    echo "Updated: $sourceFile (" . count($dispatcherLines) . " @include lines)\n";
}

echo "\nDone.\n";
