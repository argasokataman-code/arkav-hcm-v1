<!-- Stacked Sidebar -->
<div class="stacked-sidebar" id="stacked-sidebar">
    <div class="sidebar sidebar-stacked">
        <div class="stacked-mini">
            <a href="{{url('index')}}" class="logo-small">
                <img src="{{URL::asset('build/img/image111.png')}}" alt="Logo">
            </a>
            <div class="sidebar-left slimscroll">
                <div class="d-flex align-items-center flex-column">
                    <div class="mb-1 notification-item">
                        <a href="#" class="btn btn-menubar position-relative">
                            <i class="ti ti-bell"></i>
                            <span class="notification-status-dot"></span>
                        </a>
                    </div>
                    <div class="mb-1">
                        <a href="#" class="btn btn-menubar btnFullscreen">
                            <i class="ti ti-maximize"></i>
                        </a>
                    </div>
                    <div class="mb-1">
                        <a href="{{url('calendar')}}" class="btn btn-menubar">
                            <i class="ti ti-layout-grid-remove"></i>
                        </a>
                    </div>
                    <div class="mb-1">
                        <a href="{{url('email')}}" class="btn btn-menubar">
                            <i class="ti ti-mail"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <div class="sidebar-right d-flex justify-content-between flex-column">
            <div class="sidebar-scroll">
                <h6 class="mb-3">Welcome to SmartHR</h6>
                <div class="sidebar-profile text-center rounded bg-light p-3 mb-4">
                    <div class="avatar avatar-lg online mb-3">
                        <img src="{{URL::asset('build/img/profiles/avatar-02.jpg')}}" alt="Img" class="img-fluid rounded-circle">
                    </div>
                    <h6 class="fs-12 fw-normal mb-1">Adrian Herman</h6>
                    <p class="fs-10">System Admin</p>
                </div>
                <div class="stack-menu">
                    <div class="nav flex-column align-items-center nav-pills" role="tablist" aria-orientation="vertical">
                        <div class="row g-2">
                            <div class="col-6">
                                <a href="#menu-dashboard" role="tab" class="nav-link {{ Request::is('index','employee-dashboard') ? ' show active ' : '' }}" title="Dashboard" data-bs-toggle="tab" data-bs-target="#menu-dashboard" aria-selected="true">
                                    <span><i class="ti ti-smart-home"></i></span>
                                    <p>Dashboard</p>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="#menu-application" role="tab" class="nav-link {{ Request::is('voice-call','video-call','outgoing-call','incoming-call','call-history',
                            'calendar','email','notes','social-feed','invoices') ? ' show active ' : '' }} " title="Apps" data-bs-toggle="tab" data-bs-target="#menu-application" aria-selected="false">
                                    <span><i class="ti ti-layout-grid-add"></i></span>
                                    <p>Applications</p>
                                </a>
                            </div>
                            @if ($isGlobalHcmAdmin)
                            <div class="col-6">
                                <a href="#menu-superadmin" role="tab" class="nav-link {{ Request::is('dashboard','activity','companies','subscription','packages','packages-grid','domain','purchase-transaction','saas/packages','saas/subscriptions','saas/domains','saas/transactions','saas/invoices','saas/payments','saas/reports','saas/reminders','saas/billing-overview','saas/billing-overview/*','saas/renewal-monitoring','saas/pricing*','platform-tax-compliance*','notification-observability','cronjob-schedule','payment-report') ? 'show active' : '' }}" title="Apps" data-bs-toggle="tab" data-bs-target="#menu-superadmin" aria-selected="false">
                                    <span><i class="ti ti-user-star"></i></span>
                                    <p>Super Admin</p>
                                </a>
                            </div>
                            @endif
                            <div class="col-6">
                                <a href="#menu-layout" role="tab" class="nav-link {{ Request::is('layout-horizontal','layout-detached','layout-modern',
                    'layout-two-column','layout-hovered','layout-box','layout-horizontal-single','layout-horizontal-overlay','layout-horizontal-box',
                    'layout-horizontal-sidemenu','layout-vertical-transparent','layout-without-header','layout-rtl','layout-dark') ? 'show active' : '' }}" title="Layout" data-bs-toggle="tab" data-bs-target="#menu-layout" aria-selected="false">
                                    <span><i class="ti ti-layout-board-split"></i></span>
                                    <p>Layouts</p>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="#menu-project" role="tab" class="nav-link {{ Request::is('clients',
                    'employees','employee-details','departments','designations','policy','tickets','tickets-grid','ticket-details','holidays','leaves','leaves-employee','leave-settings','attendance-admin','attendance-employee',
                            'timesheets','schedule-timing','shift-master','overtime-master','overtime','overtime-employee','leaves','leaves-employee','leave-settings','performance-indicator','performance-review','performance-appraisal','goal-tracking','goal-type','training','trainers','training-type','promotion','resignation','termination') ? ' show active ' : '' }}" title="Projects" data-bs-toggle="tab" data-bs-target="#menu-project" aria-selected="false">
                                    <span><i class="ti ti-folder"></i></span>
                                    <p>Projects</p>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="#menu-crm" role="tab" class="nav-link {{ Request::is('contacts-grid','contacts','contact-details','companies-grid','companies-crm','company-details'
                            ) ? 'show active' : '' }}" title="CRM" data-bs-toggle="tab" data-bs-target="#menu-crm" aria-selected="false">
                                    <span><i class="ti ti-user-shield"></i></span>
                                    <p>Crm</p>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="#menu-hrm" role="tab" class="nav-link {{ Request::is('employees','employee-details','departments','designations','policy','tickets','tickets-grid','ticket-details','holidays',
                        'leaves','leaves-employee','leave-settings','attendance-admin','attendance-employee',
                            'timesheets','schedule-timing','shift-master','overtime-master','overtime','overtime-employee','performance-indicator','performance-review','performance-appraisal','goal-tracking','goal-type','training','trainers','training-type','promotion','resignation','termination') ? ' show active ' : '' }}" title="HRM" data-bs-toggle="tab" data-bs-target="#menu-hrm" aria-selected="false">
                                    <span><i class="ti ti-users"></i></span>
                                    <p>Hrm</p>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="#menu-finance" role="tab" class="nav-link {{Request::is('estimates','invoices','payments','expenses','provident-fund','taxes','categories','budgets','budget-expenses','budget-revenues','employee-salary','payslip','payroll','payroll-overtime','payroll-deduction','payroll-thr') ? ' show active ' : '' }}" title="Finance & Accounts" data-bs-toggle="tab" data-bs-target="#menu-finance" aria-selected="false">
                                    <span><i class="ti ti-shopping-cart-dollar"></i></span>
                                    <p>Finance & Accounts</p>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="#menu-administration" role="tab" class="nav-link {{ Request::is('assets','asset-categories','knowledgebase','knowledgebase/*','users','roles-permissions',
                        'expenses-report','invoice-report','payment-report','user-report','employee-report','payslip-report','attendance-report','leave-report','daily-report',
                        'profile-settings','company-profile','security-settings','notification-settings','business-settings','seo-settings','localization-settings','prefixes','preferences','performance-appraisal','language','authentication-settings','ai-settings',
                            'approval-settings','invoice-settings','leave-type','email-settings','custom-css','custom-js','cronjob') ? 'show active ' : '' }}" title="Administration" data-bs-toggle="tab" data-bs-target="#menu-administration" aria-selected="false">
                                    <span><i class="ti ti-cash"></i></span>
                                    <p>Administration</p>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="#menu-content" role="tab" class="nav-link {{ Request::is('pages','blogs','blog-categories','blog-comments','blog-tags','countries','states','cities','villages','testimonials','faq') ? '  active subdrop' : '' }}" title="Content" data-bs-toggle="tab" data-bs-target="#menu-content" aria-selected="false">
                                    <span><i class="ti ti-license"></i></span>
                                    <p>Contents</p>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="#menu-pages" role="tab" class="nav-link {{ Request::is('starter','profile','gallery','search-result','timeline','coming-soon','under-maintenance','under-construction','api-keys','privacy-policy','terms-condition') ? '  active subdrop' : '' }}" title="Pages"
                                    data-bs-toggle="tab" data-bs-target="#menu-pages" aria-selected="false">
                                    <span><i class="ti ti-page-break"></i></span>
                                    <p>Pages</p>
                                </a>
                            </div>
                            @if ($showTemplateCatalogMenus)
                            <div class="col-6">
                                <a href="#menu-authentication" role="tab" class="nav-link {{ Request::is('login','login-2','login-3','register','register-2','register-3',
                    'forgot-password','forgot-password-2','forgot-password-3','reset-password','reset-password-2','reset-password-3','email-verification','email-verification-2','email-verification-3',
                   'two-step-verification','two-step-verification-2','two-step-verification-3','lock-screen','error-404','error-500' ) ? ' show active' : '' }} " title="Authentication" data-bs-toggle="tab" data-bs-target="#menu-authentication" aria-selected="false">
                                    <span><i class="ti ti-lock-check"></i></span>
                                    <p>Authentication</p>
                                </a>
                            </div>
                            @endif
                            <div class="col-6">
                                <a href="#menu-ui-elements" role="tab" class="nav-link {{ Request::is('ui-alerts',
                                'ui-accordion',
                                'ui-avatar',
                                'ui-badges',
                                'ui-borders',
                                'ui-buttons',
                                'ui-buttons-group',
                                'ui-breadcrumb',
                                'ui-cards',
                                'ui-carousel',
                                'ui-colors',
                                'ui-dropdowns',
                                'ui-grid',
                                'ui-images',
                                'ui-lightbox',
                                'ui-media',
                                'ui-modals',
                                'ui-offcanvas',
                                'ui-pagination',
                                'ui-popovers',
                                'ui-progress',
                                'ui-placeholders',
                                'ui-spinner',
                                'ui-sweetalerts',
                                'ui-nav-tabs',
                                'ui-toasts',
                                'ui-tooltips',
                                'ui-typography',
                                'ui-video',
                                'ui-sortable',
                                'ui-swiperjs',
                                'ui-ribbon','ui-clipboard','ui-drag-drop',
                                'ui-rangeslider','ui-rating','ui-text-editor','ui-counter','ui-scrollbar','ui-stickynote','ui-timeline',
                                'form-basic-inputs',
                                'form-checkbox-radios',
                                'form-input-groups',
                                'form-grid-gutters',
                                'form-select',
                                'form-mask',
                                'form-fileupload',
                                'form-horizontal',
                                'form-vertical',
                                'form-floating-labels',
                                'form-validation',
                                'form-select2',
                                'form-wizard',
                                'form-pickers',
                                'tables-basic',
                                'data-tables',
                                'chart-apex','chart-c3','chart-js','chart-morris','chart-flot','chart-peity',
                                'icon-fontawesome','icon-tabler','icon-bootstrap',
                                'icon-remix','icon-feather','icon-ionic','icon-material','icon-pe7','icon-simpleline','icon-themify','icon-ionic',
                                'icon-material','icon-pe7','icon-simpleline','icon-themify','icon-weather','icon-typicon','icon-flag'
                                

                                ) ? ' show active ' : '' }}" title="UI Elements" data-bs-toggle="tab" data-bs-target="#menu-ui-elements" aria-selected="false">
                                    <span><i class="ti ti-ux-circle"></i></span>
                                    <p>Basic UI</p>
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="tab-content">
                        <div class="tab-pane fade {{ Request::is('index','employee-dashboard') ? ' show active ' : '' }}"  id="menu-dashboard">
                            <ul class="stack-submenu">
                                <li><a href="{{url('index')}}" class="{{ Request::is('index') ? 'active' : '' }}">Admin Dashboard</a></li>
                        <li><a href="{{url('employee-dashboard')}}" class="{{ Request::is('employee-dashboard') ? 'active' : '' }}">Employee Dashboard</a></li>
                            </ul>
                        </div>
@if ($isGlobalHcmAdmin)
                        <div class="tab-pane fade {{ Request::is('dashboard','activity','companies','subscription','packages','packages-grid','domain','purchase-transaction','saas/packages','saas/subscriptions','saas/domains','saas/transactions','saas/invoices','saas/payments','saas/reports','saas/reminders','saas/billing-overview','saas/billing-overview/*','saas/renewal-monitoring','saas/pricing*','platform-tax-compliance*','notification-observability','cronjob-schedule','payment-report') ? ' show active ' : '' }}" id="menu-superadmin">
                            <ul class="stack-submenu">
                        <li><a href="{{url('dashboard')}}" class="{{ Request::is('dashboard') ? 'active' : '' }}">Dashboard</a></li>
@if ($isPrimarySuperAdmin)
                        <li><a href="{{url('activity')}}" class="{{ Request::is('activity') ? 'active' : '' }}">Activities</a></li>
@endif
                        <li><a href="{{url('companies')}}"  class="{{ Request::is('companies') ? 'active' : '' }}">Companies</a></li>
                        <li><a href="{{url('saas/billing-overview')}}" class="{{ Request::is('saas/billing-overview','saas/billing-overview/*') ? 'active' : '' }}">Trial & Billing Dashboard</a></li>
                        <li><a href="{{url('saas/renewal-monitoring')}}" class="{{ Request::is('saas/renewal-monitoring') ? 'active' : '' }}">Renewal Monitoring</a></li>
                        <li><a href="{{url('saas/subscriptions')}}" class="{{ Request::is('saas/subscriptions') ? 'active' : '' }}">Subscriptions</a></li>
                        <li><a href="{{ route('super-admin.package-compliance') }}" class="{{ Request::is('super-admin/package-compliance') ? 'active' : '' }}">Package Compliance</a></li>
                        <li><a href="{{url('packages')}}" class="{{ Request::is('packages','packages-grid','saas/packages') ? 'active' : '' }}">Packages</a></li>
                        <li><a href="{{url('domain')}}" class="{{ Request::is('domain','saas/domains') ? 'active' : '' }}">Domain</a></li>
                        <li><a href="{{url('purchase-transaction')}}" class="{{ Request::is('purchase-transaction','saas/transactions') ? 'active' : '' }}">Purchase Transaction</a></li>
                        <li><a href="{{url('saas/invoices')}}" class="{{ Request::is('saas/invoices') ? 'active' : '' }}">SaaS Invoices</a></li>
                        <li><a href="{{url('saas/payments')}}" class="{{ Request::is('saas/payments') ? 'active' : '' }}">SaaS Payments</a></li>
                        <li><a href="{{url('saas/reports')}}" class="{{ Request::is('saas/reports') ? 'active' : '' }}">SaaS Reports</a></li>
                        <li><a href="{{url('saas/reminders')}}" class="{{ Request::is('saas/reminders') ? 'active' : '' }}">SaaS Reminders</a></li>
                        <li><a href="{{ route('saas.pricing') }}" class="{{ Request::is('saas/pricing*') ? 'active' : '' }}">Pricing & Plans</a></li>
                        <li><a href="{{ route('platform-tax-compliance.policies') }}" class="{{ Request::is('platform-tax-compliance*') ? 'active' : '' }}">Platform Tax Compliance Settings</a></li>
                        @include('layout.partials.sidebar.sections.shared.notification-observability-link')
                        <li><a href="{{url('cronjob-schedule')}}" class="{{ Request::is('cronjob-schedule') ? 'active' : '' }}">Cronjob Schedule</a></li>
                        <li><a href="{{url('payment-report')}}" class="{{ Request::is('payment-report') ? 'active' : '' }}">Payment Report</a></li>
