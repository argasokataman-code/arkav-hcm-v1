<?php


use App\Mail\AdminComposeMailable;
use App\Models\NotificationDelivery;
use App\Services\NotificationDeliveryRecorder;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CustomAuthController;
use App\Http\Controllers\KnowledgebaseController;
use App\Support\HcmKnowledgebase;
use App\Http\Controllers\CronjobController;
use App\Http\Controllers\WilayahLocationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Models\HcmLeaveTypeSetting;
use App\Http\Controllers\PublicLandingController;

Route::post('custom-login', [CustomAuthController::class, 'customLogin'])->name('login.custom'); 
Route::post('custom-registration', [CustomAuthController::class, 'customRegistration'])->name('register.custom'); 
Route::get('signout', [CustomAuthController::class, 'signOut'])->name('signout');


Route::get('/', [PublicLandingController::class, 'index'])->name('root');

// Public marketing landing page (explicit path)
Route::get('/landing', [PublicLandingController::class, 'index'])->name('landing');

// Public onboarding (trial/pending payment) - unified to landing React modal.
// /trial is kept as a redirect alias so legacy links still work, forwarding
// relevant query params (packageId -> package, startMode) to /landing.
Route::get('/trial', function (Request $request) {
    $query = ['openOnboarding' => 1];

    $packageId = trim((string) $request->query('packageId', ''));
    if ($packageId !== '') {
        $query['package'] = $packageId;
    }

    $startMode = trim((string) $request->query('startMode', ''));
    if (in_array($startMode, ['trial', 'pending_payment'], true)) {
        $query['startMode'] = $startMode;
    }

    return redirect()->route('landing', $query);
})->name('trial');

Route::get('/api-docs', function () {
    return view('api-docs.swagger');
})->name('api-docs');

Route::get('/api-docs/openapi.yaml', function (Request $request) {
    $path = base_path('../docs/api/openapi.yaml');
    if (! is_file($path)) {
        abort(404);
    }

    $raw = file_get_contents($path);
    if (! is_string($raw) || $raw === '') {
        abort(500);
    }

    // Auto-detect the current origin so Swagger "Servers" defaults correctly
    // without manual switching between localhost ports/domains.
    $origin = $request->getSchemeAndHttpHost();

    $serversBlock = "servers:\n"
        ."  - url: {$origin}\n"
        ."    description: Auto-detected (current host)\n"
        ."  - url: http://127.0.0.1:8007\n"
        ."    description: Local backend (./run.sh)\n"
        ."  - url: http://127.0.0.1:5179\n"
        ."    description: Local frontend proxy (optional)\n";

    // Replace existing servers section (if present) up to tags: block.
    $patched = preg_replace('/^servers:\n.*?^tags:\n/ms', $serversBlock."tags:\n", $raw);
    if (! is_string($patched) || $patched === '') {
        // If the file doesn't match expected structure, serve as-is.
        $patched = $raw;
    }

    return response($patched, 200, [
        'Content-Type' => 'application/yaml; charset=utf-8',
        'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
    ]);
})->name('api-docs.spec');

Route::get('/index', function () {
    return view('index');
})->middleware('hcm.web.admin')->name('index');

Route::get('/employee-dashboard', function () {
    return view('employee-dashboard');
})->name('employee-dashboard');

Route::get('/chat', function () {
    return view('chat');
})->name('chat');

Route::get('/voice-call', function () {
    return view('voice-call');
})->name('voice-call');

Route::get('/video-call', function () {
    return view('video-call');
})->name('video-call');

Route::get('/outgoing-call', function () {
    return view('outgoing-call');
})->name('outgoing-call');

Route::get('/incoming-call', function () {
    return view('incoming-call');
})->name('incoming-call');

Route::get('/call-history', function () {
    return view('call-history');
})->name('call-history');

Route::get('/calendar', function () {
    return view('calendar');
})->name('calendar');

Route::match(['get', 'post'], '/email', function (Request $request) {
    $redirectParameters = [];
    $label = trim((string) $request->input('Label', $request->query('Label', '')));
    if ($label !== '') {
        $redirectParameters['Label'] = $label;
    }

    if ($request->isMethod('post')) {
        $validated = $request->validate([
            'to' => ['required', 'email'],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        try {
            Mail::to($validated['to'])->send(new AdminComposeMailable(
                subjectLine: $validated['subject'],
                messageBody: $validated['message'],
                senderName: (string) ($request->user()?->name ?? config('app.name', 'Arkav')),
            ));

            app(NotificationDeliveryRecorder::class)->recordSent('email.compose.sent', 'mail', [
                'recipient' => $validated['to'],
                'metadata' => [
                    'subject' => $validated['subject'],
                    'messagePreview' => mb_substr($validated['message'], 0, 160),
                    'senderUserId' => (int) ($request->user()?->id ?? 0),
                    'senderEmail' => (string) ($request->user()?->email ?? ''),
                ],
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()
                ->route('email', $redirectParameters)
                ->withInput()
                ->withErrors([
                    'compose' => 'Email gagal dikirim. Cek konfigurasi email aktif lalu coba lagi.',
                ]);
        }

        return redirect()
            ->route('email', $redirectParameters)
            ->with('status', 'Email berhasil dikirim ke '.$validated['to'].'.');
    }

    $currentUserId = (int) ($request->user()?->id ?? 0);
    $currentUserEmail = strtolower(trim((string) ($request->user()?->email ?? '')));

    $deliveries = NotificationDelivery::query()
        ->whereIn('event_key', ['email.compose.sent', 'email.inbound.received'])
        ->where('channel', 'mail')
        ->latest('created_at')
        ->limit(200)
        ->get();

    $sentItems = $deliveries
        ->filter(function (NotificationDelivery $delivery) use ($currentUserId): bool {
            if ($delivery->event_key !== 'email.compose.sent') {
                return false;
            }

            return (int) ($delivery->metadata['senderUserId'] ?? 0) === $currentUserId;
        })
        ->values()
        ->map(function (NotificationDelivery $delivery): array {
            return [
                'to' => (string) ($delivery->recipient ?? ''),
                'subject' => (string) ($delivery->metadata['subject'] ?? '(No subject)'),
                'preview' => (string) ($delivery->metadata['messagePreview'] ?? ''),
                'sentAt' => optional($delivery->sent_at)->diffForHumans() ?: '-',
                'sentAtIso' => optional($delivery->sent_at)->toIso8601String(),
            ];
        })
        ->all();

    $inboxItems = $deliveries
        ->filter(function (NotificationDelivery $delivery) use ($currentUserEmail): bool {
            if ($delivery->event_key !== 'email.inbound.received') {
                return false;
            }

            if ($currentUserEmail === '') {
                return true;
            }

            $recipient = strtolower(trim((string) ($delivery->recipient ?? '')));
            if ($recipient === '') {
                return true;
            }

            return str_contains($recipient, $currentUserEmail);
        })
        ->values()
        ->map(function (NotificationDelivery $delivery): array {
            return [
                'from' => (string) ($delivery->metadata['from'] ?? '-'),
                'subject' => (string) ($delivery->metadata['subject'] ?? '(No subject)'),
                'preview' => (string) ($delivery->metadata['messagePreview'] ?? ''),
                'receivedAt' => optional($delivery->sent_at)->diffForHumans() ?: '-',
                'receivedAtIso' => optional($delivery->sent_at)->toIso8601String(),
            ];
        })
        ->all();

    return view('email', [
        'inboxItems' => $inboxItems,
        'sentItems' => $sentItems,
        'inboxCount' => count($inboxItems),
        'sentCount' => count($sentItems),
        'totalCount' => count($sentItems) + count($inboxItems),
    ]);
})->name('email');

Route::get('/notes', function () {
    return view('notes');
})->name('notes');

Route::get('/social-feed', function () {
    return view('social-feed');
})->name('social-feed');

Route::get('/file-manager', function () {
    return view('file-manager');
})->name('file-manager');

Route::get('/invoices', function () {
    return view('invoices');
})->name('invoices');

Route::get('/add-invoices', function () {
    return view('add-invoices');
})->name('add-invoices');

Route::get('/edit-invoices', function () {
    return view('edit-invoices');
})->name('edit-invoices');

Route::get('/invoice-details', function () {
    return view('invoice-details');
})->name('invoice-details');

Route::get('/dashboard', function () {
    return view('saas-dashboard');
})->middleware('hcm.web.global-admin')->name('dashboard');

Route::get('/activity', function () {
    return view('activity');
})->middleware('hcm.web.primary-super-admin')->name('activity');

Route::get('/saas-dashboard', function () {
    return view('saas-dashboard');
})->middleware('hcm.web.global-admin')->name('saas-dashboard');

Route::get('/saas/packages', function () {
    return view('saas.packages');
})->middleware('hcm.web.global-admin')->name('saas.packages');

Route::get('/saas/subscriptions', function () {
    return view('saas.subscriptions');
})->middleware('hcm.web.global-admin')->name('saas.subscriptions');

Route::get('/saas/billing-overview', function () {
    return view('saas.billing-overview');
})->middleware('hcm.web.global-admin')->name('saas.billing-overview');

Route::get('/saas/billing-overview/invoices/{invoice}', function (\App\Models\Invoice $invoice) {
    return view('saas.billing-overview-invoice-detail', ['invoice' => $invoice]);
})->middleware('hcm.web.global-admin')->name('saas.billing-overview.invoice-detail');

Route::get('/saas/domains', function () {
    return view('saas.domains');
})->middleware('hcm.web.global-admin')->name('saas.domains');

Route::get('/saas/transactions', function () {
    return view('saas.transactions');
})->middleware('hcm.web.global-admin')->name('saas.transactions');

Route::get('/saas/invoices', function () {
    return view('saas.invoices');
})->middleware('hcm.web.global-admin')->name('saas.invoices');

Route::get('/saas/payments', function () {
    return view('saas.payments');
})->middleware('hcm.web.global-admin')->name('saas.payments');

Route::get('/saas/reports', function () {
    return view('saas.reports');
})->middleware('hcm.web.global-admin')->name('saas.reports');

Route::get('/saas/reminders', function () {
    return view('saas.reminders');
})->middleware('hcm.web.global-admin')->name('saas.reminders');

// Company views for billing — tenant admin self-service (OWNER/HR_ADMIN/OPS_ADMIN/ADMIN
// via RBAC). Non-admin karyawan dialihkan ke /employee-dashboard. Global super-admin tetap
// bypass lewat EnsureHcmWebAdminPage.
Route::get('/company/invoices', function () {
    return view('company.invoices');
})->middleware('hcm.web.admin')->name('company.invoices');

// Upgrade plan flow — reachable for any authenticated user whose tenant was
// blocked by a feature gate. The page itself posts to /v1/hcm/subscriptions/*
// endpoints which enforce owner/admin RBAC.
Route::get('/upgrade', function () {
    return view('upgrade');
})->name('upgrade');

Route::get('/api-token', [\App\Http\Controllers\ApiTokenController::class, 'getToken'])->name('api-token');

Route::get('/companies', function () {
    return view('companies');
})->middleware('hcm.web.global-admin')->name('companies');

Route::get('/subscription', function () {
    return view('saas.subscription-checkout');
})->middleware('hcm.web.admin')->name('subscription');

Route::get('/packages', function () {
    return view('saas.packages');
})->middleware('hcm.web.global-admin')->name('packages');

Route::get('/packages-grid', function () {
    return view('packages-grid');
})->middleware('hcm.web.global-admin')->name('packages-grid');

Route::get('/domain', function () {
    return view('saas.domains');
})->middleware('hcm.web.global-admin')->name('domain');

Route::get('/purchase-transaction', function () {
    return view('saas.transactions');
})->middleware('hcm.web.global-admin')->name('purchase-transaction');

Route::get('/layout-horizontal', function () {
    return view(view: 'layout-horizontal');
})->name('layout-horizontal');

Route::get('/layout-detached', function () {
    return view(view: 'layout-detached');
})->name('layout-detached');

Route::get('/layout-modern', function () {
    return view(view: 'layout-modern');
})->name('layout-modern');

Route::get('/layout-two-column', function () {
    return view(view: 'layout-two-column');
})->name('layout-two-column');

Route::get('/layout-horizontal-overlay', function () {
    return view(view: 'layout-horizontal-overlay');
})->name('layout-horizontal-overlay');

Route::get('/layout-hovered', function () {
    return view(view: 'layout-hovered');
})->name('layout-hovered');

Route::get('/layout-box', function () {
    return view(view: 'layout-box');
})->name('layout-box');

Route::get('/layout-horizontal-single', function () {
    return view(view: 'layout-horizontal-single');
})->name('layout-horizontal-single');

Route::get('/layout-horizontal-box', function () {
    return view(view: 'layout-horizontal-box');
})->name('layout-horizontal-box');

Route::get('/layout-horizontal-sidemenu', function () {
    return view(view: 'layout-horizontal-sidemenu');
})->name('layout-horizontal-sidemenu');

Route::get('/layout-vertical-transparent', function () {
    return view(view: 'layout-vertical-transparent');
})->name('layout-vertical-transparent');

Route::get('/layout-without-header', function () {
    return view(view: 'layout-without-header');
})->name('layout-without-header');

Route::get('/layout-rtl', function () {
    return view(view: 'layout-rtl');
})->name('layout-rtl');

Route::get('/layout-dark', function () {
    return view(view: 'layout-dark');
})->name('layout-dark');

Route::get('/clients-grid', function () {
    return view(view: 'clients-grid');
})->name('clients-grid');

Route::get('/clients', function () {
    return view(view: 'clients');
})->name('clients');

Route::get('/client-details', function () {
    return view(view: 'client-details');
})->name('client-details');

Route::get('/contacts-grid', function () {
    return view(view: 'contacts-grid');
})->name('contacts-grid');

Route::get('/contacts', function () {
    return view(view: 'contacts');
})->name('contacts');

Route::get('/contact-details', function () {
    return view(view: 'contact-details');
})->name('contact-details');

Route::get('/companies-grid', function () {
    return view(view: 'companies-grid');
})->name('companies-grid');

Route::get('/companies-crm', function () {
    return view(view: 'companies-crm');
})->name('companies-crm');

Route::get('/company-details', function () {
    return view(view: 'company-details');
})->name('company-details');

Route::get('/employees', function () {
    return view(view: 'employees');
})->middleware('hcm.web.admin')->name('employees');

Route::get('/employees-grid', function () {
    return view(view: 'employees-grid');
})->middleware('hcm.web.admin')->name('employees-grid');

Route::get('/employee-details', function () {
    return view(view: 'employee-details');
})->name('employee-details');

Route::get('/departments', function () {
    return view(view: 'departments');
})->middleware('hcm.web.admin')->name('departments');

Route::get('/designations', function () {
    return view(view: 'designations');
})->middleware('hcm.web.admin')->name('designations');

Route::get('/teams', function () {
    return view(view: 'teams');
})->middleware('hcm.web.admin')->name('teams');

Route::get('/teams/{id}/members', function (string $id) {
    return view(view: 'team-members', data: ['teamId' => $id]);
})->middleware('hcm.web.admin')->name('team-members');

Route::get('/policy', function () {
    return view(view: 'policy');
})->middleware('hcm.web.admin')->name('policy');

Route::get('/tickets', function () {
    $user = request()->user();
    $activeCompanyId = (int) (request()->attributes->get('activeCompanyId') ?? 0);
    $isAdmin = $user && ($activeCompanyId > 0
        ? $user->isHcmAdminForCompany($activeCompanyId)
        : $user->isHcmAdmin());

    if ($isAdmin) {
        return redirect('/tickets-admin');
    }

    return redirect('/tickets-employee');
})->name('tickets');

Route::get('/tickets-admin', function () {
    return view(view: 'tickets', data: [
        'ticketMode' => 'admin',
        'ticketTitle' => 'Tickets (Admin)',
    ]);
})->middleware(['hcm.web.admin', 'hcm.web.feature:tickets'])->name('tickets-admin');

Route::get('/tickets-employee', function () {
    return view(view: 'tickets', data: [
        'ticketMode' => 'employee',
        'ticketTitle' => 'Tickets (Employee)',
    ]);
})->middleware('hcm.web.feature:tickets')->name('tickets-employee');

Route::get('/ticket-master', function () {
    return view(view: 'ticket-master');
})->middleware(['hcm.web.admin', 'hcm.web.feature:tickets'])->name('ticket-master');

Route::get('/tickets-grid', function () {
    return view(view: 'tickets-grid');
})->name('tickets-grid');

Route::get('/ticket-details', function () {
    $user = request()->user();
    $activeCompanyId = (int) (request()->attributes->get('activeCompanyId') ?? 0);
    $isAdmin = $user && ($activeCompanyId > 0
        ? $user->isHcmAdminForCompany($activeCompanyId)
        : $user->isHcmAdmin());

    if ($isAdmin) {
        return redirect('/tickets-admin');
    }

    return redirect('/tickets-employee');
})->name('ticket-details-legacy');

Route::get('/ticket-details/{id}', function (int $id) {
    return view(view: 'ticket-details', data: ['ticketId' => $id]);
})->whereNumber('id')->name('ticket-details');

Route::get('/holidays', function () {
    return view(view: 'holidays');
})->middleware('hcm.web.admin')->name('holidays');

Route::get('/leaves', function () {
    return view(view: 'leaves');
})->middleware('hcm.web.admin')->name('leaves');

Route::get('/leaves-employee', function () {
    return view(view: 'leaves-employee');
})->name('leaves-employee');

Route::get('/leave-request', function () {
    $user = request()->user();
    $activeCompanyId = (int) (request()->attributes->get('activeCompanyId') ?? 0);
    $isAdmin = $user && ($activeCompanyId > 0
        ? $user->isHcmAdminForCompany($activeCompanyId)
        : $user->isHcmAdmin());

    if ($isAdmin) {
        return redirect('/leaves');
    }

    return redirect('/leaves-employee');
})->name('leave-request-legacy');

Route::get('/leave-settings', function () {
    return view(view: 'leave-settings');
})->middleware('hcm.web.admin')->name('leave-settings');

Route::get('/attendance-admin', function () {
    return view(view: 'attendance-admin');
})->middleware('hcm.web.admin')->name('attendance-admin');

Route::get('/attendance-employee', function () {
    return view(view: 'attendance-employee');
})->name('attendance-employee');

Route::get('/timesheets', function () {
    return view(view: 'timesheets');
})->middleware('hcm.web.admin')->name('timesheets');

Route::get('/schedule-timing', function () {
    return view(view: 'schedule-timing');
})->middleware('hcm.web.admin')->name('schedule-timing');

Route::get('/schedules', function () {
    $user = request()->user();
    $activeCompanyId = (int) (request()->attributes->get('activeCompanyId') ?? 0);
    $isAdmin = $user && ($activeCompanyId > 0
        ? $user->isHcmAdminForCompany($activeCompanyId)
        : $user->isHcmAdmin());

    if ($isAdmin) {
        return redirect('/schedule-timing');
    }

    return redirect('/attendance-employee');
})->name('schedules-legacy');

Route::get('/shift-master', function () {
    return view(view: 'shift-master');
})->middleware('hcm.web.admin')->name('shift-master');

Route::get('/overtime', function () {
    return view('overtime', ['arcavOvertimeEmployeeOnly' => false]);
})->middleware('hcm.web.admin')->name('overtime');

Route::get('/overtime-employee', function () {
    return view('overtime', ['arcavOvertimeEmployeeOnly' => true]);
})->name('overtime-employee');

Route::get('/overtime-request', function () {
    $user = request()->user();
    $activeCompanyId = (int) (request()->attributes->get('activeCompanyId') ?? 0);
    $isAdmin = $user && ($activeCompanyId > 0
        ? $user->isHcmAdminForCompany($activeCompanyId)
        : $user->isHcmAdmin());

    if ($isAdmin) {
        return redirect('/overtime');
    }

    return redirect('/overtime-employee');
})->name('overtime-request-legacy');

Route::get('/overtime-master', function () {
    return view(view: 'overtime-master');
})->middleware('hcm.web.admin')->name('overtime-master');

Route::get('/performance-indicator', function () {
    return view(view: 'performance-indicator');
})->middleware('hcm.web.admin')->name('performance-indicator');

Route::get('/performance-review', function () {
    return view(view: 'performance-review');
})->name('performance-review');

Route::get('/performance-appraisal', function () {
    return view(view: 'performance-appraisal');
})->middleware('hcm.web.admin')->name('performance-appraisal');

Route::get('/goal-tracking', function () {
    return view(view: 'goal-tracking');
})->name('goal-tracking');

Route::get('/goal-type', function () {
    return view(view: 'goal-type');
})->middleware('hcm.web.admin')->name('goal-type');

Route::get('/training', function () {
    return view(view: 'training');
})->middleware('hcm.web.feature:training')->name('training');

Route::get('/trainers', function () {
    return view(view: 'trainers');
})->middleware(['hcm.web.admin', 'hcm.web.feature:training'])->name('trainers');

Route::get('/training-type', function () {
    return view(view: 'training-type');
})->middleware(['hcm.web.admin', 'hcm.web.feature:training'])->name('training-type');

Route::middleware('hcm.web.admin')->group(function (): void {
    Route::get('/promotion', function () {
        return view(view: 'promotion');
    })->name('promotion');

    Route::get('/resignation', function () {
        return view(view: 'resignation');
    })->name('resignation');

    Route::get('/termination', function () {
        return view(view: 'termination');
    })->name('termination');

    Route::middleware('hcm.web.feature:payroll')->group(function (): void {
        Route::get('/salary-component-master', function () {
            return view('salary-component-master');
        })->name('salary-component-master');

        Route::get('/employee-salary', function () {
            return view('employee-salary');
        })->name('employee-salary');

        Route::get('/payroll', function () {
            return view('payroll');
        })->name('payroll');

        Route::get('/payroll-overtime', function () {
            return view('payroll-overtime');
        })->name('payroll-overtime');

        Route::get('/payroll-deduction', function () {
            return view('payroll-deduction');
        })->name('payroll-deduction');

        Route::get('/payroll-thr', function () {
            return view('payroll-thr');
        })->name('payroll-thr');

        Route::get('/payroll-pkwt-compensation', function () {
            return view('payroll-pkwt-compensation');
        })->name('payroll-pkwt-compensation');

        Route::get('/payroll-run', function () {
            return view('payroll-run');
        })->name('payroll-run');

        Route::get('/payroll-run-history', function () {
            return view('payroll-run-history');
        })->name('payroll-run-history');
    });
});

Route::get('/job-grid', function () {
    return view(view: 'job-grid');
})->name('job-grid');

Route::get('/job-list', function () {
    return view(view: 'job-list');
})->name('job-list');

Route::get('/candidates-grid', function () {
    return view(view: 'candidates-grid');
})->name('candidates-grid');

Route::get('/candidates', function () {
    return view(view: 'candidates');
})->name('candidates');

Route::get('/candidates-kanban', function () {
    return view(view: 'candidates-kanban');
})->name('candidates-kanban');

Route::get('/refferals', function () {
    return view(view: 'refferals');
})->name('refferals');

Route::get('/estimates', function () {
    return view(view: 'estimates');
})->name('estimates');

Route::get('/payments', function () {
    return view(view: 'payments');
})->name('payments');

Route::get('/job-details', function () {
    return view(view: 'job-details');
})->name('job-details');

Route::get('/aptitude-result', function () {
    return view(view: 'aptitude-result');
})->name('aptitude-result');

Route::get('/blog-2', function () {
    return view(view: 'blog-2');
})->name('blog-2');

Route::get('/currencies', function () {
    return view(view: 'currencies');
})->middleware('hcm.web.global-admin')->name('currencies');

Route::get('/email-reply', function ()       {
    return view(view: 'email-reply');
})->name('email-reply');

Route::get('/experience-level', function ()       {
    return view(view: 'experience-level');
})->name('experience-level');

Route::get('/form-pickers', function ()       {
    return view(view: 'form-pickers');
})->name('form-pickers');

Route::get('/group-video-call', function ()       {
    return view(view: 'group-video-call');
})->name('group-video-call');

Route::get('/invoice', function ()       {
    return view(view: 'invoice');
})->name('invoice');


Route::get('/ui-alerts', function () {
    return view('ui-alerts');
})->name('ui-alerts');

Route::get('/ui-accordion', function () {
    return view('ui-accordion');
})->name('ui-accordion');

Route::get('/ui-avatar', function () {
    return view('ui-avatar');
})->name('ui-avatar');

Route::get('/ui-badges', function () {
    return view('ui-badges');
})->name('ui-badges');

Route::get('/ui-borders', function () {
    return view('ui-borders');
})->name('ui-borders');

Route::get('/ui-buttons', function () {
    return view('ui-buttons');
})->name('ui-buttons');

Route::get('/ui-buttons-group', function () {
    return view('ui-buttons-group');
})->name('ui-buttons-group');

Route::get('/ui-breadcrumb', function () {
    return view('ui-breadcrumb');
})->name('ui-breadcrumb');

Route::get('/ui-cards', function () {
    return view('ui-cards');
})->name('ui-cards');

Route::get('/ui-carousel', function () {
    return view('ui-carousel');
})->name('ui-carousel');

Route::get('/ui-colors', function () {
    return view('ui-colors');
})->name('ui-colors');

Route::get('/ui-dropdowns', function () {
    return view('ui-dropdowns');
})->name('ui-dropdowns');

Route::get('/ui-grid', function () {
    return view('ui-grid');
})->name('ui-grid');

Route::get('/ui-images', function () {
    return view('ui-images');
})->name('ui-images');

Route::get('/ui-lightbox', function () {
    return view('ui-lightbox');
})->name('ui-lightbox');

Route::get('/ui-media', function () {
    return view('ui-media');
})->name('ui-media');

Route::get('/ui-modals', function () {
    return view('ui-modals');
})->name('ui-modals');

Route::get('/ui-offcanvas', function () {
    return view('ui-offcanvas');
})->name('ui-offcanvas');

Route::get('/ui-pagination', function () {
    return view('ui-pagination');
})->name('ui-pagination');

Route::get('/ui-popovers', function () {
    return view('ui-popovers');
})->name('ui-popovers');

Route::get('/ui-progress', function () {
    return view('ui-progress');
})->name('ui-progress');

Route::get('/ui-placeholders', function () {
    return view('ui-placeholders');
})->name('ui-placeholders');

Route::get('/ui-rangeslider', function () {
    return view('ui-rangeslider');
})->name('ui-rangeslider');

Route::get('/ui-spinner', function () {
    return view('ui-spinner');
})->name('ui-spinner');

Route::get('/ui-sweetalerts', function () {
    return view('ui-sweetalerts');
})->name('ui-sweetalerts');

Route::get('/ui-nav-tabs', function () {
    return view('ui-nav-tabs');
})->name('ui-nav-tabs');

Route::get('/ui-toasts', function () {
    return view('ui-toasts');
})->name('ui-toasts');

Route::get('/ui-tooltips', function () {
    return view('ui-tooltips');
})->name('ui-tooltips');

Route::get('/ui-typography', function () {
    return view('ui-typography');
})->name('ui-typography');

Route::get('/ui-video', function () {
    return view('ui-video');
})->name('ui-video');

Route::get('/ui-ribbon', function () {
    return view('ui-ribbon');
})->name('ui-ribbon');

Route::get('/ui-clipboard', function () {
    return view('ui-clipboard');
})->name('ui-clipboard');

Route::get('/ui-drag-drop', function () {
    return view('ui-drag-drop');
})->name('ui-drag-drop');

Route::get('/ui-rating', function () {
    return view('ui-rating');
})->name('ui-rating');

Route::get('/ui-text-editor', function () {
    return view('ui-text-editor');
})->name('ui-text-editor');

Route::get('/ui-swiperjs', function () {
    return view('ui-swiperjs');
})->name('ui-swiperjs');

Route::get('/ui-counter', function () {
    return view('ui-counter');
})->name('ui-counter');

Route::get('/ui-scrollbar', function () {
    return view('ui-scrollbar');
})->name('ui-scrollbar');

Route::get('/ui-stickynote', function () {
    return view('ui-stickynote');
})->name('ui-stickynote');

Route::get('/ui-timeline', function () {
    return view('ui-timeline');
})->name('ui-timeline');

Route::get('/chart-apex', function () {
    return view('chart-apex');
})->name('chart-apex');

Route::get('/chart-c3', function () {
    return view('chart-c3');
})->name('chart-c3');  

Route::get('/chart-flot', function () {
    return view('chart-flot');
})->name('chart-flot'); 

Route::get('/chart-js', function () {
    return view('chart-js');
})->name('chart-js');    

Route::get('/chart-morris', function () {
    return view('chart-morris');
})->name('chart-morris'); 

Route::get('/chart-peity', function () {
    return view('chart-peity');
})->name('chart-peity');

Route::get('/icon-fontawesome', function () {
    return view('icon-fontawesome');
})->name('icon-fontawesome');

Route::get('/icon-feather', function () {
    return view('icon-feather');
})->name('icon-feather');

Route::get('/icon-ionic', function () {
    return view('icon-ionic');
})->name('icon-ionic');

Route::get('/icon-material', function () {
    return view('icon-material');
})->name('icon-material');

Route::get('/icon-pe7', function () {
    return view('icon-pe7');
})->name('icon-pe7');

Route::get('/icon-simpleline', function () {
    return view('icon-simpleline');
})->name('icon-simpleline');

Route::get('/icon-themify', function () {
    return view('icon-themify');
})->name('icon-themify');

Route::get('/icon-weather', function () {
    return view('icon-weather');
})->name('icon-weather');

Route::get('/icon-typicon', function () {
    return view('icon-typicon');
})->name('icon-typicon');

Route::get('/icon-flag', function () {
    return view('icon-flag');
})->name('icon-flag');

Route::get('/icon-tabler', function () {
    return view('icon-tabler');
})->name('icon-tabler');

Route::get('/icon-bootstrap', function () {
    return view('icon-bootstrap');
})->name('icon-bootstrap');

Route::get('/icon-remix', function () {
    return view('icon-remix');
})->name('icon-remix');

Route::get('/form-checkbox-radios', function () {
    return view('form-checkbox-radios');
})->name('form-checkbox-radios');

Route::get('/form-floating-labels', function () {
    return view('form-floating-labels');
})->name('form-floating-labels');

Route::get('/form-grid-gutters', function () {
    return view('form-grid-gutters');
})->name('form-grid-gutters');

Route::get('/form-elements', function () {
    return view('form-elements');
})->name('form-elements');

Route::get('/form-select', function () {
    return view('form-select');
})->name('form-select');

Route::get('/form-select2', function () {
    return view('form-select2');
})->name('form-select2');

Route::get('/form-fileupload', function () {
    return view('form-fileupload');
})->name('form-fileupload');

Route::get('/form-wizard', function () {
    return view('form-wizard');
})->name('form-wizard');

Route::get('/form-basic-inputs', function () {
    return view('form-basic-inputs');
})->name('form-basic-inputs');

Route::get('/form-input-groups', function () {
    return view('form-input-groups');
})->name('form-input-groups');

Route::get('/form-horizontal', function () {
    return view('form-horizontal');
})->name('form-horizontal');

Route::get('/form-vertical', function () {
    return view('form-vertical');
})->name('form-vertical');

Route::get('/form-mask', function () {
    return view('form-mask');
})->name('form-mask');

Route::get('/form-validation', function () {
    return view('form-validation');
})->name('form-validation');

Route::get('/tables-basic', function () {
    return view('tables-basic');
})->name('tables-basic');

Route::get('/data-tables', function () {
    return view('data-tables');
})->name('data-tables');

Route::get('/maps-vector', function () {
    return view('maps-vector');
})->name('maps-vector');

Route::get('/maps-leaflet', function () {
    return view('maps-leaflet');
})->name('maps-leaflet');
Route::get('/login', function () {
    return view('login');
})->name('login');
Route::get('/login-2', function () {
    return view('login-2');
})->name('login-2');
Route::get('/login-3', function () {
    return view('login-3');
})->name('login-3');
Route::get('/register', function () {
    return redirect()->route('landing', ['openOnboarding' => 1, 'startMode' => 'pending_payment']);
})->name('register');
Route::get('/register-2', function () {
    return redirect()->route('landing', ['openOnboarding' => 1, 'startMode' => 'pending_payment']);
})->name('register-2');
Route::get('/register-3', function () {
    return redirect()->route('landing', ['openOnboarding' => 1, 'startMode' => 'pending_payment']);
})->name('register-3');
Route::get('/forgot-password', function () {
    return view('forgot-password');
})->name('forgot-password');
Route::post('/forgot-password', [CustomAuthController::class, 'sendPasswordResetLink'])->name('password.email');
Route::get('/forgot-password-2', function () {
    return view('forgot-password-2');
})->name('forgot-password-2');
Route::get('/forgot-password-3', function () {
    return view('forgot-password-3');
})->name('forgot-password-3');
Route::get('/reset-password', function () {
    return redirect()->route('forgot-password');
})->name('reset-password');
Route::get('/reset-password/{token}', [CustomAuthController::class, 'showResetPasswordForm'])->name('password.reset');
Route::post('/reset-password', [CustomAuthController::class, 'updatePassword'])->name('password.update');
Route::get('/reset-password-2', function () {
    return view('reset-password-2');
})->name('reset-password-2');
Route::get('/reset-password-3', function () {
    return view('reset-password-3');
})->name('reset-password-3');
Route::get('/email-verification', function () {
    return view('email-verification');
})->name('email-verification');
Route::get('/email-verification-2', function () {
    return view('email-verification-2');
})->name('email-verification-2');
Route::get('/email-verification-3', function () {
    return view('email-verification-3');
})->name('email-verification-3');
Route::get('/two-step-verification', function () {
    return view('two-step-verification');
})->name('two-step-verification');
Route::get('/two-step-verification-2', function () {
    return view('two-step-verification-2');
})->name('two-step-verification-2');
Route::get('/two-step-verification-3', function () {
    return view('two-step-verification-3');
})->name('two-step-verification-3');
Route::get('/lock-screen', function () {
    return view('lock-screen');
})->name('lock-screen');
Route::post('/lock-screen/verify', [CustomAuthController::class, 'verifyLockScreen'])->name('lock-screen.verify');
Route::get('/error-404', function () {
    return view('error-404');
})->name('error-404');
Route::get('/error-500', function () {
    return view('error-500');
})->name('error-500');
Route::get('/coming-soon', function () {
    return view('coming-soon');
})->name('coming-soon');
Route::get('/under-maintenance', function () {
    return view('under-maintenance');
})->name('under-maintenance');
Route::get('/under-construction', function () {
    return view('under-construction');
})->name('under-construction');

Route::get('/profile-settings', function () {
    return view('profile-settings');
})->name('profile-settings');

Route::get('/company-profile', function () {
    return view('company-profile');
})->name('company-profile');

Route::redirect('/profile-settingsrout', '/profile-settings', 301)->name('profile-settings.alias');

Route::get('/security-settings', function () {
    return view('security-settings');
})->name('security-settings');

Route::get('/notification-settings', function () {
    return view('notification-settings');
})->name('notification-settings');

Route::get('/notification-observability', function () {
    return view('notification-observability');
})->middleware('hcm.web.global-admin')->name('notification-observability');

Route::get('/connected-apps', function () {
    return view('connected-apps');
})->name('connected-apps');

Route::get('/bussiness-settings', function () {
    return view('bussiness-settings');
})->middleware('hcm.web.global-admin')->name('bussiness-settings');

Route::get('/business-settings', function () {
    return redirect()->route('bussiness-settings');
})->middleware('hcm.web.global-admin')->name('business-settings');

Route::get('/seo-settings', function () {
    return view('seo-settings');
})->middleware('hcm.web.global-admin')->name('seo-settings');

Route::get('/localization-settings', function () {
    return view('localization-settings');
})->middleware('hcm.web.global-admin')->name('localization-settings');

Route::middleware('hcm.web.admin')->group(function (): void {
    Route::get('/expenses-report', function () {
        return view('expenses-report');
    })->name('expenses-report');

    Route::get('/invoice-report', function () {
        return view('invoice-report');
    })->name('invoice-report');

    Route::get('/payment-report', function () {
        return view('payment-report');
    })->name('payment-report');

    Route::get('/project-report', function () {
        return view('project-report');
    })->name('project-report');

    Route::get('/task-report', function () {
        return view('task-report');
    })->name('task-report');

    Route::get('user-report', function () {
        return view('user-report');
    })->name('user-report');

    Route::get('employee-report', function () {
        return view('employee-report');
    })->name('employee-report');

    Route::get('payslip-report', function () {
        return view('payslip-report');
    })->name('payslip-report');

    Route::get('attendance-report', function () {
        return view('attendance-report');
    })->name('attendance-report');

    Route::get('leave-report', function () {
        return view('leave-report');
    })->name('leave-report');

    Route::get('daily-report', function () {
        return view('daily-report');
    })->name('daily-report');

    Route::get('reports', function () {
        return view('reports.hub');
    })->name('reports-hub');
});

Route::get('roles-permissions', function() {
    return view('roles-permissions');
})->middleware('hcm.web.admin')->name('roles-permissions');

Route::get('permission', function() {
    return view('permission');
})->middleware('hcm.web.admin')->name('permission');

Route::get('knowledgebase', [KnowledgebaseController::class, 'index'])->name('knowledgebase');
Route::get('knowledgebase/category/{slug}', [KnowledgebaseController::class, 'category'])->name('knowledgebase.category');
Route::get('knowledgebase/article/{slug}', [KnowledgebaseController::class, 'article'])->name('knowledgebase.article');

Route::get('knowledgebase-view', function (Request $request) {
    $category = $request->query('category');
    if (is_string($category) && $category !== '' && HcmKnowledgebase::categoryBySlug($category)) {
        return redirect()->route('knowledgebase.category', ['slug' => $category]);
    }

    return redirect()->route('knowledgebase');
})->name('knowledgebase-view');

Route::get('knowledgebase-details', function (Request $request) {
    $article = $request->query('article');
    if (is_string($article) && $article !== '' && HcmKnowledgebase::resolveArticle($article)) {
        return redirect()->route('knowledgebase.article', ['slug' => $article]);
    }

    return redirect()->route('knowledgebase');
})->name('knowledgebase-details');

Route::get('users', function() {
    return view('users');
})->middleware('hcm.web.admin')->name('users');

Route::middleware(['hcm.web.admin', 'hcm.web.asset-management'])->group(function (): void {
    Route::get('assets', function() {
        return view('assets');
    })->name('assets');

    Route::get('asset-categories', function() {
        return view('asset-categories');
    })->name('asset-categories');
});

Route::get('payslip', function() {
    return view('payslip');
})->name('payslip');

Route::get('/prefixes', function () {
    return view('prefixes');
})->middleware('hcm.web.admin')->name('prefixes');

Route::get('/preferences', function () {
    return view('preferences');
})->middleware('hcm.web.admin')->name('preferences');

Route::get('/appearance', function () {
    return view('appearance');
})->middleware('hcm.web.admin')->name('appearance');

Route::get('/language', function () {
    return view('language');
})->middleware('hcm.web.global-admin')->name('language');

Route::get('/language-web', function () {
    return view('language-web');
})->middleware('hcm.web.global-admin')->name('language-web');

Route::get('/add-language', function () {
    return view('add-language');
})->middleware('hcm.web.global-admin')->name('add-language');

Route::get('/authentication-settings', function () {
    return view('authentication-settings');
})->middleware('hcm.web.global-admin')->name('authentication-settings');

Route::get('/ai-settings', function () {
    return view('ai-settings');
})->middleware('hcm.web.global-admin')->name('ai-settings');

Route::get( '/salary-settings', function () {
    return view('salary-settings');
})->middleware('hcm.web.admin')->name('salary-settings');

Route::get( '/approval-settings', function () {
    return view('approval-settings');
})->middleware('hcm.web.admin')->name('approval-settings');

Route::get( '/invoice-settings', function () {
    return view('invoice-settings');
})->middleware('hcm.web.admin')->name('invoice-settings');

Route::get('/leave-type', function () {
    $leaveTypes = HcmLeaveTypeSetting::query()
        ->orderBy('sort_order')
        ->orderBy('id')
        ->get();

    return view('leave-type', ['leaveTypes' => $leaveTypes]);
})->middleware('hcm.web.admin')->name('leave-type');

Route::get( '/custom-fields', function () {
    return view('custom-fields');
})->middleware('hcm.web.admin')->name('custom-fields');

Route::get( '/email-settings', function () {
    return view('email-settings');
})->middleware('hcm.web.global-admin')->name('email-settings');

Route::get( '/email-template', function () {
    return view('email-template');
})->middleware('hcm.web.global-admin')->name('email-template');

Route::get( '/sms-settings', function () {
    return view('sms-settings');
})->middleware('hcm.web.global-admin')->name('sms-settings');

Route::get( '/sms-template', function () {
    return view('sms-template');
})->middleware('hcm.web.global-admin')->name('sms-template');

Route::get( '/otp-settings', function () {
    return view('otp-settings');
})->middleware('hcm.web.global-admin')->name('otp-settings');

Route::get( '/gdpr', function () {
    return view('gdpr');
})->middleware('hcm.web.global-admin')->name('gdpr');

Route::get( '/maintenance-mode', function () {
    return view('maintenance-mode');
})->middleware('hcm.web.global-admin')->name('maintenance-mode');

Route::get( '/payment-gateways', function () {
    return view( 'payment-gateways');
})->middleware('hcm.web.global-admin')->name( 'payment-gateways');

Route::get( '/tax-rates', function () {
    return view( 'tax-rates');
})->middleware('hcm.web.admin')->name( 'tax-rates');

Route::get( '/pages', function () {
    return view( 'pages');
})->middleware('hcm.web.primary-super-admin')->name( 'pages');

Route::get( '/blogs', function () {
    return view( 'blogs');
})->middleware('hcm.web.primary-super-admin')->name( 'blogs');

Route::get( '/blog-categories', function () {
    return view( 'blog-categories');
})->name( 'blog-categories');

Route::get( '/blog-comments', function () {
    return view( 'blog-comments');
})->name( 'blog-comments');

Route::get( '/blog-tags',  function () {
    return view( 'blog-tags');
})->name( 'blog-tags');

Route::get('/countries', [WilayahLocationController::class, 'countries'])->name('countries');

Route::post('/locations/sync', [WilayahLocationController::class, 'sync'])->name('locations.sync');

Route::get('/locations/sync-status', [WilayahLocationController::class, 'syncStatus'])->name('locations.sync-status');

Route::get('/states', [WilayahLocationController::class, 'states'])->name('states');

Route::get('/cities', [WilayahLocationController::class, 'cities'])->name('cities');

Route::get('/villages', [WilayahLocationController::class, 'villages'])->name('villages');

Route::get( '/testimonials', function () {
    return view( 'testimonials');
})->middleware('hcm.web.primary-super-admin')->name( 'testimonials');

Route::get( '/faq', function () {
    return view( 'faq');
})->name( 'faq');

Route::get( '/budget-expenses', function () {
    return view( 'budget-expenses');
})->name( 'budget-expenses');

Route::get( '/budget-revenues', function () {
    return view( 'budget-revenues');
})->name( 'budget-revenues');

Route::get( '/budgets', function () {
    return view( 'budgets');
})->name( 'budgets');

Route::get( '/categories', function () {
    return view( 'categories');
})->name( 'categories');

Route::get( '/taxes', function () {
    return view( 'taxes');
})->name( 'taxes');

Route::get( '/provident-fund', function () {
    return view( 'provident-fund');
})->name( 'provident-fund');

Route::get( '/expenses', function () {
    return view( 'expenses');
})->name( 'expenses');

Route::get( '/shortlist-candidates', function () {
    return view( 'shortlist-candidates');
})->name( 'shortlist-candidates');

Route::get( '/offer-approvals', function () {
    return view( 'offer-approvals');
})->name( 'offer-approvals');

Route::get('/terms-condition', function () {
    return view('terms-condition');
})->name('terms-condition');

Route::get('/privacy-policy', function () {
    return view('privacy-policy');
})->name('privacy-policy');

Route::get('/api-keys', function () {
    return view('api-keys');
})->name('api-keys');

Route::get('/pricing', function () {
    return view('pricing');
})->name('pricing');

Route::get('/timeline', function () {
    return view('timeline');
})->name('timeline');

Route::get('/search-result', function () {
    return view('search-result');
})->name('search-result');

Route::get('/gallery', function () {
    return view('gallery');
})->name('gallery');

Route::get('/profile', function () {
    return view('profile');
})->name('profile');

Route::get('/starter', function () {
    return view('starter');
})->name('starter');

Route::get('/custom-css', function () {
    return view('custom-css');
})->middleware('hcm.web.global-admin')->name('custom-css');

Route::get('/custom-js', function () {
    return view('custom-js');
})->middleware('hcm.web.global-admin')->name('custom-js');

Route::get('/cronjob', [CronjobController::class, 'index'])->middleware('hcm.web.global-admin')->name('cronjob');
Route::post('/cronjob', [CronjobController::class, 'update'])->middleware('hcm.web.global-admin')->name('cronjob.update');

Route::get('/cronjob-schedule', function () {
    return view('cronjob-schedule');
})->middleware('hcm.web.global-admin')->name('cronjob-schedule');

Route::get('/storage-settings', function () {
    return view('storage-settings');
})->middleware('hcm.web.global-admin')->name('storage-settings');

Route::get('/ban-ip-address', function () {
    return view('ban-ip-address');
})->middleware('hcm.web.global-admin')->name('ban-ip-address');

Route::get('/backup', function () {
    return view('backup');
})->middleware('hcm.web.global-admin')->name('backup');

Route::get('/clear-cache', function () {
    return view('clear-cache');
})->middleware('hcm.web.global-admin')->name('clear-cache');

Route::get('/success', function () {
    return view('success');
})->name('success');
Route::get('/success-2', function () {
    return view('success-2');
})->name('success-2');
Route::get('/success-3', function () {
    return view('success-3');
})->name('success-3');



