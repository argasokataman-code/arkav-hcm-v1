<!-- Header -->
@php
    $authUser = request()->user() ?: auth()->user();
    $authUser?->loadMissing('employeeProfile:id,user_id,profile_photo_path');
    $primarySuperAdminEmail = strtolower(trim((string) config('hcm.admin_email', 'qa.login@example.com')));
    $secondarySuperAdminEmail = strtolower(trim((string) config('hcm.secondary_admin_email', 'qa.hcm@example.com')));
    $authUserEmail = strtolower(trim((string) ($authUser->email ?? '')));
    $isPrimarySuperAdmin = $authUser && $authUserEmail === $primarySuperAdminEmail;
    $showTemplateCatalogMenus = $isPrimarySuperAdmin;
    $isHcmAdmin = (bool) ($authUser?->isHcmAdmin());
    $isGlobalHcmAdmin = (bool) ($authUser?->isGlobalHcmAdmin());
    $activeCompany = request()->attributes->get('activeCompany');
    $activeCompanyRole = strtolower(trim((string) request()->attributes->get('activeCompanyRole', '')));
    if ($activeCompanyRole === '' && $activeCompany instanceof \App\Models\Company && $authUser) {
        $activeCompanyRole = strtolower((string) (\App\Models\CompanyUser::query()
            ->where('company_id', $activeCompany->id)
            ->where('user_id', $authUser->id)
            ->value('role') ?? ''));
    }
    if ($activeCompanyRole === '' && $authUser) {
        $activeCompanyRole = strtolower((string) (\App\Models\CompanyUser::query()
            ->where('user_id', $authUser->id)
            ->where('status', 'active')
            ->orderByDesc('id')
            ->value('role') ?? ''));
    }
    $isEmployeeScopedUser = in_array($activeCompanyRole, ['employee', 'member'], true);
    $activeCompanySubscription = $activeCompany instanceof \App\Models\Company
        ? $activeCompany->activeSubscription()
        : null;
    $activeCompanyIdentifier = $activeCompany instanceof \App\Models\Company
        ? ((string) ($activeCompany->uuid ?? '') !== '' ? (string) $activeCompany->uuid : (string) ((int) ($activeCompany->id ?? 0)))
        : null;
    $hasTraining = (bool) ($activeCompanySubscription?->package?->hasFeature('training') ?? false);
    $hasTickets = (bool) ($activeCompanySubscription?->package?->hasFeature('tickets') ?? false);
    $hasEmployeeManagement = (bool) ($activeCompanySubscription?->package?->hasFeature('employee_management') ?? false);
    $hasAttendance = (bool) ($activeCompanySubscription?->package?->hasFeature('attendance') ?? false);
    $hasLeaveManagement = (bool) ($activeCompanySubscription?->package?->hasFeature('leave_management') ?? false);
    $hasHolidayCalendar = (bool) ($activeCompanySubscription?->package?->hasFeature('holiday_calendar') ?? false);
    $hasEmployeeLifecycle = (bool) ($activeCompanySubscription?->package?->hasFeature('employee_lifecycle') ?? false);
    $hasPerformance = (bool) ($activeCompanySubscription?->package?->hasFeature('performance') ?? false);
    $isInTenantContext = $activeCompany instanceof \App\Models\Company;
    // Global super admin bypass applies only outside tenant context.
    $featureBypass = $isGlobalHcmAdmin && !$isInTenantContext;
    $canManageTrainingMenu = $featureBypass || ($hasTraining && (bool) ($authUser?->hasPermissionForCompany('training.manage', $activeCompanyIdentifier)));
    $canViewTrainingMenu = $canManageTrainingMenu || ($hasTraining && (bool) ($authUser?->hasPermissionForCompany('training.view', $activeCompanyIdentifier)));
    $hasDocumentCenter = (bool) ($activeCompanySubscription?->package?->hasFeature('employee_document_center') ?? false);
    $canManageDocumentCenterMenu = $isGlobalHcmAdmin || $featureBypass || ($hasDocumentCenter && (bool) ($authUser?->hasPermissionForCompany('document_center.manage', $activeCompanyIdentifier)));
    $canViewDocumentCenterMenu = $canManageDocumentCenterMenu || ($hasDocumentCenter && (bool) ($authUser?->hasPermissionForCompany('document_center.view', $activeCompanyIdentifier)));
    $canSeeTicketsMenu = $featureBypass || $hasTickets;
    $canSeeEmployeesMenu = $featureBypass || $hasEmployeeManagement || $hasDocumentCenter || $hasEmployeeLifecycle;
    $canSeeAttendanceMenu = $featureBypass || $hasAttendance || $hasLeaveManagement;
    $canSeeHolidaysMenu = $featureBypass || ($hasHolidayCalendar && $isHcmAdmin);
    $canSeeEmployeeLifecycleMenu = $featureBypass || ($hasEmployeeLifecycle && $isHcmAdmin);
    $canSeePerformanceMenu = $featureBypass || ($hasPerformance && !$isEmployeeScopedUser && $isHcmAdmin);
    $hasPayroll = (bool) ($activeCompanySubscription?->package?->hasFeature('payroll') ?? false);
    $canSeePayrollMenu = $featureBypass || ($hasPayroll && !$isEmployeeScopedUser && ($isHcmAdmin || $isGlobalHcmAdmin));
    $hasAssetManagement = (bool) ($activeCompanySubscription?->package?->hasFeature('asset_management') ?? false);
    $isQaSuperAdmin = $authUser
        && (
            strtolower(trim((string) ($authUser->email ?? ''))) === strtolower(trim((string) config('hcm.admin_email', 'qa.login@example.com')))
            || (bool) ($authUser->is_super_admin ?? false)
        );
    $canSeeAssetManagementMenu = $featureBypass || ($hasAssetManagement && !$isEmployeeScopedUser && $isHcmAdmin);
    $headerProfileName = trim((string) ($authUser->name ?? 'User')) ?: 'User';
    $headerProfileEmail = trim((string) ($authUser->email ?? '')) ?: 'user@example.com';
    $headerProfilePhotoPath = trim((string) ($authUser->employeeProfile->profile_photo_path ?? ''));
    $headerProfilePhotoUrl = $headerProfilePhotoPath !== ''
        ? asset('storage/'.ltrim($headerProfilePhotoPath, '/'))
        : URL::asset('build/img/profiles/avatar-12.jpg');
    $headerActiveCompanyName = trim((string) ($activeCompany?->name ?? ''));
    $headerActiveCompanyCode = trim((string) ($activeCompany?->code ?? ''));
    $headerRoleBadge = strtoupper($activeCompanyRole !== '' ? $activeCompanyRole : 'member');
    $headerPackageCode = strtoupper(trim((string) ($activeCompanySubscription?->package?->code ?? $activeCompanySubscription?->plan_code ?? '')));
    $headerPackageName = trim((string) ($activeCompanySubscription?->package?->name ?? ''));
    $headerPackageIsInternal = (bool) ($activeCompanySubscription?->package?->is_global_admin_only ?? false);
    $headerPackageBadgeClass = $headerPackageIsInternal ? 'bg-dark text-white' : 'bg-light text-dark border';
@endphp
<div class="header">
    <div class="main-header">

        <div class="header-left">
            <a href="{{url('index')}}" class="logo">
                <img src="{{URL::asset('build/img/image111.png')}}" alt="Logo">
            </a>
            <a href="{{url('index')}}" class="dark-logo">
                <img src="{{URL::asset('build/img/logo-white.svg')}}" alt="Logo">
            </a>
        </div>

        <a id="mobile_btn" class="mobile_btn" href="#sidebar">
            <span class="bar-icon">
                <span></span>
                <span></span>
                <span></span>
            </span>
        </a>

        <div class="header-user">
            <div class="nav user-menu nav-list">

                <div class="me-auto d-flex align-items-center" id="header-search">
                    <a id="toggle_btn" href="javascript:void(0);" class="btn btn-menubar me-1">
                        <i class="ti ti-arrow-bar-to-left"></i>
                    </a>
                    <!-- Search -->
                    <div class="input-group input-group-flat d-inline-flex me-1" data-hcm-global-search>
                        <span class="input-icon-addon">
                            <i class="ti ti-search"></i>
                        </span>
                        <input type="text" class="form-control" placeholder="Search in HRMS" autocomplete="off" data-hcm-global-search-input>
                        <span class="input-group-text">
                            <kbd>CTRL + / </kbd>
                        </span>
                    </div>
                    <!-- /Search -->
                    <div class="dropdown crm-dropdown">
                        <a href="#" class="btn btn-menubar me-1" data-bs-toggle="dropdown">
                            <i class="ti ti-layout-grid"></i>
                        </a>
                        <div class="dropdown-menu dropdown-lg dropdown-menu-start">
                            <div class="card mb-0 border-0 shadow-none">
                                <div class="card-header">
                                    <h4>CRM</h4>
                                </div>
                                <div class="card-body pb-1">		
                                    <div class="row">
                                        <div class="col-sm-6">							
                                            <a href="{{url('contacts')}}" class="d-flex align-items-center justify-content-between p-2 crm-link mb-3">
                                                <span class="d-flex align-items-center me-3">
                                                    <i class="ti ti-user-shield text-default me-2"></i>Contacts
                                                </span>
                                                <i class="ti ti-arrow-right"></i>
                                            </a>							
                                        </div>
                                        <div class="col-sm-6">							
                                            <a href="{{url('companies-grid')}}" class="d-flex align-items-center justify-content-between p-2 crm-link mb-3">
                                                <span class="d-flex align-items-center me-3">
                                                    <i class="ti ti-building text-default me-2"></i>Companies
                                                </span>
                                                <i class="ti ti-arrow-right"></i>
                                            </a>								
                                        </div>
                                    </div>		
                                </div>
                            </div>
                        </div>
                    </div>
                    <a href="{{url('profile-settings')}}" class="btn btn-menubar">
                        <i class="ti ti-settings-cog"></i>
                    </a>	
                </div>

                <!-- Horizontal Single -->
                <div class="sidebar sidebar-horizontal" id="horizontal-single">
                    <div class="sidebar-menu">
                        <div class="main-menu">
                            <ul class="nav-menu">
                                <li class="menu-title">
                                    <span>Main</span>
                                </li>
                                <li class="submenu">
                                    <a href="#" class="{{ Request::is('index','employee-dashboard') ? 'active subdrop' : '' }}">
                                        <i class="ti ti-smart-home"></i><span>Dashboard</span>
                                        <span class="menu-arrow"></span>
                                    </a>
                                    <ul>
                                        <li><a href="{{url('index')}}" class="{{ Request::is('index') ? 'active' : '' }}">Admin Dashboard</a></li>
                                        <li><a href="{{url('employee-dashboard')}}" class="{{ Request::is('employee-dashboard') ? 'active' : '' }}">Employee Dashboard</a></li>
                                    </ul>
                                </li>
@if ($isGlobalHcmAdmin)
                                <li class="submenu">
                                    <a href="#" class="{{ Request::is('dashboard','companies','subscription','packages','packages-grid','domain','purchase-transaction','saas/packages','saas/subscriptions','saas/domains','saas/transactions','saas/invoices','saas/payments','saas/reports','saas/reminders','saas/billing-overview','saas/billing-overview/*','saas/pricing*','platform-tax-compliance*','notification-observability','cronjob-schedule','payment-report') ? 'active subdrop' : '' }}">
                                        <i class="ti ti-user-star"></i><span>Super Admin</span>
                                        <span class="menu-arrow"></span>
                                    </a>
                                    <ul>
                                        <li><a href="{{url('dashboard')}}" class="{{ Request::is('dashboard') ? 'active' : '' }}">Dashboard</a></li>
                        <li><a href="{{url('companies')}}"  class="{{ Request::is('companies') ? 'active' : '' }}">Companies</a></li>
                        <li><a href="{{url('saas/billing-overview')}}" class="{{ Request::is('saas/billing-overview','saas/billing-overview/*') ? 'active' : '' }}">Trial & Billing</a></li>
                        <li><a href="{{url('saas/subscriptions')}}" class="{{ Request::is('saas/subscriptions') ? 'active' : '' }}">Subscriptions</a></li>
                        <li><a href="{{url('packages')}}" class="{{ Request::is('packages','packages-grid','saas/packages') ? 'active' : '' }}">Packages</a></li>
                        <li><a href="{{url('domain')}}" class="{{ Request::is('domain','saas/domains') ? 'active' : '' }}">Domain</a></li>
                        <li><a href="{{url('purchase-transaction')}}" class="{{ Request::is('purchase-transaction','saas/transactions') ? 'active' : '' }}">Purchase Transaction</a></li>
                        <li><a href="{{url('saas/invoices')}}" class="{{ Request::is('saas/invoices') ? 'active' : '' }}">SaaS Invoices</a></li>
                        <li><a href="{{url('saas/payments')}}" class="{{ Request::is('saas/payments') ? 'active' : '' }}">SaaS Payments</a></li>
                        <li><a href="{{url('saas/reports')}}" class="{{ Request::is('saas/reports') ? 'active' : '' }}">SaaS Reports</a></li>
                        <li><a href="{{url('saas/reminders')}}" class="{{ Request::is('saas/reminders') ? 'active' : '' }}">SaaS Reminders</a></li>
                        <li><a href="{{ route('saas.pricing') }}" class="{{ Request::is('saas/pricing*') ? 'active' : '' }}">Pricing & Plans</a></li>
                        <li><a href="{{ route('platform-tax-compliance.policies') }}" class="{{ Request::is('platform-tax-compliance*') ? 'active' : '' }}">Platform Tax & Compliance</a></li>
                        <li><a href="{{url('notification-observability')}}" class="{{ Request::is('notification-observability') ? 'active' : '' }}">Notification Observability</a></li>
                        <li><a href="{{url('cronjob-schedule')}}" class="{{ Request::is('cronjob-schedule') ? 'active' : '' }}">Cronjob Schedule</a></li>
                        <li><a href="{{url('payment-report')}}" class="{{ Request::is('payment-report') ? 'active' : '' }}">Payment Report</a></li>
                                    </ul>
                                </li>
@endif
                                <li class="submenu">
                                        <a href="#" class="{{ Request::is('chat','voice-call','video-call','outgoing-call','incoming-call','call-history',
                                    'calendar','email','notes','social-feed','file-manager','invoices','invoice-details') ? 'active subdrop' : '' }}">
                                        <i class="ti ti-layout-grid-add"></i><span>Applications</span>
                                        <span class="menu-arrow"></span>
                                    </a>
                                    <ul>
                                        <li><a href="{{url('chat')}}" class="{{ Request::is('chat') ? 'active' : '' }}">Chat</a></li>
                                        <li class="submenu submenu-two">
                                            <a href="{{url('call')}}" class="{{ Request::is('voice-call','video-call','outgoing-call','incoming-call','call-history') ? 'active subdrop' : '' }}">Calls<span
                                                    class="menu-arrow inside-submenu"></span></a>
                                            <ul>
                                                <li><a href="{{url('voice-call')}}" class="{{ Request::is('voice-call') ? 'active' : '' }}">Voice Call</a></li>
                                <li><a href="{{url('video-call')}}" class="{{ Request::is('video-call') ? 'active' : '' }}">Video Call</a></li>
                                <li><a href="{{url('outgoing-call')}}"  class="{{ Request::is('outgoing-call') ? 'active' : '' }}">Outgoing Call</a></li>
                                <li><a href="{{url('incoming-call')}}" class="{{ Request::is('incoming-call') ? 'active' : '' }}">Incoming Call</a></li>
                                <li><a href="{{url('call-history')}}"  class="{{ Request::is('call-history') ? 'active' : '' }}">Call History</a></li>
                                            </ul>
                                        </li>
                                        <li><a href="{{url('calendar')}}" class="{{ Request::is('calendar') ? 'active' : '' }}">Calendar</a></li>
                        <li><a href="{{url('email')}}" class="{{ Request::is('email') ? 'active' : '' }}">Email</a></li>
                        <li><a href="{{url('notes')}}" class="{{ Request::is('notes') ? 'active' : '' }}">Notes</a></li>
                        <li><a href="{{url('social-feed')}}" class="{{ Request::is('social-feed') ? 'active' : '' }}">Social Feed</a></li>
                        <li><a href="{{url('file-manager')}}" class="{{ Request::is('file-manager') ? 'active' : '' }}">File Manager</a></li>
                        <li><a href="{{url('invoices')}}" class="{{ Request::is('invoices','invoice-details') ? 'active' : '' }}">Invoices</a></li>
                                    </ul>
                                </li>
                                <li class="submenu">
                                    <a href="#" class="{{ Request::is('layout-horizontal','layout-detached','layout-modern',
                                    'layout-two-column','layout-hovered','layout-box','layout-horizontal-single','layout-horizontal-overlay','layout-horizontal-box',
                                    'layout-horizontal-sidemenu','layout-vertical-transparent','layout-without-header','layout-rtl','layout-dark') ? 'active subdrop' : '' }}">
                                        <i class="ti ti-layout-board-split"></i><span>Layouts</span>
                                        <span class="menu-arrow"></span>
                                    </a>
                                    <ul>
                                        <li>
                                            <a href="{{url('layout-horizontal')}}" class="{{ Request::is('layout-horizontal') ? 'active' : '' }}">
                                                <span>Horizontal</span>
                                            </a>
                                        </li>
                                        <li >
                                            <a href="{{url('layout-detached')}}" class="{{ Request::is('layout-detached') ? 'active' : '' }}">
                                                <span>Detached</span>
                                            </a>
                                        </li>
                                        <li >
                                            <a href="{{url('layout-modern')}}" class="{{ Request::is('layout-modern') ? 'active' : '' }}">
                                                <span>Modern</span>
                                            </a>
                                        </li>
                                        <li >
                                            <a href="{{url('layout-two-column')}}" class="{{ Request::is('layout-two-column') ? 'active' : '' }}">
                                                <span>Two Column </span>
                                            </a>
                                        </li>
                                        <li>
                                            <a href="{{url('layout-hovered')}}" class="{{ Request::is('layout-hovered') ? 'active' : '' }}">
                                                <span>Hovered</span>
                                            </a>
                                        </li>
                                        <li>
                                            <a href="{{url('layout-box')}}"  class="{{ Request::is('layout-box') ? 'active' : '' }}">
                                                <span>Boxed</span>
                                            </a>
                                        </li>
                                        <li >
                                            <a href="{{url('layout-horizontal-single')}}" class="{{ Request::is('layout-horizontal-single') ? 'active' : '' }}">
                                                <span>Horizontal Single</span>
                                            </a>
                                        </li>
                                        <li>
                                            <a href="{{url('layout-horizontal-overlay')}}" class="{{ Request::is('layout-horizontal-overlay') ? 'active' : '' }}">
                                                <span>Horizontal Overlay</span>
                                            </a>
                                        </li>
                                        <li>
                                            <a href="{{url('layout-horizontal-box')}}" class="{{ Request::is('layout-horizontal-box') ? 'active' : '' }}">
                                                <span>Horizontal Box</span>
                                            </a>
                                        </li>
                                        <li>
                                            <a href="{{url('layout-horizontal-sidemenu')}}" class="{{ Request::is('layout-horizontal-sidemenu') ? 'active' : '' }}">
                                                <span>Menu Aside</span>
                                            </a>
                                        </li>
                                        <li>
                                            <a href="{{url('layout-vertical-transparent')}}" class="{{ Request::is('layout-vertical-transparent') ? 'active' : '' }}">
                                                <span>Transparent</span>
                                            </a>
                                        </li>
                                        <li>
                                            <a href="{{url('layout-without-header')}}" class="{{ Request::is('layout-without-header') ? 'active' : '' }}">
                                                <span>Without Header</span>
                                            </a>
                                        </li>
                                        <li>
                                            <a href="{{url('layout-rtl')}}" class="{{ Request::is('layout-rtl') ? 'active' : '' }}">
                                                <span>RTL</span>
                                            </a>
                                        </li>
                                        <li> 
                                            <a href="{{url('layout-dark')}}" class="{{ Request::is('layout-dark') ? 'active' : '' }}">
                                                <span>Dark</span>
                                            </a>
                                        </li>
                                    </ul>
                                </li>
                                <li class="submenu">
                                        <a href="#" class="{{ Request::is('clients-grid',
                                        'contacts-grid','contacts','contact-details','companies-grid','companies-crm','company-details',
                                     'employees','employee-details','departments','designations','policy','tickets','tickets-grid','ticket-details','holidays','leaves','leaves-employee','leave-settings','attendance-admin','attendance-employee',
                                             'timesheets','schedule-timing','shift-master','overtime-master','overtime','overtime-employee','leaves','leaves-employee','leave-settings','performance-indicator','performance-review','performance-appraisal','goal-tracking','goal-type','training','trainers','training-type','promotion','resignation','termination') ? 'active ' : '' }}">
                                        <i class="ti ti-user-star"></i><span>Projects</span>
                                        <span class="menu-arrow"></span>
                                    </a>
                                    <ul>
                                        <li>
                                            <a href="{{url('clients-grid')}}" class="{{ Request::is('clients-grid') ? 'active' : '' }}"><span>Clients</span>
                                            </a>
                                        </li>
                                        <li class="submenu">
                                            <a href="{{url('call')}}" class="{{ Request::is('contacts-grid','contacts','contact-details','companies-grid','companies-crm','company-details') ? 'active subdrop' : '' }}">Crm<span class="menu-arrow"></span></a>
                                            <ul>
                                                <li><a href="{{url('contacts-grid')}}" class="{{ Request::is('contacts-grid','contacts','contact-details') ? 'active' : '' }}"><span>Contacts</span></a></li>
                                                <li><a href="{{url('companies-grid')}}" class="{{ Request::is('companies-grid','companies-crm','company-details') ? 'active' : '' }}"><span>Companies</span></a></li>
                                            </ul>
                                        </li>
@if ($canSeeEmployeesMenu)
                                        <li class="submenu">
                                            <a href="javascript:void(0);"  class="{{ Request::is('employees','employee-details','departments','designations','policy') ? 'active subdrop' : '' }}"><span>Employees</span>
                                                <span class="menu-arrow"></span>
                                            </a>
                                            <ul>
                                                <li><a href="{{url('employees')}}" class="{{ Request::is('employees') ? 'active' : '' }}">Employee Lists</a></li>
                                                                                                <li><a href="{{url('employee-details')}}" class="{{ Request::is('employee-details') ? 'active' : '' }}">Employee Details</a></li>
                                                <li><a href="{{url('departments')}}" class="{{ Request::is('departments') ? 'active' : '' }}">Departments</a></li>
                                                <li><a href="{{url('designations')}}" class="{{ Request::is('designations') ? 'active' : '' }}">Designations</a></li>
                                                <li><a href="{{url('policy')}}" class="{{ Request::is('policy') ? 'active' : '' }}">Policies</a></li>
                                            </ul>
                                        </li>
@endif
@if ($canSeeTicketsMenu)
                                        <li class="submenu">
                                            <a href="javascript:void(0);" class="{{ Request::is('ticket-master','tickets-admin','tickets-employee','tickets-grid','ticket-details*') ? 'active subdrop' : '' }}"><span>Tickets</span>
                                                <span class="menu-arrow"></span>
                                            </a>
                                            <ul>
                                                <li><a href="{{url('ticket-master')}}" class="{{ Request::is('ticket-master') ? 'active' : '' }}">Master Ticket</a></li>
                                                <li><a href="{{url('tickets-admin')}}" class="{{ Request::is('tickets-admin','tickets-grid') ? 'active' : '' }}">Ticket (Admin)</a></li>
                                                <li><a href="{{url('tickets-employee')}}" class="{{ Request::is('tickets-employee') ? 'active' : '' }}">Ticket (Employee)</a></li>

                                            </ul>
                                        </li>
@endif
@if ($canSeeHolidaysMenu)
                                        <li class="{{ Request::is('holidays') ? 'active' : '' }}"><a href="{{url('holidays')}}"><span>Holidays</span></a></li>
@endif
@if ($canSeeAttendanceMenu)
                                        <li class="submenu">
                                            <a href="javascript:void(0);" class="{{ Request::is('leaves','leaves-employee','leave-settings','attendance-admin','attendance-employee',
                            'timesheets','schedule-timing','shift-master','overtime-master','overtime','overtime-employee') ? 'active subdrop' : '' }}"><span>Attendance</span>
                                                <span class="menu-arrow"></span>
                                            </a>
                                            <ul>
                                                <li class="submenu">
                                                    <a href="javascript:void(0);" class="{{ Request::is('leaves','leaves-employee','leave-settings') ? 'active subdrop' : '' }}">Leaves<span class="menu-arrow"></span></a>
                                                    <ul>
                                                        <li><a href="{{url('leaves')}}" class="{{ Request::is('leaves') ? 'active' : '' }}" >Leaves (Admin)</a></li>
                                        <li><a href="{{url('leaves-employee')}}" class="{{ Request::is('leaves-employee') ? 'active' : '' }}">Leave (Employee)</a></li>
                                        <li><a href="{{url('leave-settings')}}" class="{{ Request::is('leave-settings') ? 'active' : '' }}">Leave Settings</a></li>												
                                                    </ul>												
                                                </li>
                                                <li><a href="{{url('attendance-admin')}}" class="{{ Request::is('attendance-admin') ? 'active' : '' }}">Attendance (Admin)</a></li>
                                                <li><a href="{{url('attendance-employee')}}" class="{{ Request::is('attendance-employee') ? 'active' : '' }}">Attendance (Employee)</a></li>
                                                <li><a href="{{url('timesheets')}}" class="{{ Request::is('timesheets') ? 'active' : '' }}">Timesheets</a></li>
                                                <li><a href="{{url('schedule-timing')}}" class="{{ Request::is('schedule-timing') ? 'active' : '' }}">Shift & Schedule</a></li>
                                                <li><a href="{{url('shift-master')}}" class="{{ Request::is('shift-master') ? 'active' : '' }}">Master Shift</a></li>
                                                <li class="submenu">
                                                    <a href="javascript:void(0);" class="{{ Request::is('overtime-master','overtime','overtime-employee') ? 'active subdrop' : '' }}">Overtime<span class="menu-arrow"></span></a>
                                                    <ul>
                                                        <li><a href="{{url('overtime-master')}}" class="{{ Request::is('overtime-master') ? 'active' : '' }}">Master Overtime</a></li>
                                                        <li><a href="{{url('overtime')}}" class="{{ Request::is('overtime') ? 'active' : '' }}">Overtime (Admin)</a></li>
                                                        <li><a href="{{url('overtime-employee')}}" class="{{ Request::is('overtime-employee') ? 'active' : '' }}">Overtime (Employee)</a></li>
                                                    </ul>
                                                </li>
                
                                            </ul>
                                        </li>
                @endif
                @if ($canSeePerformanceMenu)
                                                        <li class="submenu">
                                            <a href="javascript:void(0);" class="{{ Request::is('performance-indicator','performance-review','performance-appraisal','goal-tracking','goal-type') ? 'active subdrop' : '' }}"><span>Performance</span>
                                                <span class="menu-arrow"></span>
                                            </a>
                                            <ul>
                                                <li><a href="{{url('performance-indicator')}}" class="{{ Request::is('performance-indicator') ? 'active' : '' }}">Performance Indicator</a></li>
                                <li><a href="{{url('performance-review')}}" class="{{ Request::is('performance-review') ? 'active' : '' }}">Performance Review</a></li>
                                <li><a href="{{url('performance-appraisal')}}" class="{{ Request::is('performance-appraisal') ? 'active' : '' }}">Performance Appraisal</a></li>
                                <li><a href="{{url('goal-tracking')}}" class="{{ Request::is('goal-tracking') ? 'active' : '' }}">Goal List</a></li>
                                <li><a href="{{url('goal-type')}}" class="{{ Request::is('goal-type') ? 'active' : '' }}">Goal Type</a></li>
                                            </ul>
                                        </li>
@endif
                                        @if ($showTemplateCatalogMenus && $canViewTrainingMenu)
                                        <li class="submenu">
                                            <a href="javascript:void(0);" class="{{ Request::is('training','trainers','training-type') ? 'active subdrop' : '' }}"><span>Training</span>
                                                <span class="menu-arrow"></span>
                                            </a>
                                            <ul>
                                                <li><a href="{{url('training')}}" class="{{ Request::is('training') ? 'active' : '' }}">Training List</a></li>
@if ($canManageTrainingMenu)
                                               <li><a href="{{url('trainers')}}" class="{{ Request::is('trainers') ? 'active' : '' }}">Trainers</a></li>
                                                <li><a href="{{url('training-type')}}" class="{{ Request::is('training-type') ? 'active' : '' }}">Training Type</a></li>
@endif

                                            </ul>
                                        </li>
@endif
@if ($canSeeEmployeeLifecycleMenu)
                                        <li><a href="{{url('promotion')}}" class="{{ Request::is('promotion') ? 'active' : '' }}"><span>Promotion</span></a></li>
                                        <li><a href="{{url('resignation')}}" class="{{ Request::is('resignation') ? 'active' : '' }}"><span>Resignation</span></a></li>
                                        <li><a href="{{url('termination')}}" class="{{ Request::is('termination') ? 'active' : '' }}"><span>Termination</span></a></li>
@endif
                                    </ul>
                                </li>
                                <li class="submenu">
                                    <a href="#" class="{{ Request::is('estimates','invoices','payments','expenses','provident-fund','tax-employees*','taxes','categories','budgets','budget-expenses','budget-revenues','employee-salary','payslip','payroll','payroll-overtime','payroll-deduction','payroll-thr','payroll-pkwt-compensation',
                                    'assets','asset-categories','knowledgebase','knowledgebase/*', 'users','roles-permissions','expenses-report','invoice-report','project-report','user-report','employee-report','payslip-report','attendance-report','leave-report','daily-report',
                                    'profile-settings','security-settings','notification-settings','business-settings','seo-settings','localization-settings','prefixes','preferences','performance-appraisal','language','authentication-settings','ai-settings',
                                     'approval-settings','leave-type','email-settings','email-template','sms-settings','sms-template','otp-settings','gdpr','maintenance-mode','payment-gateways','currencies','custom-css','custom-js','cronjob','storage-settings','ban-ip-address','backup','clear-cache') ? 'active subdrop' : '' }}">
                                        <i class="ti ti-user-star"></i><span>Administration</span>
                                        <span class="menu-arrow"></span>
                                    </a>
                                    <ul>
                                        <li class="submenu">
                                            <a href="javascript:void(0);" class="{{ Request::is('estimates','invoice','payments','expenses','provident-fund') ? 'active subdrop' : '' }}"><span>Sales</span>
                                                <span class="menu-arrow"></span>
                                            </a>
                                            <ul>
                                                <li><a href="{{url('estimates')}}" class="{{ Request::is('estimates') ? 'active' : '' }}">Estimates</a></li>
                                                <li><a href="{{url('invoice')}}" class="{{ Request::is('invoice') ? 'active' : '' }}">Invoices</a></li>
                                                <li><a href="{{url('payments')}}" class="{{ Request::is('payments') ? 'active' : '' }}">Payments</a></li>
                                                <li><a href="{{url('expenses')}}" class="{{ Request::is('expenses') ? 'active' : '' }}">Expenses</a></li>
                                                <li><a href="{{url('provident-fund')}}" class="{{ Request::is('provident-fund') ? 'active' : '' }}">Provident Fund</a></li>
                                            </ul>
                                        </li>
                                        <li class="submenu">
                                            <a href="javascript:void(0);" class="{{ Request::is('categories','budgets','budget-expenses','budget-revenues') ? 'active subdrop' : '' }}"><span>Accounting</span>
                                                <span class="menu-arrow"></span>
                                            </a>
                                            <ul>
                                                <li><a href="{{url('categories')}}" class="{{ Request::is('categories') ? 'active' : '' }}" >Categories</a></li>
                                                <li><a href="{{url('budgets')}}" class="{{ Request::is('budgets') ? 'active' : '' }}">Budgets</a></li>
                                                <li><a href="{{url('budget-expenses')}}" class="{{ Request::is('budget-expenses') ? 'active' : '' }}">Budget Expenses</a></li>
                                                <li><a href="{{url('budget-revenues')}}" class="{{ Request::is('budget-revenues') ? 'active' : '' }}">Budget Revenues</a></li>
                                            </ul>
                                        </li>
@if ($canSeePayrollMenu)
                                        <li class="submenu">
                                            <a href="javascript:void(0);"  class="{{ Request::is('employee-salary','payslip','payroll-run','payroll-run-history','payroll','payroll-overtime','payroll-deduction','payroll-thr','payroll-pkwt-compensation') ? 'active subdrop' : '' }}"><span>Payroll</span>
                                                <span class="menu-arrow"></span>
                                            </a>
                                            <ul>
                                                <li class="submenu submenu-two">
                                                    <a href="javascript:void(0);" class="{{ Request::is('payroll-run','payroll-thr','payroll-pkwt-compensation','employee-salary') ? 'subdrop' : '' }}">Payroll Operations<span class="menu-arrow inside-submenu"></span></a>
                                                    <ul>
                                                        <li><a href="{{url('payroll-run')}}" class="{{ Request::is('payroll-run') ? 'active' : '' }}">Process Monthly Payroll</a></li>
                                                        <li><a href="{{url('payroll-thr')}}" class="{{ Request::is('payroll-thr') ? 'active' : '' }}">THR Payroll</a></li>
                                                        <li><a href="{{url('payroll-pkwt-compensation')}}" class="{{ Request::is('payroll-pkwt-compensation') ? 'active' : '' }}">PKWT Compensation</a></li>
                                                        <li><a href="{{url('employee-salary')}}" class="{{ Request::is('employee-salary') ? 'active' : '' }}">Compensation Management</a></li>
                                                    </ul>
                                                </li>
                                                <li class="submenu submenu-two">
                                                    <a href="javascript:void(0);" class="{{ Request::is('payslip','payroll-run-history','payroll','payroll-overtime','payroll-deduction') ? 'subdrop' : '' }}">Payroll Records &amp; Setup<span class="menu-arrow inside-submenu"></span></a>
                                                    <ul>
                                                        <li><a href="{{url('payslip')}}" class="{{ Request::is('payslip') ? 'active' : '' }}">Payslips</a></li>
                                                        <li><a href="{{url('payroll-run-history')}}" class="{{ Request::is('payroll-run-history') ? 'active' : '' }}">Payroll History</a></li>
                                                        <li><a href="{{url('payroll')}}" class="{{ Request::is('payroll') ? 'active' : '' }}">Additions</a></li>
                                                        <li><a href="{{url('payroll-overtime')}}" class="{{ Request::is('payroll-overtime') ? 'active' : '' }}">Overtime</a></li>
                                                        <li><a href="{{url('payroll-deduction')}}" class="{{ Request::is('payroll-deduction') ? 'active' : '' }}">Deductions</a></li>
                                        <li><a href="{{url('salary-component-master')}}" class="{{ Request::is('salary-component-master') ? 'active' : '' }}">Salary Components</a></li>
                                                    </ul>
                                                </li>
                                            </ul>
                                        </li>
@endif
@if ($canSeeAssetManagementMenu)
                                        <li class="submenu">
                                            <a href="javascript:void(0);"  class="{{ Request::is('assets','asset-categories') ? 'active subdrop' : '' }}"><span>Assets</span>
                                                <span class="menu-arrow"></span>
                                            </a>
                                            <ul>
                                                <li><a href="{{url('assets')}}" class="{{ Request::is('assets') ? 'active' : '' }}">Assets</a></li>
                                                <li><a href="{{url('asset-categories')}}" class="{{ Request::is('asset-categories') ? 'active' : '' }}">Asset Categories</a></li>
                                            </ul>
                                        </li>
@endif
                                        <li class="submenu">
                                            <a href="javascript:void(0);" class="{{ Request::is('knowledgebase','knowledgebase/*','knowledgebase-details') ? 'active subdrop' : '' }}"><span>Help & Supports</span>
                                                <span class="menu-arrow"></span>
                                            </a>
                                            <ul>
                                                <li><a href="{{url('knowledgebase')}}" class="{{ Request::is('knowledgebase','knowledgebase/*','knowledgebase-details') ? 'active' : '' }}">Knowledge Base</a></li>

                                            </ul>
                                        </li>
                                        <li class="submenu">
                                            <a href="javascript:void(0);" class="{{ Request::is('users','roles-permissions') ? 'active subdrop' : '' }}"><span>User Management</span>
                                                <span class="menu-arrow"></span>
                                            </a>
                                            <ul>
                                                <li><a href="{{url('users')}}" class="{{ Request::is('users') ? 'active' : '' }}">Users</a></li>
                                                <li><a href="{{url('roles-permissions')}}" class="{{ Request::is('roles-permissions') ? 'active' : '' }}">Roles & Permissions</a></li>
                                            </ul>
                                        </li>
                                        <li class="submenu">
                                            <a href="javascript:void(0);" class="{{ Request::is('expenses-report','invoice-report','project-report','user-report','employee-report','payslip-report','attendance-report','leave-report','daily-report') ? 'active subdrop' : '' }}"><span>Reports</span>
                                                <span class="menu-arrow"></span>
                                            </a>
                                            <ul>
                                                <li><a href="{{url('expenses-report')}}" class="{{ Request::is('expenses-report') ? 'active' : '' }}">Expense Report</a></li>
                                <li><a href="{{url('invoice-report')}}" class="{{ Request::is('invoice-report') ? 'active' : '' }}">Invoice Report</a></li>
                                <li><a href="{{url('project-report')}}" class="{{ Request::is('project-report') ? 'active' : '' }}">Project Report</a></li>
                                <li><a href="{{url('user-report')}}" class="{{ Request::is('user-report') ? 'active' : '' }}">User Report</a></li>
                                <li><a href="{{url('employee-report')}}" class="{{ Request::is('employee-report') ? 'active' : '' }}">Employee Report</a></li>
                                <li><a href="{{url('payslip-report')}}" class="{{ Request::is('payslip-report') ? 'active' : '' }}">Payslip Report</a></li>
                                <li><a href="{{url('attendance-report')}}" class="{{ Request::is('attendance-report') ? 'active' : '' }}">Attendance Report</a></li>
                                <li><a href="{{url('leave-report')}}" class="{{ Request::is('leave-report') ? 'active' : '' }}">Leave Report</a></li>
                                <li><a href="{{url('daily-report')}}" class="{{ Request::is('daily-report') ? 'active' : '' }}">Daily Report</a></li>
                                            </ul>
                                        </li>
                                        <li class="submenu">
                                            <a href="javascript:void(0);" class="{{ Request::is('profile-settings','security-settings','notification-settings','tax-employees*','taxes','business-settings','seo-settings','localization-settings','prefixes','preferences','performance-appraisal','language','authentication-settings','ai-settings',
                            'approval-settings','leave-type','email-settings','email-template','sms-settings','sms-template','otp-settings','gdpr','maintenance-mode','payment-gateways','currencies','custom-css','custom-js','cronjob','storage-settings','ban-ip-address','backup','clear-cache') ? 'active subdrop' : '' }}"><span>Settings</span>
                                                <span class="menu-arrow"></span>
                                            </a>
                                            <ul>
                                                <li class="submenu">
                                                    <a href="javascript:void(0);" class="{{ Request::is('profile-settings','security-settings','notification-settings','tax-employees*','taxes') ? 'active subdrop' : '' }}">General Settings<span class="menu-arrow"></span></a>
                                                    <ul>
                                                        <li><a href="{{url('profile-settings')}}" class="{{ Request::is('profile-settings') ? 'active' : '' }}">Profile</a></li>
                                                        @if ($isGlobalHcmAdmin)
                                                        <li><a href="{{url('security-settings')}}" class="{{ Request::is('security-settings') ? 'active' : '' }}">Security</a></li>
                                                        @endif
                                                        <li><a href="{{url('notification-settings')}}" class="{{ Request::is('notification-settings') ? 'active' : '' }}">Notifications</a></li>
                                                        @if (!$isGlobalHcmAdmin)
                                                        <li><a href="{{url('tax-employees')}}" class="{{ Request::is('tax-employees*','taxes') ? 'active' : '' }}">Tax Governance</a></li>
                                                        @endif
                                                    </ul>
                                                </li>
                                                <li class="submenu">
                                                    <a href="javascript:void(0);" class="{{ Request::is('business-settings','seo-settings','localization-settings','prefixes','preferences','performance-appraisal','language','authentication-settings','ai-settings') ? 'active subdrop' : '' }}">Website Settings<span class="menu-arrow"></span></a>
                                                    <ul>
                                                        @if ($isGlobalHcmAdmin)
                                                        <li><a href="{{url('business-settings')}}" class="{{ Request::is('business-settings') ? 'active' : '' }}">Business Settings</a></li>
                                        <li><a href="{{url('seo-settings')}}" class="{{ Request::is('seo-settings') ? 'active' : '' }}">SEO Settings</a></li>
                                        <li><a href="{{url('localization-settings')}}" class="{{ Request::is('localization-settings') ? 'active' : '' }}">Localization</a></li>
                                        <li><a href="{{url('language')}}" class="{{ Request::is('language') ? 'active' : '' }}">Language</a></li>
                                        <li><a href="{{url('authentication-settings')}}" class="{{ Request::is('authentication-settings') ? 'active' : '' }}">Authentication</a></li>
                                        <li><a href="{{url('ai-settings')}}" class="{{ Request::is('ai-settings') ? 'active' : '' }}">AI Settings</a></li>
                                        @endif
                                        <li><a href="{{url('prefixes')}}" class="{{ Request::is('prefixes') ? 'active' : '' }}">Prefixes</a></li>
                                        <li><a href="{{url('preferences')}}" class="{{ Request::is('preferences') ? 'active' : '' }}">Preferences</a></li>
                                        <li><a href="{{url('performance-appraisal')}}" class="{{ Request::is('performance-appraisal') ? 'active' : '' }}">Appearance</a></li>
                                                    </ul>
                                                </li>
                                                <li class="submenu">
                                                    <a href="javascript:void(0);" class="{{ Request::is('approval-settings','leave-type') ? 'active subdrop' : '' }}">App Settings<span class="menu-arrow"></span></a>
                                                    <ul>
                                        <li><a href="{{url('approval-settings')}}" class="{{ Request::is('approval-settings') ? 'active' : '' }}">Approval Settings</a></li>
                                        @if ($isGlobalHcmAdmin)<li><a href="{{url('invoice-settings')}}" class="{{ Request::is('invoice-settings') ? 'active' : '' }}">Invoice Settings</a></li>@endif
                                        <li><a href="{{url('leave-type')}}" class="{{ Request::is('leave-type') ? 'active' : '' }}">Leave Type</a></li>
                                                    </ul>
                                                </li>
                                                @if ($isGlobalHcmAdmin)
                                                <li class="submenu">
                                                    <a href="javascript:void(0);" class="{{ Request::is('email-settings','email-template','sms-settings','sms-template','otp-settings','gdpr','maintenance-mode') ? 'active subdrop' : '' }}">System Settings<span class="menu-arrow"></span></a>
                                                    <ul>
                                                        <li><a href="{{url('email-settings')}}" class="{{ Request::is('email-settings') ? 'active' : '' }}">Email Settings</a></li>
                                                        <li><a href="{{url('email-template')}}" class="{{ Request::is('email-template') ? 'active' : '' }}">Email Templates</a></li>
                                                        <li><a href="{{url('sms-settings')}}" class="{{ Request::is('sms-settings') ? 'active' : '' }}">SMS Settings</a></li>
                                                        <li><a href="{{url('sms-template')}}" class="{{ Request::is('sms-template') ? 'active' : '' }}">SMS Templates</a></li>
                                                        <li><a href="{{url('otp-settings')}}" class="{{ Request::is('otp-settings') ? 'active' : '' }}">OTP</a></li>
                                                        <li><a href="{{url('gdpr')}}" class="{{ Request::is('gdpr') ? 'active' : '' }}">GDPR Cookies</a></li>
                                                        <li><a href="{{url('maintenance-mode')}}" class="{{ Request::is('maintenance-mode') ? 'active' : '' }}">Maintenance Mode</a></li>
                
                                                    </ul>
                                                </li>
                                                @endif
                                                @if ($isGlobalHcmAdmin)
                                                @endif
                                                <li class="submenu">
                                                    <a href="javascript:void(0);" class="{{ Request::is('payment-gateways','currencies') ? 'active subdrop' : '' }}">Financial Settings<span class="menu-arrow"></span></a>
                                                    <ul>
                                                        @if ($isGlobalHcmAdmin)
                                                        <li><a href="{{url('payment-gateways')}}" class="{{ Request::is('payment-gateways') ? 'active' : '' }}">Payment Gateways</a></li>
                                                        @endif
                                                        @if ($isGlobalHcmAdmin)
                                                        <li><a href="{{url('currencies')}}" class="{{ Request::is('currencies') ? 'active' : '' }}">Currencies</a></li>
                                                        @endif
                                                    </ul>
                                                </li>
                                                @if ($isGlobalHcmAdmin)
                                                <li class="submenu">
                                                    <a href="javascript:void(0);" class="{{ Request::is('custom-css','custom-js','cronjob','storage-settings','ban-ip-address','backup','clear-cache') ? 'active subdrop' : '' }}">Other Settings<span class="menu-arrow"></span></a>
                                                    <ul>
                                                        <li><a href="{{url('custom-css')}}" class="{{ Request::is('custom-css') ? 'active' : '' }}">Custom CSS</a></li>
                                        <li><a href="{{url('custom-js')}}" class="{{ Request::is('custom-js') ? 'active' : '' }}">Custom JS</a></li>
                                        <li><a href="{{url('cronjob')}}" class="{{ Request::is('cronjob') ? 'active' : '' }}">Cronjob</a></li>
                                        <li><a href="{{url('storage-settings')}}" class="{{ Request::is('storage-settings') ? 'active' : '' }}">Storage</a></li>
                                        <li><a href="{{url('ban-ip-address')}}" class="{{ Request::is('ban-ip-address') ? 'active' : '' }}">Ban IP Address</a></li>
                                        <li><a href="{{url('backup')}}" class="{{ Request::is('backup') ? 'active' : '' }}">Backup</a></li>
                                        <li><a href="{{url('clear-cache')}}" class="{{ Request::is('clear-cache') ? 'active' : '' }}">Clear Cache</a></li>

                                                    </ul>
                                                </li>
                                                @endif
                                            </ul>
                                        </li>
                                    </ul>
                                </li>
                                <li class="submenu">
                                    <a href="#" class="{{ Request::is('starter','profile','gallery','search-result','timeline','coming-soon','under-maintenance','under-construction','api-keys','privacy-policy','terms-condition',
                    'pages','blogs','blog-categories','blog-comments','blog-tags','countries','states','cities','testimonials','faq') ? 'active' : '' }}">
                                        <i class="ti ti-page-break"></i><span>Pages</span>
                                        <span class="menu-arrow"></span>
                                    </a>
                                    <ul>
                                        <li><a href="{{url('starter')}}" class="{{ Request::is('starter') ? 'active' : '' }}"><span>Starter</span></a></li>
                                        <li><a href="{{url('profile')}}" class="{{ Request::is('profile') ? 'active' : '' }}"><span>Profile</span></a></li>
                                        <li><a href="{{url('gallery')}}" class="{{ Request::is('gallery') ? 'active' : '' }}"><span>Gallery</span></a></li>
                                        <li><a href="{{url('search-result')}}" class="{{ Request::is('search-result') ? 'active' : '' }}"><span>Search Results</span></a></li>
                                        <li><a href="{{url('timeline')}}" class="{{ Request::is('timeline') ? 'active' : '' }}"><span>Timeline</span></a></li>
                                        <li><a href="{{url('coming-soon')}}" class="{{ Request::is('coming-soon') ? 'active' : '' }}"><span>Coming Soon</span></a></li>
                                        <li><a href="{{url('under-maintenance')}}" class="{{ Request::is('under-maintenance') ? 'active' : '' }}"><span>Under Maintenance</span></a></li>
                                        <li><a href="{{url('under-construction')}}" class="{{ Request::is('under-construction') ? 'active' : '' }}"><span>Under Construction</span></a></li>
                                        <li><a href="{{url('api-keys')}}" class="{{ Request::is('api-keys') ? 'active' : '' }}"><span>API Keys</span></a></li>
                                        <li><a href="{{url('privacy-policy')}}" class="{{ Request::is('privacy-policy') ? 'active' : '' }}"><span>Privacy Policy</span></a></li>
                                        <li><a href="{{url('terms-condition')}}"  class="{{ Request::is('terms-condition') ? 'active' : '' }}"><span>Terms & Conditions</span></a></li>
                
                                            <li class="submenu">
                                            <a href="#" class="{{ Request::is('pages','blogs','blog-categories','blog-comments','blog-tags') ? 'active' : '' }}"><span>Content</span> <span class="menu-arrow"></span></a>
                                            <ul>
                                                @if ($isHcmAdmin)
                                                <li class="{{ Request::is('pages') ? 'active' : '' }}"><a href="{{url('pages')}}">Pages</a></li>
                                                @endif
                                                <li class="submenu">
                                                    <a href="javascript:void(0);"class="{{ Request::is('blogs','blog-categories','blog-comments','blog-tags') ? 'active' : '' }}">Blogs<span class="menu-arrow"></span></a>
                                                    <ul>
                                                        <li class="{{ Request::is('blogs') ? 'active' : '' }}"><a href="{{url('blogs')}}">All Blogs</a></li>
                                        <li class="{{ Request::is('blog-categories') ? 'active' : '' }}"><a href="{{url('blog-categories')}}">Categories</a></li>
                                        <li class="{{ Request::is('blog-comments') ? 'active' : '' }}"><a href="{{url('blog-comments')}}">Comments</a></li>
                                        <li class="{{ Request::is('blog-tags') ? 'active' : '' }}"><a href="{{url('blog-tags')}}">Tags</a></li>
                                                    </ul>
                                                </li>
                                                <li class="submenu">
                                                    <a href="javascript:void(0);" class="{{ Request::is('countries','states','cities') ? 'active' : '' }}">Locations<span class="menu-arrow"></span></a>
                                                    <ul>
                                                        <li><a href="{{url('countries')}}" class="{{ Request::is('countries') ? 'active' : '' }}">Provinces</a></li>
                                        <li><a href="{{url('states')}}" class="{{ Request::is('states') ? 'active' : '' }}">Regencies</a></li>
                                        <li><a href="{{url('cities')}}" class="{{ Request::is('cities') ? 'active' : '' }}">Districts</a></li>                                                    </ul>
                                                </li>
                                                <li><a href="{{url('testimonials')}}" class="{{ Request::is('testimonials') ? 'active' : '' }}">Testimonials</a></li>
                                <li><a href="{{url('faq')}}" class="{{ Request::is('faq') ? 'active' : '' }}">FAQ’S</a></li>
                                            </ul>
                                        </li>
                                        @if ($showTemplateCatalogMenus)
                                        <li class="submenu">
                                            <a href="#" class="{{ Request::is('login','login-2','login-3','register','register-2','register-3','forgot-password','forgot-password-2','forgot-password-3',
                                            'reset-password','reset-password-2','reset-password-3','email-verification','email-verification-2','email-verification-3','two-step-verification','two-step-verification-2','two-step-verification-3',
                                           'lock-screen','error-404','error-500' ) ? 'active' : '' }}">
                                                <span>Authentication</span>
                                                <span class="menu-arrow"></span>
                                            </a>
                                            <ul>
                                                <li class="submenu">
                                                    <a href="javascript:void(0);" class="{{ Request::is('login','login-2','login-3') ? 'active' : '' }}">Login<span class="menu-arrow"></span></a>
                                                    <ul>
                                                        <li><a href="{{url('login')}}" class="{{ Request::is('login') ? 'active' : '' }}">Cover</a></li>
                                                       <li><a href="{{url('login-2')}}" class="{{ Request::is('login-2') ? 'active' : '' }}">Illustration</a></li>
                                                       <li><a href="{{url('login-3')}}" class="{{ Request::is('login-3') ? 'active' : '' }}">Basic</a></li>

                                                    </ul>
                                                </li>
                                                <li class="submenu">
                                                    <a href="javascript:void(0);" class="{{ Request::is('register','register-2','register-3') ? 'active' : '' }}">Register<span class="menu-arrow"></span></a>
                                                    <ul>
                                                        <li><a href="{{url('register')}}" class="{{ Request::is('register') ? 'active' : '' }}">Cover</a></li>
                                                        <li><a href="{{url('register-2')}}" class="{{ Request::is('register-2') ? 'active' : '' }}">Illustration</a></li>
                                                        <li><a href="{{url('register-3')}}" class="{{ Request::is('register-3') ? 'active' : '' }}">Basic</a></li>                                                    </ul>
                                                </li>
                                                <li class="submenu"><a href="javascript:void(0);" class="{{ Request::is('forgot-password','forgot-password-2','forgot-password-3') ? 'active' : '' }}">Forgot Password<span class="menu-arrow"></span></a>
                                                    <ul>
                                                        <li><a href="{{url('forgot-password')}}" class="{{ Request::is('forgot-password') ? 'active' : '' }}">Cover</a></li>
                                                        <li><a href="{{url('forgot-password-2')}}" class="{{ Request::is('forgot-password-2') ? 'active' : '' }}">Illustration</a></li>
                                                        <li><a href="{{url('forgot-password-3')}}" class="{{ Request::is('forgot-password-3') ? 'active' : '' }}">Basic</a></li>
                                                    </ul>
                                                </li>
                                                <li class="submenu">
                                                    <a href="javascript:void(0);" class="{{ Request::is('reset-password','reset-password-2','reset-password-3') ? 'active' : '' }}">Reset Password<span class="menu-arrow"></span></a>
                                                    <ul>
                                                        <li><a href="{{url('reset-password')}}" class="{{ Request::is('reset-password') ? 'active' : '' }}">Cover</a></li>
                                        <li><a href="{{url('reset-password-2')}}" class="{{ Request::is('reset-password-2') ? 'active' : '' }}">Illustration</a></li>
                                        <li><a href="{{url('reset-password-3')}}" class="{{ Request::is('reset-password-3') ? 'active' : '' }}">Basic</a></li>
                                                    </ul>
                                                </li>
                                                <li class="submenu">
                                                    <a href="javascript:void(0);" class="{{ Request::is('email-verification','email-verification-2','email-verification-3') ? 'active' : '' }}">Email Verification<span class="menu-arrow"></span></a>
                                                    <ul>
                                                        <li><a href="{{url('email-verification')}}" class="{{ Request::is('email-verification') ? 'active' : '' }}">Cover</a></li>
                                        <li><a href="{{url('email-verification-2')}}" class="{{ Request::is('email-verification-2') ? 'active' : '' }}">Illustration</a></li>
                                        <li><a href="{{url('email-verification-3')}}" class="{{ Request::is('email-verification-3') ? 'active' : '' }}">Basic</a></li>                                                    </ul>
                                                </li>
                                                <li class="submenu">
                                                    <a href="javascript:void(0);" class="{{ Request::is('two-step-verification','two-step-verification-2','two-step-verification-3') ? 'active' : '' }}">2 Step Verification<span class="menu-arrow"></span></a>
                                                    <ul>
                                                        <li><a href="{{url('two-step-verification')}}" class="{{ Request::is('two-step-verification') ? 'active' : '' }}">Cover</a></li>
                                                        <li><a href="{{url('two-step-verification-2')}}" class="{{ Request::is('two-step-verification-2') ? 'active' : '' }}">Illustration</a></li>
                                                        <li><a href="{{url('two-step-verification-3')}}" class="{{ Request::is('two-step-verification-3') ? 'active' : '' }}">Basic</a></li>
                                                    </ul>
                                                </li>
                                                <li><a href="{{url('lock-screen')}}" class="{{ Request::is('lock-screen') ? 'active' : '' }}">Lock Screen</a></li>
                                                <li><a href="{{url('error-404')}}" class="{{ Request::is('error-404') ? 'active' : '' }}">404 Error</a></li>
                                                <li><a href="{{url('error-500')}}" class="{{ Request::is('error-500') ? 'active' : '' }}">500 Error</a></li>

                                            </ul>
                                        </li>
                                        @endif
                                        <li class="submenu">
                                            <a href="#" class="{{ Request::is('ui-alerts',
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
                                            'ui-rangeslider','ui-rating','ui-text-editor','ui-counter','ui-scrollbar','ui-stickynote','ui-timeline','form-basic-inputs',
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
                                            'form-basic-inputs',
                                                    'form-checkbox-radios',
                                                    'form-input-groups',
                                                    'form-grid-gutters',
                                                    'form-select',
                                                    'form-mask',
                                                    'form-fileupload',
                                                    'form-validation',
                                                    'form-select2',
                                                    'form-wizard',
                                                    'form-pickers',
                                                    'tables-basic','data-tables',
                                                    'chart-apex',
                                                    'chart-c3',
                                                    'chart-js',
                                                    'chart-morris',
                                                    'chart-flot',
                                                    'chart-peity',
                                                    'icon-fontawesome','icon-tabler','icon-bootstrap',
                                            'icon-remix','icon-feather','icon-ionic','icon-material','icon-pe7','icon-simpleline','icon-themify','icon-ionic',
                                            'icon-material','icon-pe7','icon-simpleline','icon-themify','icon-weather','icon-typicon','icon-flag','maps-vector','maps-leaflet') ? 'active subdrop' : '' }}">
                                                <span>UI Interface</span>
                                                <span class="menu-arrow"></span>
                                            </a>
                                            <ul>
                                                <li class="submenu">
                                                    <a href="javascript:void(0);" class="{{ Request::is('ui-alerts',
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
                                                    'ui-swiperjs') ? 'active subdrop' : '' }}">
                                                        <i class="ti ti-hierarchy-2"></i>
                                                        <span>Base UI</span>
                                                        <span class="menu-arrow"></span>
                                                    </a>
                                                    <ul>
                                                        <li>
                                                            <a href="{{url('ui-alerts')}}" class="{{ Request::is('ui-alerts') ? 'active' : '' }}">Alerts</a>
                                                        </li>
                                                        <li>
                                                            <a href="{{url('ui-accordion')}}" class="{{ Request::is('ui-accordion') ? 'active' : '' }}">Accordion</a>
                                                        </li>
                                                        <li>
                                                            <a href="{{url('ui-avatar')}}" class="{{ Request::is('ui-avatar') ? 'active' : '' }}">Avatar</a>
                                                        </li>
                                                        <li>
                                                            <a href="{{url('ui-badges')}}" class="{{ Request::is('ui-badges') ? 'active' : '' }}">Badges</a>
                                                        </li>
                                                        <li>
                                                            <a href="{{url('ui-borders')}}" class="{{ Request::is('ui-borders') ? 'active' : '' }}">Border</a>
                                                        </li>
                                                        <li>
                                                            <a href="{{url('ui-buttons')}}" class="{{ Request::is('ui-buttons') ? 'active' : '' }}">Buttons</a>
                                                        </li>
                                                        <li>
                                                            <a href="{{url('ui-buttons-group')}}" class="{{ Request::is('ui-buttons-group') ? 'active' : '' }}">Button Group</a>
                                                        </li>
                                                        <li>
                                                            <a href="{{url('ui-breadcrumb')}}" class="{{ Request::is('ui-breadcrumb') ? 'active' : '' }}">Breadcrumb</a>
                                                        </li>
                                                        <li>
                                                            <a href="{{url('ui-cards')}}" class="{{ Request::is('ui-cards') ? 'active' : '' }}">Card</a>
                                                        </li>
                                                        <li>
                                                            <a href="{{url('ui-carousel')}}" class="{{ Request::is('ui-carousel') ? 'active' : '' }}">Carousel</a>
                                                        </li>
                                                        <li>
                                                            <a href="{{url('ui-colors')}}" class="{{ Request::is('ui-colors') ? 'active' : '' }}">Colors</a>
                                                        </li>
                                                        <li>
                                                            <a href="{{url('ui-dropdowns')}}" class="{{ Request::is('ui-dropdowns') ? 'active' : '' }}">Dropdowns</a>
                                                        </li>
                                                        <li>
                                                            <a href="{{url('ui-grid')}}" class="{{ Request::is('ui-grid') ? 'active' : '' }}">Grid</a>
                                                        </li>
                                                        <li>
                                                            <a href="{{url('ui-images')}}" class="{{ Request::is('ui-images') ? 'active' : '' }}">Images</a>
                                                        </li>
                                                        <li>
                                                            <a href="{{url('ui-lightbox')}}" class="{{ Request::is('ui-lightbox') ? 'active' : '' }}">Lightbox</a>
                                                        </li>
                                                        <li>
                                                            <a href="{{url('ui-media')}}" class="{{ Request::is('ui-media') ? 'active' : '' }}">Media</a>
                                                        </li>
                                                        <li>
                                                            <a href="{{url('ui-modals')}}" class="{{ Request::is('ui-modals') ? 'active' : '' }}">Modals</a>
                                                        </li>
                                                        <li>
                                                            <a href="{{url('ui-offcanvas')}}" class="{{ Request::is('ui-offcanvas') ? 'active' : '' }}">Offcanvas</a>
                                                        </li>
                                                        <li>
                                                            <a href="{{url('ui-pagination')}}" class="{{ Request::is('ui-pagination') ? 'active' : '' }}">Pagination</a>
                                                        </li>
                                                        <li>
                                                            <a href="{{url('ui-popovers')}}" class="{{ Request::is('ui-popovers') ? 'active' : '' }}">Popovers</a>
                                                        </li>
                                                        <li>
                                                            <a href="{{url('ui-progress')}}" class="{{ Request::is('ui-progress') ? 'active' : '' }}">Progress</a>
                                                        </li>
                                                        <li>
                                                            <a href="{{url('ui-placeholders')}}" class="{{ Request::is('ui-placeholders') ? 'active' : '' }}">Placeholders</a>
                                                        </li>
                                                        <li>
                                                            <a href="{{url('ui-spinner')}}" class="{{ Request::is('ui-spinner') ? 'active' : '' }}">Spinner</a>
                                                        </li>
                                                        <li>
                                                            <a href="{{url('ui-sweetalerts')}}" class="{{ Request::is('ui-sweetalerts') ? 'active' : '' }}">Sweet Alerts</a>
                                                        </li>
                                                        <li>
                                                            <a href="{{url('ui-nav-tabs')}}" class="{{ Request::is('ui-nav-tabs') ? 'active' : '' }}">Tabs</a>
                                                        </li>
                                                        <li>
                                                            <a href="{{url('ui-toasts')}}" class="{{ Request::is('ui-toasts') ? 'active' : '' }}">Toasts</a>
                                                        </li>
                                                        <li>
                                                            <a href="{{url('ui-tooltips')}}" class="{{ Request::is('ui-tooltips') ? 'active' : '' }}">Tooltips</a>
                                                        </li>
                                                        <li>
                                                            <a href="{{url('ui-typography')}}" class="{{ Request::is('ui-typography') ? 'active' : '' }}">Typography</a>
                                                        </li>
                                                        <li>
                                                            <a href="{{url('ui-video')}}" class="{{ Request::is('ui-video') ? 'active' : '' }}">Video</a>
                                                        </li>
                                                        <li>
                                                            <a href="{{url('ui-sortable')}}" class="{{ Request::is('ui-sortable') ? 'active' : '' }}">Sortable</a>
                                                        </li>
                                                        <li>
                                                            <a href="{{url('ui-swiperjs')}}" class="{{ Request::is('ui-swiperjs') ? 'active' : '' }}">Swiperjs</a>
                                                        </li>
                                                    </ul>
                                                </li>
                                                <li class="submenu">
                                                    <a href="javascript:void(0);" class="{{ Request::is('ui-ribbon','ui-clipboard','ui-drag-drop',
                                                    'ui-rangeslider','ui-rating','ui-text-editor','ui-counter','ui-scrollbar','ui-stickynote','ui-timeline'
                                                    ) ? 'active subdrop' : '' }}">
                                                        <i class="ti ti-hierarchy-3"></i>
                                                        <span>Advanced UI</span>
                                                        <span class="menu-arrow"></span>
                                                    </a>
                                                    <ul>
                                                        <li>
                                                            <a href="{{url('ui-ribbon')}}" class="{{ Request::is('ui-ribbon') ? 'active' : '' }}">Ribbon</a>
                                                        </li>
                                                        <li>
                                                            <a href="{{url('ui-clipboard')}}" class="{{ Request::is('ui-clipboard') ? 'active' : '' }}">Clipboard</a>
                                                        </li>
                                                        <li>
                                                            <a href="{{url('ui-drag-drop')}}" class="{{ Request::is('ui-drag-drop') ? 'active' : '' }}">Drag & Drop</a>
                                                        </li>
                                                        <li>
                                                            <a href="{{url('ui-rangeslider')}}" class="{{ Request::is('ui-rangeslider') ? 'active' : '' }}">Range Slider</a>
                                                        </li>
                                                        <li>
                                                            <a href="{{url('ui-rating')}}" class="{{ Request::is('ui-rating') ? 'active' : '' }}">Rating</a>
                                                        </li>
                                                        <li>
                                                            <a href="{{url('ui-text-editor')}}" class="{{ Request::is('ui-text-editor') ? 'active' : '' }}">Text Editor</a>
                                                        </li>
                                                        <li>
                                                            <a href="{{url('ui-counter')}}" class="{{ Request::is('ui-counter') ? 'active' : '' }}">Counter</a>
                                                        </li>
                                                        <li>
                                                            <a href="{{url('ui-scrollbar')}}" class="{{ Request::is('ui-scrollbar') ? 'active' : '' }}">Scrollbar</a>
                                                        </li>
                                                        <li>
                                                            <a href="{{url('ui-stickynote')}}" class="{{ Request::is('ui-stickynote') ? 'active' : '' }}">Sticky Note</a>
                                                        </li>
                                                        <li>
                                                            <a href="{{url('ui-timeline')}}" class="{{ Request::is('ui-timeline') ? 'active' : '' }}">Timeline</a>
                                                        </li>
                                                    </ul>
                                                </li>
                                                <li class="submenu">
                                                    <a href="javascript:void(0);" class="{{ Request::is('form-basic-inputs',
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
                                                    'form-pickers'
                                                    ) ? 'active subdrop' : '' }}">
                                                        <i class="ti ti-input-search"></i>
                                                        <span>Forms</span>
                                                        <span class="menu-arrow"></span>
                                                    </a>
                                                    <ul>
                                                        <li class="submenu submenu-two">
                                                            <a href="javascript:void(0);"  class="{{ Request::is('form-basic-inputs',
                                                            'form-checkbox-radios',
                                                            'form-input-groups',
                                                            'form-grid-gutters',
                                                            'form-select',
                                                            'form-mask',
                                                            'form-fileupload',
                                                            'form-validation',
                                                            'form-select2',
                                                            'form-wizard',
                                                            'form-pickers'
                                                            ) ? 'active subdrop' : '' }}">Form Elements <span class="menu-arrow inside-submenu"></span>
                                                            </a>
                                                            <ul>
                                                                <li>
                                                                    <a href="{{url('form-basic-inputs')}}" class="{{ Request::is('form-basic-inputs') ? 'active' : '' }}">Basic Inputs</a>
                                                                </li>
                                                                <li>
                                                                    <a href="{{url('form-checkbox-radios')}}" class="{{ Request::is('form-checkbox-radios') ? 'active' : '' }}">Checkbox & Radios</a>
                                                                </li>
                                                                <li>
                                                                    <a href="{{url('form-input-groups')}}" class="{{ Request::is('form-input-groups') ? 'active' : '' }}">Input Groups</a>
                                                                </li>
                                                                <li>
                                                                    <a href="{{url('form-grid-gutters')}}" class="{{ Request::is('form-grid-gutters') ? 'active' : '' }}">Grid & Gutters</a>
                                                                </li>
                                                                <li>
                                                                    <a href="{{url('form-select')}}" class="{{ Request::is('form-select') ? 'active' : '' }}">Form Select</a>
                                                                </li>
                                                                <li>
                                                                    <a href="{{url('form-mask')}}" class="{{ Request::is('form-mask') ? 'active' : '' }}">Input Masks</a>
                                                                </li>
                                                                <li>
                                                                    <a href="{{url('form-fileupload')}}" class="{{ Request::is('form-fileupload') ? 'active' : '' }}">File Uploads</a>
                                                                </li>
                                                            </ul>
                                                        </li>
                                                        <li class="submenu submenu-two">
                                                            <a href="javascript:void(0);" class="{{ Request::is('form-horizontal','form-vertical','form-floating-labels') ? 'active subdrop' : '' }}">Layouts <span class="menu-arrow inside-submenu"></span>
                                                            </a>
                                                            <ul>
                                                                <li>
                                                                    <a href="{{url('form-horizontal')}}"  class="{{ Request::is('form-horizontal') ? 'active' : '' }}">Horizontal Form</a>
                                                                </li>
                                                                <li>
                                                                    <a href="{{url('form-vertical')}}"  class="{{ Request::is('form-vertical') ? 'active' : '' }}">Vertical Form</a>
                                                                </li>
                                                                <li>
                                                                    <a href="{{url('form-floating-labels')}}"  class="{{ Request::is('form-floating-labels') ? 'active' : '' }}">Floating Labels</a>
                                                                </li>
                                                            </ul>
                                                        </li>
                                                        <li>
                                                            <a href="{{url('form-validation')}}"  class="{{ Request::is('form-validation') ? 'active' : '' }}">Form Validation</a>
                                                        </li>
                                                        <li>
                                                            <a href="{{url('form-select2')}}"  class="{{ Request::is('form-select2') ? 'active' : '' }}">Select2</a>
                                                        </li>
                                                        <li>
                                                            <a href="{{url('form-wizard')}}"  class="{{ Request::is('form-wizard') ? 'active' : '' }}">Form Wizard</a>
                                                        </li>
                                                        <li>
                                                            <a href="{{url('form-pickers')}}"  class="{{ Request::is('form-pickers') ? 'active' : '' }}">Form Pickers</a>
                                                        </li>
                                                        
                                                    </ul>
                                                </li>
                                                <li class="submenu">
                                                    <a href="javascript:void(0);" class="{{ Request::is('tables-basic','data-tables') ? 'active subdrop' : '' }}">
                                                        <i class="ti ti-table-plus"></i>
                                                        <span>Tables</span>
                                                        <span class="menu-arrow"></span>
                                                    </a>
                                                    <ul>
                                                        <li>
                                                            <a href="{{url('tables-basic')}}"  class="{{ Request::is('tables-basic') ? 'active' : '' }}">Basic Tables </a>
                                                        </li>
                                                        <li>
                                                            <a href="{{url('data-tables')}}"  class="{{ Request::is('data-tables') ? 'active' : '' }}">Data Table </a>
                                                        </li>
                                                    </ul>
                                                </li>
                                                <li class="submenu">
                                                    <a href="javascript:void(0);" class="{{ Request::is('chart-apex','chart-c3','chart-js','chart-morris','chart-flot','chart-peity') ? 'active subdrop' : '' }}">
                                                        <i class="ti ti-chart-line"></i>
                                                        <span>Charts</span>
                                                        <span class="menu-arrow"></span>
                                                    </a>
                                                    <ul>
                                                        <li>
                                                            <a href="{{url('chart-apex')}}"  class="{{ Request::is('chart-apex') ? 'active' : '' }}">Apex Charts</a>
                                                        </li>
                                                        <li>
                                                            <a href="{{url('chart-c3')}}" class="{{ Request::is('chart-c3') ? 'active' : '' }}">Chart C3</a>
                                                        </li>
                                                        <li>
                                                            <a href="{{url('chart-js')}}" class="{{ Request::is('chart-js') ? 'active' : '' }}">Chart Js</a>
                                                        </li>
                                                        <li>
                                                            <a href="{{url('chart-morris')}}" class="{{ Request::is('chart-morris') ? 'active' : '' }}">Morris Charts</a>
                                                        </li>
                                                        <li>
                                                            <a href="{{url('chart-flot')}}" class="{{ Request::is('chart-flot') ? 'active' : '' }}">Flot Charts</a>
                                                        </li>
                                                        <li>
                                                            <a href="{{url('chart-peity')}}" class="{{ Request::is('chart-peity') ? 'active' : '' }}">Peity Charts</a>
                                                        </li>
                                                    </ul>
                                                </li>
                                                <li class="submenu">
                                                    <a href="javascript:void(0);" class="{{ Request::is('icon-fontawesome','icon-tabler','icon-bootstrap',
                                                    'icon-remix','icon-feather','icon-ionic','icon-material','icon-pe7','icon-simpleline','icon-themify','icon-ionic',
                                                    'icon-material','icon-pe7','icon-simpleline','icon-themify','icon-weather','icon-typicon','icon-flag') ? 'active subdrop' : '' }}">
                                                        <i class="ti ti-icons"></i>
                                                        <span>Icons</span>
                                                        <span class="menu-arrow"></span>
                                                    </a>
                                                    <ul>
                                                        <li>
                                                            <a href="{{url('icon-fontawesome')}}" class="{{ Request::is('icon-fontawesome') ? 'active' : '' }}">Fontawesome Icons</a>
                                                        </li>
                                                        <li>
                                                            <a href="{{url('icon-tabler')}}" class="{{ Request::is('icon-tabler') ? 'active' : '' }}">Tabler Icons</a>
                                                        </li>
                                                        <li>
                                                            <a href="{{url('icon-bootstrap')}}" class="{{ Request::is('icon-bootstrap') ? 'active' : '' }}">Bootstrap Icons</a>
                                                        </li>
                                                        <li>
                                                            <a href="{{url('icon-remix')}}" class="{{ Request::is('icon-remix') ? 'active' : '' }}">Remix Icons</a>
                                                        </li>
                                                        <li>
                                                            <a href="{{url('icon-feather')}}" class="{{ Request::is('icon-feather') ? 'active' : '' }}">Feather Icons</a>
                                                        </li>
                                                        <li>
                                                            <a href="{{url('icon-ionic')}}" class="{{ Request::is('icon-ionic') ? 'active' : '' }}">Ionic Icons</a>
                                                        </li>
                                                        <li>
                                                            <a href="{{url('icon-material')}}" class="{{ Request::is('icon-material') ? 'active' : '' }}">Material Icons</a>
                                                        </li>
                                                        <li>
                                                            <a href="{{url('icon-pe7')}}" class="{{ Request::is('icon-pe7') ? 'active' : '' }}">Pe7 Icons</a>
                                                        </li>
                                                        <li>
                                                            <a href="{{url('icon-simpleline')}}" class="{{ Request::is('icon-simpleline') ? 'active' : '' }}">Simpleline Icons</a>
                                                        </li>
                                                        <li>
                                                            <a href="{{url('icon-themify')}}" class="{{ Request::is('icon-themify') ? 'active' : '' }}">Themify Icons</a>
                                                        </li>
                                                        <li>
                                                            <a href="{{url('icon-weather')}}" class="{{ Request::is('icon-weather') ? 'active' : '' }}">Weather Icons</a>
                                                        </li>
                                                        <li>
                                                            <a href="{{url('icon-typicon')}}" class="{{ Request::is('icon-typicon') ? 'active' : '' }}">Typicon Icons</a>
                                                        </li>
                                                        <li>
                                                            <a href="{{url('icon-flag')}}" class="{{ Request::is('icon-flag') ? 'active' : '' }}">Flag Icons</a>
                                                        </li>
                                                    </ul>
                                                </li>
                                                <li class="submenu">
                                                    <a href="javascript:void(0);"  class="{{ Request::is('maps-vector','maps-leaflet') ? 'active' : '' }}">
                                                        <i class="ti ti-table-plus"></i>
                                                        <span>Maps</span>
                                                        <span class="menu-arrow"></span>
                                                    </a>
                                                    <ul>
                                                        <li>
                                                            <a href="{{url('maps-vector')}}" class="{{ Request::is('maps-vector') ? 'active' : '' }}">Vector</a>
                                                        </li>
                                                        <li>
                                                            <a href="{{url('maps-leaflet')}}" class="{{ Request::is('maps-leaflet') ? 'active' : '' }}">Leaflet</a>
                                                        </li>
                                                    </ul>
                                                </li>
                                            </ul>
                                        </li>
                                        <li><a href="#">Documentation</a></li>
                                        <li><a href="#">Change Log</a></li>
                                        <li class="submenu">
                                            <a href="javascript:void(0);"><span>Multi Level</span><span class="menu-arrow"></span></a>
                                            <ul>
                                                <li><a href="javascript:void(0);">Multilevel 1</a></li>
                                                <li class="submenu submenu-two">
                                                    <a href="javascript:void(0);">Multilevel 2<span class="menu-arrow inside-submenu"></span></a>
                                                    <ul>
                                                        <li><a href="javascript:void(0);">Multilevel 2.1</a></li>
                                                        <li class="submenu submenu-two submenu-three">
                                                            <a href="javascript:void(0);">Multilevel 2.2<span class="menu-arrow inside-submenu inside-submenu-two"></span></a>
                                                            <ul>
                                                                <li><a href="javascript:void(0);">Multilevel 2.2.1</a></li>
                                                                <li><a href="javascript:void(0);">Multilevel 2.2.2</a></li>
                                                            </ul>
                                                        </li>
                                                    </ul>
                                                </li>
                                                <li><a href="javascript:void(0);">Multilevel 3</a></li>
                                            </ul>
                                        </li>
                                    </ul>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
                <!-- /Horizontal Single -->

                <div class="d-flex align-items-center">
                    <div class="me-1">
                        <a href="#" class="btn btn-menubar btnFullscreen">
                            <i class="ti ti-maximize"></i>
                        </a>
                    </div>
                    <div class="dropdown me-1">
                        <a href="#" class="btn btn-menubar" data-bs-toggle="dropdown">
                            <i class="ti ti-layout-grid-remove"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end">
                            <div class="card mb-0 border-0 shadow-none">
                                <div class="card-header">
                                    <h4>Applications</h4>
                                </div>
                                <div class="card-body">											
                                    <a href="{{url('calendar')}}" class="d-block pb-2">
                                        <span class="avatar avatar-md bg-transparent-dark me-2"><i class="ti ti-calendar text-gray-9"></i></span>Calendar
                                    </a>										
                                    <a href="{{url('notes')}}" class="d-block py-2">
                                        <span class="avatar avatar-md bg-transparent-dark me-2"><i class="ti ti-notes text-gray-9"></i></span>Notes
                                    </a>										
                                    <a href="{{url('file-manager')}}" class="d-block py-2">
                                        <span class="avatar avatar-md bg-transparent-dark me-2"><i class="ti ti-folder text-gray-9"></i></span>File Manager
                                    </a>								
                                    <a href="{{url('invoices')}}" class="d-block py-2 pb-0">
                                        <span class="avatar avatar-md bg-transparent-dark me-2"><i class="ti ti-file-invoice text-gray-9"></i></span>Invoices
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="me-1">
                        <a href="{{url('chat')}}" class="btn btn-menubar position-relative">
                            <i class="ti ti-brand-hipchat"></i>
                            <span class="badge bg-info rounded-pill d-flex align-items-center justify-content-center header-badge">5</span>
                        </a>
                    </div>
                    <div class="me-1">
                        <a href="{{url('email')}}" class="btn btn-menubar">
                            <i class="ti ti-mail"></i>
                        </a>
                    </div>
                    @include('layout.partials.header.notification-dropdown')
                    @php
                        $trialEndsAt = $activeCompanySubscription?->trial_ends_at;
                        $trialDaysLeft = null;
                        if ($activeCompanySubscription && $activeCompanySubscription->status === 'trial' && $trialEndsAt) {
                            // Use ceiling for friendly UX: if remaining hours > 0, show at least 1 day.
                            $secondsLeft = now()->diffInSeconds($trialEndsAt, false);
                            $trialDaysLeft = $secondsLeft > 0 ? (int) ceil($secondsLeft / 86400) : 0;
                        }
                    @endphp
                    @if (is_int($trialDaysLeft) && $trialDaysLeft > 0)
                        <div class="me-2 d-inline-flex d-md-none align-items-center">
                            <a href="{{ url('/subscription') }}" class="btn btn-sm btn-outline-warning d-inline-flex align-items-center gap-1 text-nowrap px-2" title="Trial {{ $trialDaysLeft }} hari lagi">
                                <i class="ti ti-sparkles"></i>
                                <span class="badge bg-warning text-dark rounded-pill">{{ $trialDaysLeft }}d</span>
                            </a>
                        </div>
                        <div class="me-2 d-none d-md-flex align-items-center">
                            <a href="{{ url('/subscription') }}" class="btn btn-sm btn-outline-warning d-inline-flex align-items-center gap-1 text-nowrap px-2">
                                <i class="ti ti-sparkles"></i>
                                <span class="fw-semibold">Trial</span>
                                <span class="badge bg-warning text-dark rounded-pill">{{ $trialDaysLeft }} hari lagi</span>
                            </a>
                        </div>
                    @endif
                    @if ($headerPackageCode !== '')
                        <div class="me-2 d-none d-md-flex align-items-center">
                            <a href="{{ url('/subscription') }}" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1 text-nowrap px-2" title="Paket aktif: {{ $headerPackageName !== '' ? $headerPackageName : $headerPackageCode }}">
                                <i class="ti ti-package"></i>
                                <span class="badge {{ $headerPackageBadgeClass }} rounded-pill">{{ $headerPackageCode }}</span>
                            </a>
                        </div>
                    @endif
                    <div class="dropdown profile-dropdown">
                        <a href="javascript:void(0);" class="dropdown-toggle d-flex align-items-center"
                            data-bs-toggle="dropdown">
                            <span class="avatar avatar-sm online">
                                <img src="{{ $headerProfilePhotoUrl }}" alt="Img" class="img-fluid rounded-circle" data-profile-display-avatar="1">
                            </span>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end shadow p-0" style="min-width:260px;border-radius:12px;overflow:hidden;">
                            <div class="card mb-0 border-0">
                                {{-- Profile header --}}
                                <div class="card-header bg-primary bg-opacity-10 border-0 px-3 py-3">
                                    <div class="d-flex align-items-center gap-3">
                                        {{-- Avatar with initials fallback --}}
                                        @php
                                            $initials = collect(explode(' ', $headerProfileName))->map(fn($w) => strtoupper(substr($w,0,1)))->take(2)->join('');
                                        @endphp
                                        <div class="position-relative flex-shrink-0">
                                            <img src="{{ $headerProfilePhotoUrl }}"
                                                alt="{{ $initials }}"
                                                data-profile-display-avatar="1"
                                                class="rounded-circle border border-2 border-white shadow-sm"
                                                style="width:48px;height:48px;object-fit:cover;"
                                                onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                                            <div class="rounded-circle bg-primary text-white fw-bold border border-2 border-white shadow-sm"
                                                style="width:48px;height:48px;display:none;align-items:center;justify-content:center;font-size:16px;">
                                                {{ $initials }}
                                            </div>
                                            <span class="position-absolute bottom-0 end-0 bg-success rounded-circle border border-white" style="width:10px;height:10px;"></span>
                                        </div>
                                        <div class="overflow-hidden">
                                            <div class="fw-semibold text-dark lh-sm text-truncate" style="max-width:170px;" data-profile-display-name="1">{{ $headerProfileName }}</div>
                                            <div class="text-muted fs-12 text-truncate" style="max-width:170px;" data-profile-display-email="1">{{ $headerProfileEmail }}</div>
                                            @if ($headerActiveCompanyName !== '' || $headerActiveCompanyCode !== '')
                                                <div class="d-flex align-items-center flex-wrap gap-1 mt-1">
                                                    <span class="badge bg-white text-dark border rounded-pill fs-11" style="font-weight:500;">
                                                        <i class="ti ti-building me-1" style="font-size:10px;"></i>{{ $headerActiveCompanyCode !== '' ? $headerActiveCompanyCode : $headerActiveCompanyName }}
                                                    </span>
                                                    <span class="badge bg-primary-subtle text-primary rounded-pill fs-11">{{ $headerRoleBadge }}</span>
                                                    @if ($headerPackageCode !== '')
                                                        <span class="badge {{ $headerPackageIsInternal ? 'bg-dark text-white' : 'bg-success-subtle text-success' }} rounded-pill fs-11"
                                                            title="{{ $headerPackageName !== '' ? $headerPackageName : $headerPackageCode }}">
                                                            {{ $headerPackageCode }}
                                                        </span>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                {{-- Menu items --}}
                                <div class="card-body p-2">
                                    <a class="dropdown-item rounded-2 d-flex align-items-center gap-2 py-2 px-3" href="{{url('profile')}}">
                                        <i class="ti ti-user-circle fs-16 text-muted"></i><span>My Profile</span>
                                    </a>
                                    <a class="dropdown-item rounded-2 d-flex align-items-center gap-2 py-2 px-3" href="{{ $isGlobalHcmAdmin ? url('business-settings') : url('profile-settings') }}">
                                        <i class="ti ti-settings fs-16 text-muted"></i><span>Settings</span>
                                    </a>
                                    <a class="dropdown-item rounded-2 d-flex align-items-center gap-2 py-2 px-3" href="{{url('security-settings')}}">
                                        <i class="ti ti-shield-lock fs-16 text-muted"></i><span>Security</span>
                                    </a>
                                    <a class="dropdown-item rounded-2 d-flex align-items-center gap-2 py-2 px-3" href="{{url('profile-settings')}}">
                                        <i class="ti ti-user-cog fs-16 text-muted"></i><span>My Account</span>
                                    </a>
                                    <a class="dropdown-item rounded-2 d-flex align-items-center gap-2 py-2 px-3" href="{{url('knowledgebase')}}">
                                        <i class="ti ti-help-circle fs-16 text-muted"></i><span>Knowledge Base</span>
                                    </a>
                                </div>
                                {{-- Logout --}}
                                <div class="card-footer border-top p-2">
                                    <a class="dropdown-item rounded-2 d-flex align-items-center gap-2 py-2 px-3 text-danger" href="javascript:void(0);" data-auth-logout>
                                        <i class="ti ti-logout fs-16"></i><span>Logout</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @include('layout.partials.header.mobile-user-menu')

    </div>

</div>
<!-- /Header -->