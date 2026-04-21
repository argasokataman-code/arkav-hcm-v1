<!-- Sidebar -->
@php
    $authUser = request()->user() ?: auth()->user();
    $whiteLogoUrl = \App\Support\WebsiteSettings::brandingUrl('white_logo', URL::asset('build/img/image111.png'));
    $darkLogoUrl = \App\Support\WebsiteSettings::brandingUrl('dark_logo', URL::asset('build/img/logo-white.svg'));
    $whiteMiniLogoUrl = \App\Support\WebsiteSettings::brandingUrl('white_mini_logo', URL::asset('build/img/image111.png'));
    $darkMiniLogoUrl = \App\Support\WebsiteSettings::brandingUrl('dark_mini_logo', URL::asset('build/img/logo-white.svg'));
    $isHcmAdmin = (bool) ($authUser?->isHcmAdmin());
    $isGlobalHcmAdmin = (bool) ($authUser?->isGlobalHcmAdmin());
    $primarySuperAdminEmail = strtolower(trim((string) config('hcm.admin_email', 'qa.login@example.com')));
    $secondarySuperAdminEmail = strtolower(trim((string) config('hcm.secondary_admin_email', 'qa.hcm@example.com')));
    $authUserEmail = strtolower(trim((string) ($authUser->email ?? '')));
    $isPrimarySuperAdmin = $authUser && $authUserEmail === $primarySuperAdminEmail;
    $isSecondarySuperAdmin = $authUser && $authUserEmail === $secondarySuperAdminEmail;
    $showTemplateCatalogMenus = $isPrimarySuperAdmin || $isGlobalHcmAdmin; // Super admin sees all template catalogs
    $isQaSuperAdmin = $authUser
        && (
            strtolower(trim((string) ($authUser->email ?? ''))) === strtolower(trim((string) config('hcm.admin_email', 'qa.login@example.com')))
            || (bool) ($authUser->is_super_admin ?? false)
        );
    $activeCompany = request()->attributes->get('activeCompany');
    $activeCompanySubscription = $activeCompany instanceof \App\Models\Company
        ? $activeCompany->activeSubscription()
        : null;
    $hasCompanyBillingAccess = (bool) $activeCompanySubscription;
    $hasAssetManagement = (bool) ($activeCompanySubscription?->package?->hasFeature('asset_management') ?? false);
    $canSeeAssetManagementMenu = $hasAssetManagement || $isQaSuperAdmin || $isGlobalHcmAdmin; // Super admin sees all assets
    $hasPayroll = (bool) ($activeCompanySubscription?->package?->hasFeature('payroll') ?? false);
    $hasPerformance = (bool) ($activeCompanySubscription?->package?->hasFeature('performance') ?? false);
    // Super admin (developer mode) sees all features regardless of package
    $isSuperAdminDeveloperMode = $isGlobalHcmAdmin;
    $superAdminUnlockedPayroll = $hasPayroll || $isSuperAdminDeveloperMode;
    $superAdminUnlockedPerformance = $hasPerformance || $isSuperAdminDeveloperMode;
@endphp
<div class="sidebar" id="sidebar">
    <!-- Logo -->
    <div class="sidebar-logo">
        <a href="{{url('index')}}" class="logo logo-normal">
            <img src="{{ $whiteLogoUrl }}" alt="Logo">
        </a>
        <a href="{{url('index')}}" class="logo-small">
            <img class="logo-mini-light" src="{{ $whiteMiniLogoUrl }}" alt="Logo">
            <img class="logo-mini-dark" src="{{ $darkMiniLogoUrl }}" alt="Logo">
        </a>
        <a href="{{url('index')}}" class="dark-logo">
            <img src="{{ $darkLogoUrl }}" alt="Logo">
        </a>
    </div>
    <style>
        .sidebar-logo .logo-small .logo-mini-dark {
            display: none;
        }

        html[data-theme="dark"] .sidebar-logo .logo-small .logo-mini-light {
            display: none;
        }

        html[data-theme="dark"] .sidebar-logo .logo-small .logo-mini-dark {
            display: inline-block;
        }
    </style>
    <!-- /Logo -->
    <div class="modern-profile p-3 pb-0">
        <div class="text-center rounded bg-light p-3 mb-4 user-profile">
            <div class="avatar avatar-lg online mb-3">
                <img src="{{URL::asset('build/img/profiles/avatar-02.jpg')}}" alt="Img" class="img-fluid rounded-circle">
            </div>
            <h6 class="fs-12 fw-normal mb-1">Adrian Herman</h6>
            <p class="fs-10">System Admin</p>
        </div>
        <div class="sidebar-nav mb-3">
            <ul class="nav nav-tabs nav-tabs-solid nav-tabs-rounded nav-justified bg-transparent" role="tablist">
                <li class="nav-item"><a class="nav-link active border-0" href="#">Menu</a></li>
                <li class="nav-item"><a class="nav-link border-0" href="{{url('chat')}}">Chats</a></li>
                <li class="nav-item"><a class="nav-link border-0" href="{{url('email')}}">Inbox</a></li>
            </ul>
        </div>
    </div>
    <div class="sidebar-header p-3 pb-0 pt-2">
        <div class="text-center rounded bg-light p-2 mb-4 sidebar-profile d-flex align-items-center">
            <div class="avatar avatar-md onlin">
                <img src="{{URL::asset('build/img/profiles/avatar-02.jpg')}}" alt="Img" class="img-fluid rounded-circle">
            </div>
            <div class="text-start sidebar-profile-info ms-2">
                <h6 class="fs-12 fw-normal mb-1">Adrian Herman</h6>
                <p class="fs-10">System Admin</p>
            </div>
        </div>
        <div class="input-group input-group-flat d-inline-flex mb-4">
            <span class="input-icon-addon">
                <i class="ti ti-search"></i>
            </span>
            <input type="text" class="form-control" placeholder="Search in HRMS">
            <span class="input-group-text">
                <kbd>CTRL + / </kbd>
            </span>
        </div>
        <div class="d-flex align-items-center justify-content-between menu-item mb-3">
            <div class="me-3">
                <a href="{{url('calendar')}}" class="btn btn-menubar">
                    <i class="ti ti-layout-grid-remove"></i>
                </a>
            </div>
            <div class="me-3">
                <a href="{{url('chat')}}" class="btn btn-menubar position-relative">
                    <i class="ti ti-brand-hipchat"></i>
                    <span class="badge bg-info rounded-pill d-flex align-items-center justify-content-center header-badge">5</span>
                </a>
            </div>
            <div class="me-3 notification-item">
                <a href="{{url('activity')}}" class="btn btn-menubar position-relative me-1">
                    <i class="ti ti-bell"></i>
                    <span class="notification-status-dot"></span>
                </a>
            </div>
            <div class="me-0">
                <a href="{{url('email')}}" class="btn btn-menubar">
                    <i class="ti ti-message"></i>
                </a>
            </div>
        </div>
    </div>
    <div class="sidebar-inner slimscroll">
        <div id="sidebar-menu" class="sidebar-menu">
            <ul>
                <li class="menu-title"><span>MAIN MENU</span></li>
                <li>
                    <ul>
                        <li class="submenu">
                            <a href="javascript:void(0);" class="{{ Request::is('index','employee-dashboard','deals-dashboard','leads-dashboard') ? 'active subdrop' : '' }}">
                                <i class="ti ti-smart-home"></i>
                                <span>Dashboard</span>
                                <span class="badge badge-danger fs-10 fw-medium text-white p-1">Hot</span>
                                <span class="menu-arrow"></span>
                            </a>
                            <ul>
                                <li><a href="{{url('index')}}" class="{{ Request::is('index') ? 'active' : '' }}">Admin Dashboard</a></li>
                                <li><a href="{{url('employee-dashboard')}}" class="{{ Request::is('employee-dashboard') ? 'active' : '' }}">Employee Dashboard</a></li>
@if ($showTemplateCatalogMenus)
                                <li><a href="{{url('deals-dashboard')}}" class="{{ Request::is('deals-dashboard') ? 'active' : '' }}">Deals Dashboard</a></li>
                                <li><a href="{{url('leads-dashboard')}}" class="{{ Request::is('leads-dashboard') ? 'active' : '' }}">Leads Dashboard</a></li>
@endif
                            </ul>
                        </li>
                        <!-- <li class="submenu">
                            <a href="javascript:void(0);" class="{{ Request::is('chat','voice-call','video-call','outgoing-call','incoming-call','call-history',
                            'calendar','email','todo','notes','social-feed','file-manager','kanban-view','invoices','invoice-details') ? 'active subdrop' : '' }}">
                                <i class="ti ti-layout-grid-add"></i><span>Applications</span>
                                <span class="menu-arrow"></span>
                            </a>
                            <ul>
                                <li><a href="{{url('chat')}}" class="{{ Request::is('chat') ? 'active' : '' }}">Chat</a></li>
                                <li class="submenu submenu-two">
                                    <a href="{{url('call')}}" class="{{ Request::is('voice-call','video-call','outgoing-call','incoming-call','call-history') ? 'active subdrop' : '' }}">Calls<span class="menu-arrow inside-submenu"></span></a>
                                    <ul>
                                        <li><a href="{{url('voice-call')}}" class="{{ Request::is('voice-call') ? 'active' : '' }}">Voice Call</a></li>
                                        <li><a href="{{url('video-call')}}"  class="{{ Request::is('video-call') ? 'active' : '' }}">Video Call</a></li>
                                        <li><a href="{{url('outgoing-call')}}"  class="{{ Request::is('outgoing-call') ? 'active' : '' }}">Outgoing Call</a></li>
                                        <li><a href="{{url('incoming-call')}}"  class="{{ Request::is('incoming-call') ? 'active' : '' }}">Incoming Call</a></li>
                                        <li><a href="{{url('call-history')}}"  class="{{ Request::is('call-history') ? 'active' : '' }}">Call History</a></li>
                                    </ul>
                                </li>
                                <li><a href="{{url('calendar')}}"  class="{{ Request::is('calendar') ? 'active' : '' }}">Calendar</a></li>
                                <li><a href="{{url('email')}}"  class="{{ Request::is('email') ? 'active' : '' }}">Email</a></li>
                                <li><a href="{{url('todo')}}"  class="{{ Request::is('todo') ? 'active' : '' }}">To Do</a></li>
                                <li><a href="{{url('notes')}}"  class="{{ Request::is('notes') ? 'active' : '' }}">Notes</a></li>
                                <li><a href="{{url('social-feed')}}"  class="{{ Request::is('social-feed') ? 'active' : '' }}">Social Feed</a></li>
                                <li><a href="{{url('file-manager')}}"  class="{{ Request::is('file-manager') ? 'active' : '' }}">File Manager</a></li>
                                <li><a href="{{url('kanban-view')}}"  class="{{ Request::is('kanban-view') ? 'active' : '' }}">Kanban</a></li>
                                <li><a href="{{url('invoices')}}"  class="{{ Request::is('invoices','invoice-details') ? 'active' : '' }}">Invoices</a></li>
                            </ul>
                        </li> -->
@if ($isGlobalHcmAdmin)
                        <li class="submenu">
                            <a href="#" class="{{ Request::is('dashboard','companies','subscription','packages','packages-grid','domain','purchase-transaction','saas/packages','saas/subscriptions','saas/domains','saas/transactions','saas/invoices','saas/payments','saas/reports','saas/reminders','saas/billing-overview','saas/billing-overview/*') ? 'active subdrop' : '' }}">
                                <i class="ti ti-user-star"></i><span>Super Admin</span>
                                <span class="menu-arrow"></span>
                            </a>
                            <ul>
                                <li><a href="{{url('dashboard')}}"  class="{{ Request::is('dashboard') ? 'active' : '' }}">Dashboard</a></li>
                                <li><a href="{{url('companies')}}"  class="{{ Request::is('companies') ? 'active' : '' }}">Companies</a></li>
                                <li><a href="{{url('saas/billing-overview')}}"  class="{{ Request::is('saas/billing-overview','saas/billing-overview/*') ? 'active' : '' }}">Trial & Billing</a></li>
                                <li><a href="{{url('saas/subscriptions')}}"  class="{{ Request::is('saas/subscriptions') ? 'active' : '' }}">Subscriptions</a></li>
                                <li><a href="{{url('packages')}}"  class="{{ Request::is('packages','packages-grid','saas/packages') ? 'active' : '' }}">Packages</a></li>
                                <li><a href="{{url('domain')}}"  class="{{ Request::is('domain','saas/domains') ? 'active' : '' }}">Domain</a></li>
                                <li><a href="{{url('purchase-transaction')}}"  class="{{ Request::is('purchase-transaction','saas/transactions') ? 'active' : '' }}">Purchase Transaction</a></li>
                            </ul>
                        </li>
@elseif ($hasCompanyBillingAccess)
                        <li class="submenu">
                            <a href="#" class="{{ Request::is('company/invoices') ? 'active subdrop' : '' }}">
                                <i class="ti ti-receipt-2"></i><span>Billing</span>
                                <span class="menu-arrow"></span>
                            </a>
                            <ul>
                                <li><a href="{{url('company/invoices')}}" class="{{ Request::is('company/invoices') ? 'active' : '' }}">My Invoices</a></li>
                            </ul>
                        </li>
@endif
                    </ul>
                </li>
                <!-- <li class="menu-title"><span>LAYOUT</span></li>
                <li>
                    <ul>
                        <li class="{{ Request::is('layout-horizontal') ? 'active' : '' }}">
                            <a href="{{url('layout-horizontal')}}">
                                <i class="ti ti-layout-navbar"></i><span>Horizontal</span>
                            </a>
                        </li>
                        <li  class="{{ Request::is('layout-detached') ? 'active' : '' }}">
                            <a href="{{url('layout-detached')}}">
                                <i class="ti ti-details"></i><span>Detached</span>
                            </a>
                        </li>
                        <li  class="{{ Request::is('layout-modern') ? 'active' : '' }}">
                            <a href="{{url('layout-modern')}}">
                                <i class="ti ti-layout-board-split"></i><span>Modern</span>
                            </a>
                        </li>
                        <li  class="{{ Request::is('layout-two-column') ? 'active' : '' }}">
                            <a href="{{url('layout-two-column')}}">
                                <i class="ti ti-columns-2"></i><span>Two Column </span>
                            </a>
                        </li>
                        <li  class="{{ Request::is('layout-hovered') ? 'active' : '' }}">
                            <a href="{{url('layout-hovered')}}">
                                <i class="ti ti-column-insert-left"></i><span>Hovered</span>
                            </a>
                        </li>
                        <li  class="{{ Request::is('layout-box') ? 'active' : '' }}">
                            <a href="{{url('layout-box')}}">
                                <i class="ti ti-layout-align-middle"></i><span>Boxed</span>
                            </a>
                        </li>
                        <li  class="{{ Request::is('layout-horizontal-single') ? 'active' : '' }}">
                            <a href="{{url('layout-horizontal-single')}}">
                                <i class="ti ti-layout-navbar-inactive"></i><span>Horizontal Single</span>
                            </a>
                        </li>
                        <li  class="{{ Request::is('layout-horizontal-overlay') ? 'active' : '' }}">
                            <a href="{{url('layout-horizontal-overlay')}}">
                                <i class="ti ti-layout-collage"></i><span>Horizontal Overlay</span>
                            </a>
                        </li>
                        <li  class="{{ Request::is('layout-horizontal-box') ? 'active' : '' }}">
                            <a href="{{url('layout-horizontal-box')}}">
                                <i class="ti ti-layout-board"></i><span>Horizontal Box</span>
                            </a>
                        </li>
                        <li  class="{{ Request::is('layout-horizontal-sidemenu') ? 'active' : '' }}">
                            <a href="{{url('layout-horizontal-sidemenu')}}">
                                <i class="ti ti-table"></i><span>Menu Aside</span>
                            </a>
                        </li>
                        <li  class="{{ Request::is('layout-vertical-transparent') ? 'active' : '' }}">
                            <a href="{{url('layout-vertical-transparent')}}">
                                <i class="ti ti-layout"></i><span>Transparent</span>
                            </a>
                        </li>
                        <li  class="{{ Request::is('layout-without-header') ? 'active' : '' }}">
                            <a href="{{url('layout-without-header')}}">
                                <i class="ti ti-layout-sidebar"></i><span>Without Header</span>
                            </a>
                        </li>
                        <li  class="{{ Request::is('layout-rtl') ? 'active' : '' }}">
                            <a href="{{url('layout-rtl')}}">
                                <i class="ti ti-text-direction-rtl"></i><span>RTL</span>
                            </a>
                        </li>
                        <li  class="{{ Request::is('layout-dark') ? 'active' : '' }}">
                            <a href="{{url('layout-dark')}}">
                                <i class="ti ti-moon"></i><span>Dark</span>
                            </a>
                        </li>
                    </ul>
                </li> -->
                <!-- <li class="menu-title"><span>PROJECTS</span></li>
                <li>
                    <ul>
                        <li class="{{ Request::is('clients-grid','clients') ? 'active' : '' }}">
                            <a href="{{url('clients-grid')}}">
                                <i class="ti ti-users-group"></i><span>Clients</span>
                            </a>
                        </li>
                        <li class="submenu">
                            <a href="javascript:void(0);" class="{{ Request::is('projects-grid','tasks','task-board','projects','task-details') ? 'active' : '' }}">
                                <i class="ti ti-box"></i><span>Projects</span>
                                <span class="menu-arrow"></span>
                            </a>
                            <ul>
                                <li><a href="{{url('projects-grid')}}" class="{{ Request::is('projects-grid','project-details','projects' ) ? 'active' : '' }}" >Projects</a></li>
                                <li><a href="{{url('tasks')}}" class="{{ Request::is('tasks','task-details') ? 'active' : '' }}">Tasks</a></li>
                                <li><a href="{{url('task-board')}}" class="{{ Request::is('task-board') ? 'active' : '' }}">Task Board</a></li>
                            </ul>
                        </li>								
                    </ul>
                </li> -->
                <!-- <li class="menu-title"><span>CRM</span></li>
                <li>
                    <ul>
                        <li class="{{ Request::is('contacts-grid','contacts','contact-details') ? 'active' : '' }}">
                            <a href="{{url('contacts-grid')}}">
                                <i class="ti ti-user-shield"></i><span>Contacts</span>
                            </a>
                        </li>
                        <li class="{{ Request::is('companies-grid','companies-crm','company-details') ? 'active' : '' }}">
                            <a href="{{url('companies-grid')}}">
                                <i class="ti ti-building"></i><span>Companies</span>
                            </a>
                        </li>
                        <li class="{{ Request::is('deals-grid','deals-details','deals') ? 'active' : '' }}">
                            <a href="{{url('deals-grid')}}">
                                <i class="ti ti-heart-handshake"></i><span>Deals</span>
                            </a>
                        </li>
                        <li class="{{ Request::is('leads-grid','leads-details','leads') ? 'active' : '' }}">
                            <a href="{{url('leads-grid')}}">
                                <i class="ti ti-user-check"></i><span>Leads</span>
                            </a>
                        </li>
                        <li class="{{ Request::is('pipeline') ? 'active' : '' }}">
                            <a href="{{url('pipeline')}}">
                                <i class="ti ti-timeline-event-text"></i><span>Pipeline</span>
                            </a>
                        </li>
                        <li class="{{ Request::is('analytics') ? 'active' : '' }}">
                            <a href="{{url('analytics')}}">
                                <i class="ti ti-graph"></i><span>Analytics</span>
                            </a>
                        </li>
                        <li class="{{ Request::is('activity') ? 'active' : '' }}">
                            <a href="{{url('activity')}}">
                                <i class="ti ti-activity"></i><span>Activities</span>
                            </a>
                        </li>
                    </ul>							
                </li> -->
                <li class="menu-title"><span>HRM</span></li>
                <li>
                    <ul>
                        <li class="submenu">
                            <a href="javascript:void(0);" class="{{ Request::is('employees','employee-details','departments','designations','policy') ? 'active subdrop' : '' }}">
                                <i class="ti ti-users"></i><span>Employees</span>
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
                        <li class="submenu">
                            <a href="javascript:void(0);"  class="{{ Request::is('ticket-master','tickets-admin','tickets-employee','tickets-grid','ticket-details*') ? 'active' : '' }}">
                                <i class="ti ti-ticket"></i><span>Tickets</span>
                                <span class="menu-arrow"></span>
                            </a>
                            <ul>
                                <li><a href="{{url('ticket-master')}}" class="{{ Request::is('ticket-master') ? 'active' : '' }}">Master Ticket</a></li>
                                <li><a href="{{url('tickets-admin')}}" class="{{ Request::is('tickets-admin','tickets-grid') ? 'active' : '' }}">Ticket (Admin)</a></li>
                                <li><a href="{{url('tickets-employee')}}" class="{{ Request::is('tickets-employee') ? 'active' : '' }}">Ticket (Employee)</a></li>
                            </ul>
                        </li>
                        <li class="{{ Request::is('holidays') ? 'active' : '' }}">
                            <a href="{{url('holidays')}}">
                                <i class="ti ti-calendar-event"></i><span>Holidays</span>
                            </a>
                        </li>
                        <li class="submenu">
                            <a href="javascript:void(0);" class="{{ Request::is('leaves','leaves-employee','leave-settings','attendance-admin','attendance-employee',
                            'timesheets','schedule-timing','shift-master','overtime-master','overtime','overtime-employee') ? 'active subdrop' : '' }}">
                                <i class="ti ti-file-time"></i><span>Attendance</span>
                                <span class="menu-arrow"></span>
                            </a>
                            <ul>
                                <li class="submenu submenu-two">
                                    <a href="javascript:void(0);" class="{{ Request::is('leaves','leaves-employee','leave-settings') ? 'active subdrop' : '' }}">Leaves<span class="menu-arrow inside-submenu"></span></a>
                                    <ul>
                                        <li><a href="{{url('leaves')}}" class="{{ Request::is('leaves') ? 'active' : '' }}">Leaves (Admin)</a></li>
                                        <li><a href="{{url('leaves-employee')}}" class="{{ Request::is('leaves-employee') ? 'active' : '' }}">Leave (Employee)</a></li>
                                        <li><a href="{{url('leave-settings')}}" class="{{ Request::is('leave-settings') ? 'active' : '' }}">Leave Settings</a></li>												
                                    </ul>												
                                </li>
                                <li><a href="{{url('attendance-admin')}}" class="{{ Request::is('attendance-admin') ? 'active subdrop' : '' }}">Attendance (Admin)</a></li>
                                <li><a href="{{url('attendance-employee')}}" class="{{ Request::is('attendance-employee') ? 'active' : '' }}">Attendance (Employee)</a></li>
                                <li><a href="{{url('timesheets')}}" class="{{ Request::is('timesheets') ? 'active' : '' }}">Timesheets</a></li>
                                <li><a href="{{url('schedule-timing')}}" class="{{ Request::is('schedule-timing') ? 'active' : '' }}">Shift & Schedule</a></li>
                                <li><a href="{{url('shift-master')}}" class="{{ Request::is('shift-master') ? 'active' : '' }}">Master Shift</a></li>
                                <li class="submenu submenu-two">
                                    <a href="javascript:void(0);" class="{{ Request::is('overtime-master','overtime','overtime-employee') ? 'active subdrop' : '' }}">Overtime<span class="menu-arrow inside-submenu"></span></a>
                                    <ul>
                                        <li><a href="{{url('overtime-master')}}" class="{{ Request::is('overtime-master') ? 'active' : '' }}">Master Overtime</a></li>
                                        <li><a href="{{url('overtime')}}" class="{{ Request::is('overtime') ? 'active' : '' }}">Overtime (Admin)</a></li>
                                        <li><a href="{{url('overtime-employee')}}" class="{{ Request::is('overtime-employee') ? 'active' : '' }}">Overtime (Employee)</a></li>
                                    </ul>
                                </li>
                            </ul>
                        </li>
@if ($isHcmAdmin || $superAdminUnlockedPerformance)
                        <li class="submenu">
                            <a href="javascript:void(0);"  class="{{ Request::is('performance-indicator','performance-review','performance-appraisal','goal-tracking','goal-type') ? 'active subdrop' : '' }}">
                                <i class="ti ti-school"></i><span>Performance</span>
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
                        <li class="submenu">
                            <a href="javascript:void(0);" class="{{ Request::is('training','trainers','training-type') ? 'active subdrop' : '' }}">
                                <i class="ti ti-edit"></i><span>Training</span>
                                <span class="menu-arrow"></span>
                            </a>
                            <ul>
                                <li><a href="{{url('training')}}" class="{{ Request::is('training') ? 'active' : '' }}">Training List</a></li>
                                <li><a href="{{url('trainers')}}" class="{{ Request::is('trainers') ? 'active' : '' }}">Trainers</a></li>
                                <li><a href="{{url('training-type')}}" class="{{ Request::is('training-type') ? 'active' : '' }}">Training Type</a></li>
                            </ul>
                        </li>
                        <li class="{{ Request::is('promotion') ? 'active' : '' }}">
                            <a href="{{url('promotion')}}">
                                <i class="ti ti-speakerphone"></i><span>Promotion</span>
                            </a>
                        </li>
                        <li class="{{ Request::is('resignation') ? 'active' : '' }}">
                            <a href="{{url('resignation')}}">
                                <i class="ti ti-external-link"></i><span>Resignation</span>
                            </a>
                        </li>
                        <li class="{{ Request::is('termination') ? 'active' : '' }}">
                            <a href="{{url('termination')}}">
                                <i class="ti ti-circle-x"></i><span>Termination</span>
                            </a>
                        </li>
                    </ul>
                </li>
@if ($showTemplateCatalogMenus)
                <li class="menu-title"><span>RECRUITMENT</span></li>
                <li>
                    <ul>
                        <li class="{{ Request::is('job-grid','job-grid-2','job-list') ? 'active' : '' }}">
                            <a href="{{url('job-grid')}}">
                                <i class="ti ti-timeline"></i><span>Jobs</span>
                            </a>
                        </li>
                        <li class="{{ Request::is('candidates-grid','candidates-kanban','candidates') ? 'active' : '' }}" >
                            <a href="{{url('candidates-grid')}}">
                                <i class="ti ti-user-shield"></i><span>Candidates</span>
                            </a>
                        </li>
                        <li class="{{ Request::is('refferals') ? 'active' : '' }}">
                            <a href="{{url('refferals')}}">
                                <i class="ti ti-ux-circle"></i><span>Referrals</span>
                            </a>
                        </li>
                    </ul>
                </li>
@endif
                <li class="menu-title"><span>FINANCE & ACCOUNTS</span></li>
                <li>
                    <ul>
                        <!-- <li class="submenu">
                            <a href="javascript:void(0);" class="{{ Request::is('estimates','invoice','payments','expenses','provident-fund','taxes') ? 'active subdrop' : '' }}">
                                <i class="ti ti-shopping-cart-dollar"></i><span>Sales</span>
                                <span class="menu-arrow"></span>
                            </a>
                            <ul>
                                <li><a href="{{url('estimates')}}" class="{{ Request::is('estimates') ? 'active' : '' }}">Estimates</a></li>
                                <li><a href="{{url('invoice')}}" class="{{ Request::is('invoice') ? 'active' : '' }}">Invoices</a></li>
                                <li><a href="{{url('payments')}}" class="{{ Request::is('payments') ? 'active' : '' }}">Payments</a></li>
                                <li><a href="{{url('expenses')}}" class="{{ Request::is('expenses') ? 'active' : '' }}">Expenses</a></li>
                                <li><a href="{{url('provident-fund')}}" class="{{ Request::is('provident-fund') ? 'active' : '' }}">Provident Fund</a></li>
                                <li><a href="{{url('taxes')}}" class="{{ Request::is('taxes') ? 'active' : '' }}">Taxes</a></li>
                            </ul>
                        </li>
                        <li class="submenu">
                            <a href="javascript:void(0);" class="{{ Request::is('categories','budgets','budget-expenses','budget-revenues') ? 'active subdrop' : '' }}">
                                <i class="ti ti-file-dollar"></i><span>Accounting</span>
                                <span class="menu-arrow"></span>
                            </a>
                            <ul>
                                <li><a href="{{url('categories')}}" class="{{ Request::is('categories') ? 'active' : '' }}">Categories</a></li>
                                <li><a href="{{url('budgets')}}" class="{{ Request::is('budgets') ? 'active' : '' }}">Budgets</a></li>
                                <li><a href="{{url('budget-expenses')}}" class="{{ Request::is('budget-expenses') ? 'active' : '' }}">Budget Expenses</a></li>
                                <li><a href="{{url('budget-revenues')}}" class="{{ Request::is('budget-revenues') ? 'active' : '' }}">Budget Revenues</a></li>
                            </ul>
                        </li> -->
@if ($isHcmAdmin || $superAdminUnlockedPayroll)
                        <li class="submenu">
                            <a href="javascript:void(0);" class="{{ Request::is('employee-salary','payslip','payroll-run','payroll-run-history','salary-component-master','payroll','payroll-overtime','payroll-deduction','payroll-thr','payroll-pkwt-compensation') ? 'active subdrop' : '' }}">
                                <i class="ti ti-cash"></i><span>Payroll</span>
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
                                    <a href="javascript:void(0);" class="{{ Request::is('payslip','payroll-run-history','salary-component-master') ? 'subdrop' : '' }}">Payroll Records &amp; Setup<span class="menu-arrow inside-submenu"></span></a>
                                    <ul>
                                        <li><a href="{{url('payslip')}}" class="{{ Request::is('payslip') ? 'active' : '' }}">Payslips</a></li>
                                        <li><a href="{{url('payroll-run-history')}}" class="{{ Request::is('payroll-run-history') ? 'active' : '' }}">Payroll History</a></li>
                                        <li><a href="{{url('salary-component-master')}}" class="{{ Request::is('salary-component-master') ? 'active' : '' }}">Salary Components</a></li>
                                    </ul>
                                </li>
                            </ul>
                        </li>
@endif
                    </ul>
                </li>						
                <li class="menu-title"><span>ADMINISTRATION</span></li>
                <li>
                    <ul>
@if ($canSeeAssetManagementMenu)
                        <li class="submenu">
                            <a href="javascript:void(0);" class="{{ Request::is('assets','asset-categories') ? 'active subdrop' : '' }}">
                                <i class="ti ti-cash"></i><span>Assets</span>
                                <span class="menu-arrow"></span>
                            </a>
                            <ul>
                                <li><a href="{{url('assets')}}" class="{{ Request::is('assets') ? 'active' : '' }}">Assets</a></li>
                                <li><a href="{{url('asset-categories')}}" class="{{ Request::is('asset-categories') ? 'active subdrop' : '' }}">Asset Categories</a></li>
                            </ul>
                        </li>
@endif
                        <li class="submenu">
                            <a href="javascript:void(0);" class="{{ Request::is('knowledgebase','knowledgebase/*','knowledgebase-details','activity') ? 'active subdrop' : '' }}">
                                <i class="ti ti-headset"></i><span>Help & Supports</span>
                                <span class="menu-arrow"></span>
                            </a>
                            <ul>
                                <li><a href="{{url('knowledgebase')}}" class="{{ Request::is('knowledgebase','knowledgebase/*','knowledgebase-details') ? 'active' : '' }}">Knowledge Base</a></li>
                                <li><a href="{{url('activity')}}" class="{{ Request::is('activity') ? 'active' : '' }}">Activities</a></li>
                            </ul>
                        </li>
@if ($isHcmAdmin)
                        <li class="submenu">
                            <a href="javascript:void(0);"class="{{ Request::is('users','roles-permissions') ? 'active subdrop' : '' }}">
                                <i class="ti ti-user-star"></i><span>User Management</span>
                                <span class="menu-arrow"></span>
                            </a>
                            <ul>
                                <li><a href="{{url('users')}}" class="{{ Request::is('users') ? 'active' : '' }}">Users</a></li>
                                <li><a href="{{url('roles-permissions')}}" class="{{ Request::is('roles-permissions') ? 'active' : '' }}">Roles & Permissions</a></li>
                            </ul>
                        </li>
@endif
@if ($isHcmAdmin)
                        <li class="submenu">
                            <a href="javascript:void(0);"class="{{ Request::is('expenses-report','invoice-report','payment-report','project-report','task-report','user-report','employee-report','payslip-report','attendance-report','leave-report','daily-report') ? 'active subdrop' : '' }}">
                                <i class="ti ti-user-star"></i><span>Reports</span>
                                <span class="menu-arrow"></span>
                            </a>
                            <ul>
                                <li><a href="{{url('expenses-report')}}" class="{{ Request::is('expenses-report') ? 'active' : '' }}">Expense Report</a></li>
                                <li><a href="{{url('invoice-report')}}" class="{{ Request::is('invoice-report') ? 'active' : '' }}">Invoice Report</a></li>
                                <li><a href="{{url('payment-report')}}" class="{{ Request::is('payment-report') ? 'active' : '' }}">Payment Report</a></li>
                                <li><a href="{{url('project-report')}}" class="{{ Request::is('project-report') ? 'active' : '' }}">Project Report</a></li>
                                <li><a href="{{url('task-report')}}" class="{{ Request::is('task-report') ? 'active' : '' }}">Task Report</a></li>
                                <li><a href="{{url('user-report')}}" class="{{ Request::is('user-report') ? 'active' : '' }}">User Report</a></li>
                                <li><a href="{{url('employee-report')}}" class="{{ Request::is('employee-report') ? 'active' : '' }}">Employee Report</a></li>
                                <li><a href="{{url('payslip-report')}}" class="{{ Request::is('payslip-report') ? 'active' : '' }}">Payslip Report</a></li>
                                <li><a href="{{url('attendance-report')}}" class="{{ Request::is('attendance-report') ? 'active' : '' }}">Attendance Report</a></li>
                                <li><a href="{{url('leave-report')}}" class="{{ Request::is('leave-report') ? 'active' : '' }}">Leave Report</a></li>
                                <li><a href="{{url('daily-report')}}" class="{{ Request::is('daily-report') ? 'active' : '' }}">Daily Report</a></li>
                            </ul>
                        </li>
@if ($isHcmAdmin)
                        <li class="submenu">
                            <a href="javascript:void(0);" class="{{ Request::is('countries','states','cities','villages') ? 'active subdrop' : '' }}">
                                <i class="ti ti-map-pin-check"></i><span>Data Alamat</span>
                                <span class="menu-arrow"></span>
                            </a>
                            <ul>
                                <li><a href="{{url('countries')}}" class="{{ Request::is('countries') ? 'active' : '' }}">Provinces</a></li>
                                <li><a href="{{url('states')}}" class="{{ Request::is('states') ? 'active' : '' }}">Regencies</a></li>
                                <li><a href="{{url('cities')}}" class="{{ Request::is('cities') ? 'active' : '' }}">Districts</a></li>
                                <li><a href="{{url('villages')}}" class="{{ Request::is('villages') ? 'active' : '' }}">Villages</a></li>
                            </ul>
                        </li>
@endif
                        <li class="submenu">
                            <a href="javascript:void(0);" class="{{ Request::is('profile-settings','security-settings','notification-settings','connected-apps',
                            'business-settings','seo-settings','localization-settings','prefixes','preferences','performance-appraisal','language','authentication-settings','ai-settings',
                            'salary-settings','approval-settings','invoice-settings','leave-type','custom-fields','email-settings','email-template','sms-settings','sms-template','otp-settings','gdpr','maintenance-mode',
                            'payment-gateways','tax-rates','currencies','custom-css','custom-js','cronjob','storage-settings','ban-ip-address','backup','clear-cache'


                            ) ? 'active subdrop' : '' }}">
                                <i class="ti ti-settings"></i><span>Settings</span>
                                <span class="menu-arrow"></span>
                            </a>
                            <ul>
                                <li class="submenu submenu-two">
                                    <a href="javascript:void(0);" class="{{ Request::is('profile-settings','security-settings','notification-settings','connected-apps') ? 'active subdrop' : '' }}">General Settings<span class="menu-arrow inside-submenu"></span></a>
                                    <ul>
                                        <li><a href="{{url('profile-settings')}}" class="{{ Request::is('profile-settings') ? 'active' : '' }}">Profile</a></li>
                                        <li><a href="{{url('security-settings')}}" class="{{ Request::is('security-settings') ? 'active' : '' }}">Security</a></li>
                                        <li><a href="{{url('notification-settings')}}" class="{{ Request::is('notification-settings') ? 'active' : '' }}">Notifications</a></li>
                                        <li><a href="{{url('connected-apps')}}" class="{{ Request::is('connected-apps') ? 'active' : '' }}">Connected Apps</a></li>
                                    </ul>
                                </li>
                                <li class="submenu submenu-two">
                                    <a href="javascript:void(0);" class="{{ Request::is('business-settings','seo-settings','localization-settings','prefixes','preferences','performance-appraisal','language','authentication-settings','ai-settings') ? 'active subdrop' : '' }}">Website Settings<span class="menu-arrow inside-submenu"></span></a>
                                    <ul>
                                        <li><a href="{{url('business-settings')}}" class="{{ Request::is('business-settings') ? 'active' : '' }}" >Business Settings</a></li>
                                        <li><a href="{{url('seo-settings')}}" class="{{ Request::is('seo-settings') ? 'active' : '' }}">SEO Settings</a></li>
                                        <li><a href="{{url('localization-settings')}}" class="{{ Request::is('localization-settings') ? 'active' : '' }}">Localization</a></li>
                                        <li><a href="{{url('prefixes')}}" class="{{ Request::is('prefixes') ? 'active' : '' }}">Prefixes</a></li>
                                        <li><a href="{{url('preferences')}}" class="{{ Request::is('preferences') ? 'active' : '' }}">Preferences</a></li>
                                        <li><a href="{{url('performance-appraisal')}}" class="{{ Request::is('performance-appraisal') ? 'active' : '' }}">Appearance</a></li>
                                        <li><a href="{{url('language')}}" class="{{ Request::is('language') ? 'active' : '' }}">Language</a></li>
                                        <li><a href="{{url('authentication-settings')}}" class="{{ Request::is('authentication-settings') ? 'active' : '' }}">Authentication</a></li>
                                        <li><a href="{{url('ai-settings')}}" class="{{ Request::is('ai-settings') ? 'active' : '' }}">AI Settings</a></li>
                                    </ul>
                                </li>
                                <li class="submenu submenu-two">
                                    <a href="javascript:void(0);" class="{{ Request::is('salary-settings','approval-settings','invoice-settings','leave-type','custom-fields') ? 'active subdrop' : '' }}">App Settings<span class="menu-arrow inside-submenu"></span></a>
                                    <ul>
                                        <li><a href="{{url('salary-settings')}}" class="{{ Request::is('salary-settings') ? 'active' : '' }}">Salary Settings</a></li>
                                        <li><a href="{{url('approval-settings')}}" class="{{ Request::is('approval-settings') ? 'active' : '' }}">Approval Settings</a></li>
                                        <li><a href="{{url('invoice-settings')}}" class="{{ Request::is('invoice-settings') ? 'active' : '' }}">Invoice Settings</a></li>
                                        <li><a href="{{url('leave-type')}}" class="{{ Request::is('leave-type') ? 'active' : '' }}">Leave Type</a></li>
                                        <li><a href="{{url('custom-fields')}}" class="{{ Request::is('custom-fields') ? 'active' : '' }}">Custom Fields</a></li>
                                    </ul>
                                </li>
                                @if ($isGlobalHcmAdmin)
                                <li class="submenu submenu-two">
                                    <a href="javascript:void(0);" class="{{ Request::is('email-settings','email-template','sms-settings','sms-template','otp-settings','gdpr','maintenance-mode') ? 'active subdrop' : '' }}">System Settings<span class="menu-arrow inside-submenu"></span></a>
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
                                <li class="submenu submenu-two">
                                    <a href="javascript:void(0);" class="{{ Request::is('payment-gateways','tax-rates','currencies') ? 'active subdrop' : '' }}" >Financial Settings<span class="menu-arrow inside-submenu"></span></a>
                                    <ul>
                                        <li><a href="{{url('payment-gateways')}}" class="{{ Request::is('payment-gateways') ? 'active' : '' }}">Payment Gateways</a></li>
                                        <li><a href="{{url('tax-rates')}}" class="{{ Request::is('tax-rates') ? 'active' : '' }}">Tax Rate</a></li>
                                        <li><a href="{{url('currencies')}}" class="{{ Request::is('currencies') ? 'active' : '' }}">Currencies</a></li>
                                    </ul>
                                </li>
                                <li class="submenu submenu-two">
                                    <a href="javascript:void(0);" class="{{ Request::is('custom-css','custom-js','cronjob','storage-settings','ban-ip-address','backup','clear-cache') ? 'active subdrop' : '' }}">Other Settings<span class="menu-arrow inside-submenu"></span></a>
                                    <ul>
                                        <li><a href="{{url('custom-css')}}" class="{{ Request::is('custom-css') ? 'active' : '' }}" >Custom CSS</a></li>
                                        <li><a href="{{url('custom-js')}}" class="{{ Request::is('custom-js') ? 'active' : '' }}">Custom JS</a></li>
                                        <li><a href="{{url('cronjob')}}" class="{{ Request::is('cronjob') ? 'active' : '' }}">Cronjob</a></li>
                                        <li><a href="{{url('storage-settings')}}" class="{{ Request::is('storage-settings') ? 'active' : '' }}">Storage</a></li>
                                        <li><a href="{{url('ban-ip-address')}}" class="{{ Request::is('ban-ip-address') ? 'active' : '' }}">Ban IP Address</a></li>
                                        <li><a href="{{url('backup')}}" class="{{ Request::is('backup') ? 'active' : '' }}">Backup</a></li>
                                        <li><a href="{{url('clear-cache')}}" class="{{ Request::is('clear-cache') ? 'active' : '' }}">Clear Cache</a></li>
                                    </ul>
                                </li>
                            </ul>
                        </li>
@endif
                    </ul>
                </li>
@if ($isHcmAdmin)
                <li class="menu-title"><span>CONTENT</span></li>
                <li>
                    <ul>
                        <li class="{{ Request::is('pages') ? 'active' : '' }}">
                            <a href="{{url('pages')}}">
                                <i class="ti ti-box-multiple"></i><span>Pages</span>
                            </a>
                        </li>
                        <li class="submenu">
                            <a href="javascript:void(0);" class="{{ Request::is('blogs','blog-categories','blog-comments','blog-tags') ? 'active subdrop' : '' }}">
                                <i class="ti ti-brand-blogger"></i><span>Blogs</span>
                                <span class="menu-arrow"></span>
                            </a>
                            <ul>
                                <li><a href="{{url('blogs')}}" class="{{ Request::is('blogs') ? 'active' : '' }}">All Blogs</a></li>
                                <li><a href="{{url('blog-categories')}}" class="{{ Request::is('blog-categories') ? 'active' : '' }}">Categories</a></li>
                                <li><a href="{{url('blog-comments')}}" class="{{ Request::is('blog-comments') ? 'active' : '' }}">Comments</a></li>
                                <li><a href="{{url('blog-tags')}}" class="{{ Request::is('blog-tags') ? 'active' : '' }}">Blog Tags</a></li>
                            </ul>
                        </li>
                        <li class="submenu">
                            <a href="javascript:void(0);" class="{{ Request::is('countries','states','cities','villages') ? 'active subdrop' : '' }}">
                                <i class="ti ti-map-pin-check"></i><span>Locations</span>
                                <span class="menu-arrow"></span>
                            </a>
                            <ul>
                                <li><a href="{{url('countries')}}" class="{{ Request::is('countries') ? 'active' : '' }}">Provinces</a></li>
                                <li><a href="{{url('states')}}" class="{{ Request::is('states') ? 'active' : '' }}">Regencies</a></li>
                                <li><a href="{{url('cities')}}" class="{{ Request::is('cities') ? 'active' : '' }}">Districts</a></li>
                                <li><a href="{{url('villages')}}" class="{{ Request::is('villages') ? 'active' : '' }}">Villages</a></li>
                            </ul>
                        </li>
                        <li class="{{ Request::is('testimonials') ? 'active' : '' }}">
                            <a href="{{url('testimonials')}}">
                                <i class="ti ti-message-2"></i><span>Testimonials</span>
                            </a>
                        </li>
                        <li class="{{ Request::is('faq') ? 'active' : '' }}">
                            <a href="{{url('faq')}}">
                                <i class="ti ti-question-mark"></i><span>FAQ’S</span>
                            </a>
                        </li>
                    </ul>
                </li>
@endif
@if ($showTemplateCatalogMenus)
                <li class="menu-title"><span>PAGES</span></li>
                <li>
                    <ul>
                        <li class="{{ Request::is('starter') ? 'active' : '' }}">
                            <a href="{{url('starter')}}">
                                <i class="ti ti-layout-sidebar"></i><span>Starter</span>
                            </a>
                        </li>
                        <li class="{{ Request::is('profile') ? 'active' : '' }}">
                            <a href="{{url('profile')}}">
                                <i class="ti ti-user-circle"></i><span>Profile</span>
                            </a>
                        </li>
                        <li class="{{ Request::is('gallery') ? 'active' : '' }}">
                            <a href="{{url('gallery')}}">
                                <i class="ti ti-photo"></i><span>Gallery</span>
                            </a>
                        </li>
                        <li class="{{ Request::is('search-result') ? 'active' : '' }}">
                            <a href="{{url('search-result')}}">
                                <i class="ti ti-list-search"></i><span>Search Results</span>
                            </a>
                        </li>
                        <li  class="{{ Request::is('timeline') ? 'active' : '' }}">
                            <a href="{{url('timeline')}}">
                                <i class="ti ti-timeline"></i><span>Timeline</span>
                            </a>
                        </li>
                        <li class="{{ Request::is('pricing') ? 'active' : '' }}">
                            <a href="{{url('pricing')}}" >
                                <i class="ti ti-file-dollar"></i><span>Pricing</span>
                            </a>
                        </li>
                        <li class="{{ Request::is('coming-soon') ? 'active' : '' }}">
                            <a href="{{url('coming-soon')}}">
                                <i class="ti ti-progress-bolt"></i><span>Coming Soon</span>
                            </a>
                        </li>
                        <li  class="{{ Request::is('under-maintenance') ? 'active' : '' }}">
                            <a href="{{url('under-maintenance')}}">
                                <i class="ti ti-alert-octagon"></i><span>Under Maintenance</span>
                            </a>
                        </li>
                        <li  class="{{ Request::is('under-construction') ? 'active' : '' }}">
                            <a href="{{url('under-construction')}}">
                                <i class="ti ti-barrier-block"></i><span>Under Construction</span>
                            </a>
                        </li>
                        <li class="{{ Request::is('api-keys') ? 'active' : '' }}">
                            <a href="{{url('api-keys')}}">
                                <i class="ti ti-api"></i><span>API Keys</span>
                            </a>
                        </li>
                        <li class="{{ Request::is('privacy-policy') ? 'active' : '' }}">
                            <a href="{{url('privacy-policy')}}">
                                <i class="ti ti-file-description"></i><span>Privacy Policy</span>
                            </a>
                        </li>
                        <li class="{{ Request::is('terms-condition') ? 'active' : '' }}">
                            <a href="{{url('terms-condition')}}" >
                                <i class="ti ti-file-check"></i><span>Terms & Conditions</span>
                            </a>
                        </li>
                    </ul>
                </li>
                @if ($showTemplateCatalogMenus)
                <li class="menu-title"><span>AUTHENTICATION</span></li>
                <li>
                    <ul>
                        <li class="submenu">
                            <a href="javascript:void(0);">
                                <i class="ti ti-login"></i><span>Login</span><span class="menu-arrow"></span>
                            </a>
                            <ul>
                                <li><a href="{{url('login')}}" class="{{ Request::is('login') ? 'active' : '' }}">Cover</a></li>
                                <li><a href="{{url('login-2')}}" class="{{ Request::is('login-2') ? 'active' : '' }}">Illustration</a></li>
                                <li><a href="{{url('login-3')}}" class="{{ Request::is('login-3') ? 'active' : '' }}">Basic</a></li>
                            </ul>
                        </li>
                        <li class="submenu">
                            <a href="javascript:void(0);">
                                <i class="ti ti-forms"></i><span>Register</span><span class="menu-arrow"></span>
                            </a>
                            <ul>
                                <li><a href="{{url('register')}}" class="{{ Request::is('register') ? 'active' : '' }}">Cover</a></li>
                                <li><a href="{{url('register-2')}}" class="{{ Request::is('register-2') ? 'active' : '' }}">Illustration</a></li>
                                <li><a href="{{url('register-3')}}" class="{{ Request::is('register-3') ? 'active' : '' }}">Basic</a></li>
                            </ul>
                        </li>
                        <li class="submenu">
                            <a href="javascript:void(0);">
                                <i class="ti ti-help-triangle"></i><span>Forgot Password</span><span class="menu-arrow"></span>
                            </a>
                            <ul>
                                <li><a href="{{url('forgot-password')}}" class="{{ Request::is('forgot-password') ? 'active' : '' }}">Cover</a></li>
                                <li><a href="{{url('forgot-password-2')}}" class="{{ Request::is('forgot-password-2') ? 'active' : '' }}">Illustration</a></li>
                                <li><a href="{{url('forgot-password-3')}}" class="{{ Request::is('forgot-password-3') ? 'active' : '' }}">Basic</a></li>
                            </ul>
                        </li>
                        <li class="submenu">
                            <a href="javascript:void(0);">
                                <i class="ti ti-restore"></i><span>Reset Password</span><span class="menu-arrow"></span>
                            </a>
                            <ul>
                                <li><a href="{{url('reset-password')}}" class="{{ Request::is('reset-password') ? 'active' : '' }}">Cover</a></li>
                                <li><a href="{{url('reset-password-2')}}" class="{{ Request::is('reset-password-2') ? 'active' : '' }}">Illustration</a></li>
                                <li><a href="{{url('reset-password-3')}}" class="{{ Request::is('reset-password-3') ? 'active' : '' }}">Basic</a></li>
                            </ul>
                        </li>
                        <li class="submenu">
                            <a href="javascript:void(0);">
                                <i class="ti ti-mail-exclamation"></i><span>Email Verification</span><span class="menu-arrow"></span>
                            </a>
                            <ul>
                                <li><a href="{{url('email-verification')}}" class="{{ Request::is('email-verification') ? 'active' : '' }}">Cover</a></li>
                                <li><a href="{{url('email-verification-2')}}" class="{{ Request::is('email-verification-2') ? 'active' : '' }}">Illustration</a></li>
                                <li><a href="{{url('email-verification-3')}}" class="{{ Request::is('email-verification-3') ? 'active' : '' }}">Basic</a></li>
                            </ul>
                        </li>
                        <li class="submenu">
                            <a href="javascript:void(0);">
                                <i class="ti ti-password"></i><span>2 Step Verification</span><span class="menu-arrow"></span>
                            </a>
                            <ul>
                                <li><a href="{{url('two-step-verification')}}" class="{{ Request::is('two-step-verification') ? 'active' : '' }}">Cover</a></li>
                                <li><a href="{{url('two-step-verification-2')}}" class="{{ Request::is('two-step-verification-2') ? 'active' : '' }}">Illustration</a></li>
                                <li><a href="{{url('two-step-verification-3')}}" class="{{ Request::is('two-step-verification-3') ? 'active' : '' }}">Basic</a></li>
                            </ul>
                        </li>
                        <li><a href="{{url('lock-screen')}}" class="{{ Request::is('lock-screen') ? 'active' : '' }}"><i class="ti ti-lock-square"></i><span>Lock Screen</span></a></li>
                        <li><a href="{{url('error-404')}}" class="{{ Request::is('error-404') ? 'active' : '' }}"><i class="ti ti-error-404"></i><span>404 Error</span></a></li>
                        <li><a href="{{url('error-500')}}" class="{{ Request::is('error-500') ? 'active' : '' }}"><i class="ti ti-server"></i><span>500 Error</span></a></li>
                    </ul>
                </li>
                @endif
                <li class="menu-title"><span>UI INTERFACE</span></li>
                <li>
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
                                    <a href="{{url('ui-ribbon')}}" class="{{ Request::is('ui-ribbon') ? 'active' : '' }}" >Ribbon</a>
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
                                    <a href="javascript:void(0);" class="{{ Request::is('form-basic-inputs',
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
                                            <a href="{{url('form-horizontal')}}" class="{{ Request::is('form-horizontal') ? 'active' : '' }}">Horizontal Form</a>
                                        </li>
                                        <li>
                                            <a href="{{url('form-vertical')}}" class="{{ Request::is('form-vertical') ? 'active' : '' }}">Vertical Form</a>
                                        </li>
                                        <li>
                                            <a href="{{url('form-floating-labels')}}" class="{{ Request::is('form-floating-labels') ? 'active' : '' }}">Floating Labels</a>
                                        </li>
                                    </ul>
                                </li>
                                <li>
                                    <a href="{{url('form-validation')}}" class="{{ Request::is('form-validation') ? 'active' : '' }}">Form Validation</a>
                                </li>
                                
                                <li>
                                    <a href="{{url('form-select2')}}" class="{{ Request::is('form-select2') ? 'active' : '' }}">Select2</a>
                                </li>
                                <li>
                                    <a href="{{url('form-wizard')}}" class="{{ Request::is('form-wizard') ? 'active' : '' }}">Form Wizard</a>
                                </li>
                                <li>
                                    <a href="{{url('form-pickers')}}" class="{{ Request::is('form-pickers') ? 'active' : '' }}">Form Picker</a>
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
                                    <a href="{{url('tables-basic')}}" class="{{ Request::is('tables-basic') ? 'active' : '' }}">Basic Tables </a>
                                </li>
                                <li>
                                    <a href="{{url('data-tables')}}" class="{{ Request::is('data-tables') ? 'active' : '' }}">Data Table </a>
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
                                    <a href="{{url('chart-apex')}}" class="{{ Request::is('chart-apex') ? 'active' : '' }}" >Apex Charts</a>
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
                            <a href="javascript:void(0);"class="{{ Request::is('icon-fontawesome','icon-tabler','icon-bootstrap',
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
                            <a href="javascript:void(0);" class="{{ Request::is('maps-vector','maps-leaflet') ? 'active' : '' }}">
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
                <li class="menu-title"><span>Extras</span></li>
                <li>
                    <ul>
                        <li>
                            <a href="javascript:void(0);"><i class="ti ti-file-text"></i><span>Documentation</span></a>
                        </li>
                        <li>
                            <a href="javascript:void(0);"><i class="ti ti-exchange"></i><span>Changelog</span><span class="badge bg-pink badge-xs text-white fs-10 ms-s">v4.0.2</span></a>
                        </li>
                        <li class="submenu">
                            <a href="javascript:void(0);"><i class="ti ti-menu-2"></i><span>Multi Level</span><span class="menu-arrow"></span></a>
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
@endif
            </ul>
        </div>
    </div>
</div>
<!-- /Sidebar -->

@if ($showTemplateCatalogMenus || $isHcmAdmin)
<!-- Horizontal Menu -->
<div class="sidebar sidebar-horizontal" id="horizontal-menu">
    <div class="sidebar-menu">
        <div class="main-menu">
            <ul class="nav-menu">
                <li class="menu-title">
                    <span>Main</span>
                </li>
                <li class="submenu">
                    <a href="#"   class="{{ Request::is('index','employee-dashboard') ? 'active subdrop' : '' }}">
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
                    <a href="#" class="{{ Request::is('dashboard','companies','subscription','packages','packages-grid','domain','purchase-transaction','saas/packages','saas/subscriptions','saas/domains','saas/transactions','saas/invoices','saas/payments','saas/reports','saas/reminders') ? 'active subdrop' : '' }}">
                        <i class="ti ti-user-star"></i><span>Super Admin</span>
                        <span class="menu-arrow"></span>
                    </a>
                    <ul>
                        <li><a href="{{url('dashboard')}}" class="{{ Request::is('dashboard') ? 'active' : '' }}">Dashboard</a></li>
                        <li><a href="{{url('companies')}}"  class="{{ Request::is('companies') ? 'active' : '' }}">Companies</a></li>
                        <li><a href="{{url('saas/subscriptions')}}" class="{{ Request::is('saas/subscriptions') ? 'active' : '' }}">Subscriptions</a></li>
                        <li><a href="{{url('packages')}}" class="{{ Request::is('packages','packages-grid','saas/packages') ? 'active' : '' }}">Packages</a></li>
                        <li><a href="{{url('domain')}}" class="{{ Request::is('domain','saas/domains') ? 'active' : '' }}">Domain</a></li>
                        <li><a href="{{url('purchase-transaction')}}" class="{{ Request::is('purchase-transaction','saas/transactions') ? 'active' : '' }}">Purchase Transaction</a></li>
                    </ul>
                </li>
@endif
                <li class="submenu">
                    <a href="#" class="{{ Request::is('chat','voice-call','video-call','outgoing-call','incoming-call','call-history',
                            'calendar','email','todo','notes','social-feed','file-manager','kanban-view','invoices','invoice-details') ? ' subdrop active ' : '' }}">
                        <i class="ti ti-layout-grid-add"></i><span>Applications</span>
                        <span class="menu-arrow"></span>
                    </a>
                    <ul>
                        <li><a href="{{url('chat')}}" class="{{ Request::is('chat') ? 'active' : '' }}">Chat</a></li>
                        <li class="submenu submenu-two">
                            <a href="{{url('call')}}" class="{{ Request::is('voice-call','video-call','outgoing-call','incoming-call','call-history') ? 'active subdrop' : '' }}">Calls<span class="menu-arrow inside-submenu"></span></a>
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
                        <li><a href="{{url('todo')}}" class="{{ Request::is('todo') ? 'active' : '' }}">To Do</a></li>
                        <li><a href="{{url('notes')}}" class="{{ Request::is('notes') ? 'active' : '' }}">Notes</a></li>
                        <li><a href="{{url('social-feed')}}" class="{{ Request::is('social-feed') ? 'active' : '' }}">Social Feed</a></li>
                        <li><a href="{{url('file-manager')}}" class="{{ Request::is('file-manager') ? 'active' : '' }}">File Manager</a></li>
                        <li><a href="{{url('kanban-view')}}" class="{{ Request::is('kanban-view') ? 'active' : '' }}">Kanban</a></li>
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
                        <li >
                            <a href="{{url('layout-horizontal')}}" class="{{ Request::is('layout-horizontal') ? 'active' : '' }}">
                                <span>Horizontal</span>
                            </a>
                        </li>
                        <li >
                            <a href="{{url('layout-detached')}}" class="{{ Request::is('layout-detached') ? 'active' : '' }}">
                                <span>Detached</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{url('layout-modern')}}"  class="{{ Request::is('layout-modern') ? 'active' : '' }}">
                                <span>Modern</span>
                            </a>
                        </li>
                        <li>
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
                            <a href="{{url('layout-box')}}" class="{{ Request::is('layout-box') ? 'active' : '' }}">
                                <span>Boxed</span>
                            </a>
                        </li>
                        <li>
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
                    <a href="#" class="{{ Request::is('clients-grid','clients','projects-grid','projects','tasks','task-board',
                   'contacts-grid','contacts','contact-details','companies-grid','companies-crm','company-details','deals-grid','deals-details','deals','leads-grid','leads-details','leads','pipeline','analytics','activity',
                    'employees','employee-details','departments','designations','policy','tickets','tickets-grid','ticket-details','holidays','leaves','leaves-employee','leave-settings','attendance-admin','attendance-employee',
                            'timesheets','schedule-timing','shift-master','overtime-master','overtime','overtime-employee','leaves','leaves-employee','leave-settings','performance-indicator','performance-review','performance-appraisal','goal-tracking','goal-type','training','trainers','training-type','promotion','resignation','termination') ? 'active ' : '' }}">
                        <i class="ti ti-user-star"></i><span>Projects</span>
                        <span class="menu-arrow"></span>
                    </a>
                    <ul>
                        <li>
                            <a href="{{url('clients-grid')}}" class="{{ Request::is('clients-grid','clients') ? 'active' : '' }}"><span>Clients</span>
                            </a>
                        </li>
                        <li class="submenu">
                            <a href="javascript:void(0);"class="{{ Request::is('projects-grid','tasks','task-board','projects','project-details','task-details') ? 'active subdrop' : '' }}"><span>Projects</span>
                                <span class="menu-arrow"></span>
                            </a>
                            <ul>
                                <li><a href="{{url('projects-grid')}}" class="{{ Request::is('projects-grid','project-details','projects') ? 'active' : '' }}">Projects</a></li>
                                <li><a href="{{url('tasks')}}" class="{{ Request::is('tasks','task-details') ? 'active' : '' }}">Tasks</a></li>
                                <li><a href="{{url('task-board')}}" class="{{ Request::is('task-board') ? 'active' : '' }}">Task Board</a></li>
                            </ul>
                        </li>		
                        <li class="submenu">
                            <a href="{{url('call')}}" class="{{ Request::is('contacts-grid','contacts','contact-details','companies-grid','companies-crm','company-details','deals-grid','deals-details','deals'
                            ,'leads-grid','leads-details','leads','pipeline','analytics','activity') ? 'active subdrop' : '' }}">Crm<span class="menu-arrow"></span></a>
                            <ul>
                                <li><a href="{{url('contacts-grid')}}" class="{{ Request::is('contacts-grid','contacts','contact-details') ? 'active' : '' }}"><span>Contacts</span></a></li>
                                <li><a href="{{url('companies-grid')}}" class="{{ Request::is('companies-grid','companies-crm','company-details') ? 'active' : '' }}"><span>Companies</span></a></li>
                                <li><a href="{{url('deals-grid')}}" class="{{ Request::is('deals-grid','deals-details','deals') ? 'active' : '' }}"><span>Deals</span></a></li>
                                <li><a href="{{url('leads-grid')}}" class="{{ Request::is('leads-grid','leads-details','leads') ? 'active' : '' }}"><span>Leads</span></a></li>
                                <li><a href="{{url('pipeline')}}" class="{{ Request::is('pipeline') ? 'active' : '' }}"><span>Pipeline</span></a></li>
                                <li><a href="{{url('analytics')}}" class="{{ Request::is('analytics') ? 'active' : '' }}"><span>Analytics</span></a></li>
                                <li><a href="{{url('activity')}}" class="{{ Request::is('activity') ? 'active' : '' }}"><span>Activities</span></a></li>
                            </ul>
                        </li>
                        <li class="submenu">
                            <a href="javascript:void(0);" class="{{ Request::is('employees','employee-details','departments','designations','policy') ? 'active subdrop' : '' }}"><span>Employees</span>
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
                        <li class="submenu">
                            <a href="javascript:void(0);"  class="{{ Request::is('ticket-master','tickets-admin','tickets-employee','tickets-grid','ticket-details*') ? 'active subdrop' : '' }}"><span>Tickets</span>
                                <span class="menu-arrow"></span>
                            </a>
                            <ul>
                                <li><a href="{{url('ticket-master')}}" class="{{ Request::is('ticket-master') ? 'active' : '' }}">Master Ticket</a></li>
                                <li><a href="{{url('tickets-admin')}}" class="{{ Request::is('tickets-admin','tickets-grid') ? 'active' : '' }}">Ticket (Admin)</a></li>
                                <li><a href="{{url('tickets-employee')}}" class="{{ Request::is('tickets-employee') ? 'active' : '' }}">Ticket (Employee)</a></li>
                            </ul>
                        </li>
                        <li class="{{ Request::is('holidays') ? 'active' : '' }}"><a href="{{url('holidays')}}"><span>Holidays</span></a></li>
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
@if ($isHcmAdmin || $hasPerformance)
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
                        <li class="submenu">
                            <a href="javascript:void(0);" class="{{ Request::is('training','trainers','training-type') ? 'active subdrop' : '' }}"><span>Training</span>
                                <span class="menu-arrow"></span>
                            </a>
                            <ul>
                                <li><a href="{{url('training')}}" class="{{ Request::is('training') ? 'active' : '' }}">Training List</a></li>
                                <li><a href="{{url('trainers')}}" class="{{ Request::is('trainers') ? 'active' : '' }}">Trainers</a></li>
                                <li><a href="{{url('training-type')}}" class="{{ Request::is('training-type') ? 'active' : '' }}">Training Type</a></li>
                            </ul>
                        </li>
                        <li><a href="{{url('promotion')}}" class="{{ Request::is('promotion') ? 'active' : '' }}"><span>Promotion</span></a></li>
                        <li><a href="{{url('resignation')}}" class="{{ Request::is('resignation') ? 'active' : '' }}"><span>Resignation</span></a></li>
                        <li><a href="{{url('termination')}}"  class="{{ Request::is('termination') ? 'active' : '' }}"><span>Termination</span></a></li>														
                    </ul>
                </li>
                <li class="submenu">
                    <a href="#" class="{{ Request::is('estimates','invoices','payments','expenses','provident-fund','taxes','categories','budgets','budget-expenses','budget-revenues','employee-salary','payslip','payroll','payroll-overtime','payroll-deduction','payroll-thr','payroll-pkwt-compensation',
                   'assets','asset-categories','knowledgebase','knowledgebase/*','activity', 'users','roles-permissions','expenses-report','invoice-report','payment-report','project-report','task-report','user-report','employee-report','payslip-report','attendance-report','leave-report','daily-report',
                   'profile-settings','security-settings','notification-settings','connected-apps','business-settings','seo-settings','localization-settings','prefixes','preferences','performance-appraisal','language','authentication-settings','ai-settings',
                    'salary-settings','approval-settings','invoice-settings','leave-type','custom-fields','email-settings','email-template','sms-settings','sms-template','otp-settings','gdpr','maintenance-mode','payment-gateways','tax-rates','currencies','custom-css','custom-js','cronjob','storage-settings','ban-ip-address','backup','clear-cache') ? 'active subdrop' : '' }}">
                        <i class="ti ti-user-star"></i><span>Administration</span>
                        <span class="menu-arrow"></span>
                    </a>
                    <ul>
                        <li class="submenu">
                            <a href="javascript:void(0);" class="{{ Request::is('estimates','invoice','payments','expenses','provident-fund','taxes') ? 'active subdrop' : '' }}"><span>Sales</span>
                                <span class="menu-arrow"></span>
                            </a>
                            <ul>
                                <li><a href="{{url('estimates')}}" class="{{ Request::is('estimates') ? 'active' : '' }}">Estimates</a></li>
                                <li><a href="{{url('invoice')}}" class="{{ Request::is('invoice') ? 'active' : '' }}">Invoices</a></li>
                                <li><a href="{{url('payments')}}" class="{{ Request::is('payments') ? 'active' : '' }}">Payments</a></li>
                                <li><a href="{{url('expenses')}}" class="{{ Request::is('expenses') ? 'active' : '' }}">Expenses</a></li>
                                <li><a href="{{url('provident-fund')}}" class="{{ Request::is('provident-fund') ? 'active' : '' }}">Provident Fund</a></li>
                                <li><a href="{{url('taxes')}}" class="{{ Request::is('taxes') ? 'active' : '' }}">Taxes</a></li>
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
@if ($isHcmAdmin || $hasPayroll)
                        <li class="submenu">
                            <a href="javascript:void(0);" class="{{ Request::is('employee-salary','payslip','payroll-run','payroll-run-history','salary-component-master','payroll','payroll-overtime','payroll-deduction','payroll-thr','payroll-pkwt-compensation') ? 'active subdrop' : '' }}"><span>Payroll</span>
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
                                    <a href="javascript:void(0);" class="{{ Request::is('payslip','payroll-run-history','salary-component-master') ? 'subdrop' : '' }}">Payroll Records &amp; Setup<span class="menu-arrow inside-submenu"></span></a>
                                    <ul>
                                        <li><a href="{{url('payslip')}}" class="{{ Request::is('payslip') ? 'active' : '' }}">Payslips</a></li>
                                        <li><a href="{{url('payroll-run-history')}}" class="{{ Request::is('payroll-run-history') ? 'active' : '' }}">Payroll History</a></li>
                                        <li><a href="{{url('salary-component-master')}}" class="{{ Request::is('salary-component-master') ? 'active' : '' }}">Salary Components</a></li>
                                    </ul>
                                </li>
                            </ul>
                        </li>
@endif
@if ($canSeeAssetManagementMenu)
                        <li class="submenu">
                            <a href="javascript:void(0);" class="{{ Request::is('assets','asset-categories') ? 'active subdrop' : '' }}"><span>Assets</span>
                                <span class="menu-arrow"></span>
                            </a>
                            <ul>
                                <li><a href="{{url('assets')}}" class="{{ Request::is('assets') ? 'active' : '' }}">Assets</a></li>
                                <li><a href="{{url('asset-categories')}}" class="{{ Request::is('asset-categories') ? 'active' : '' }}">Asset Categories</a></li>
                            </ul>
                        </li>
@endif
                        <li class="submenu">
                            <a href="javascript:void(0);" class="{{ Request::is('knowledgebase','knowledgebase/*','knowledgebase-details','activity') ? 'active subdrop' : '' }}"><span>Help & Supports</span>
                                <span class="menu-arrow"></span>
                            </a>
                            <ul>
                                <li><a href="{{url('knowledgebase')}}" class="{{ Request::is('knowledgebase','knowledgebase/*','knowledgebase-details') ? 'active' : '' }}">Knowledge Base</a></li>
                                <li><a href="{{url('activity')}}" class="{{ Request::is('activity') ? 'active' : '' }}">Activities</a></li>
                            </ul>
                        </li>
@if ($isHcmAdmin)
                        <li class="submenu">
                            <a href="javascript:void(0);" class="{{ Request::is('users','roles-permissions') ? 'active subdrop' : '' }}"><span>User Management</span>
                                <span class="menu-arrow"></span>
                            </a>
                            <ul>
                                <li><a href="{{url('users')}}" class="{{ Request::is('users') ? 'active' : '' }}">Users</a></li>
                                <li><a href="{{url('roles-permissions')}}" class="{{ Request::is('roles-permissions') ? 'active' : '' }}">Roles & Permissions</a></li>
                            </ul>
                        </li>
@endif
@if ($isHcmAdmin)
                        <li class="submenu">
                            <a href="javascript:void(0);" class="{{ Request::is('expenses-report','invoice-report','payment-report','project-report','task-report','user-report','employee-report','payslip-report','attendance-report','leave-report','daily-report') ? 'active subdrop' : '' }}"><span>Reports</span>
                                <span class="menu-arrow"></span>
                            </a>
                            <ul>
                                <li><a href="{{url('expenses-report')}}" class="{{ Request::is('expenses-report') ? 'active' : '' }}">Expense Report</a></li>
                                <li><a href="{{url('invoice-report')}}" class="{{ Request::is('invoice-report') ? 'active' : '' }}">Invoice Report</a></li>
                                <li><a href="{{url('payment-report')}}" class="{{ Request::is('payment-report') ? 'active' : '' }}">Payment Report</a></li>
                                <li><a href="{{url('project-report')}}" class="{{ Request::is('project-report') ? 'active' : '' }}">Project Report</a></li>
                                <li><a href="{{url('task-report')}}" class="{{ Request::is('task-report') ? 'active' : '' }}">Task Report</a></li>
                                <li><a href="{{url('user-report')}}" class="{{ Request::is('user-report') ? 'active' : '' }}">User Report</a></li>
                                <li><a href="{{url('employee-report')}}" class="{{ Request::is('employee-report') ? 'active' : '' }}">Employee Report</a></li>
                                <li><a href="{{url('payslip-report')}}" class="{{ Request::is('payslip-report') ? 'active' : '' }}">Payslip Report</a></li>
                                <li><a href="{{url('attendance-report')}}" class="{{ Request::is('attendance-report') ? 'active' : '' }}">Attendance Report</a></li>
                                <li><a href="{{url('leave-report')}}" class="{{ Request::is('leave-report') ? 'active' : '' }}">Leave Report</a></li>
                                <li><a href="{{url('daily-report')}}" class="{{ Request::is('daily-report') ? 'active' : '' }}">Daily Report</a></li>
                            </ul>
                        </li>
                        <li class="submenu">
                            <a href="javascript:void(0);" class="{{ Request::is('profile-settings','security-settings','notification-settings','connected-apps','business-settings','seo-settings','localization-settings','prefixes','preferences','performance-appraisal','language','authentication-settings','ai-settings',
                            'salary-settings','approval-settings','invoice-settings','leave-type','custom-fields','email-settings','email-template','sms-settings','sms-template','otp-settings','gdpr','maintenance-mode','payment-gateways','tax-rates','currencies','custom-css','custom-js','cronjob','storage-settings','ban-ip-address','backup','clear-cache') ? 'active subdrop' : '' }}"><span>Settings</span>
                                <span class="menu-arrow"></span>
                            </a>
                            <ul>
                                <li class="submenu">
                                    <a href="javascript:void(0);" class="{{ Request::is('profile-settings','security-settings','notification-settings','connected-apps') ? 'active subdrop' : '' }}">General Settings<span class="menu-arrow"></span></a>
                                    <ul>
                                        <li><a href="{{url('profile-settings')}}" class="{{ Request::is('profile-settings') ? 'active' : '' }}">Profile</a></li>
                                        <li><a href="{{url('security-settings')}}" class="{{ Request::is('security-settings') ? 'active' : '' }}">Security</a></li>
                                        <li><a href="{{url('notification-settings')}}" class="{{ Request::is('notification-settings') ? 'active' : '' }}">Notifications</a></li>
                                        <li><a href="{{url('connected-apps')}}" class="{{ Request::is('connected-apps') ? 'active' : '' }}">Connected Apps</a></li>
                                    </ul>
                                </li>
                                <li class="submenu">
                                    <a href="javascript:void(0);" class="{{ Request::is('business-settings','seo-settings','localization-settings','prefixes','preferences','performance-appraisal','language','authentication-settings','ai-settings') ? 'active subdrop' : '' }}">Website Settings<span class="menu-arrow"></span></a>
                                    <ul>
                                        <li><a href="{{url('business-settings')}}" class="{{ Request::is('business-settings') ? 'active' : '' }}">Business Settings</a></li>
                                        <li><a href="{{url('seo-settings')}}" class="{{ Request::is('seo-settings') ? 'active' : '' }}">SEO Settings</a></li>
                                        <li><a href="{{url('localization-settings')}}" class="{{ Request::is('localization-settings') ? 'active' : '' }}">Localization</a></li>
                                        <li><a href="{{url('prefixes')}}" class="{{ Request::is('prefixes') ? 'active' : '' }}">Prefixes</a></li>
                                        <li><a href="{{url('preferences')}}" class="{{ Request::is('preferences') ? 'active' : '' }}">Preferences</a></li>
                                        <li><a href="{{url('performance-appraisal')}}" class="{{ Request::is('performance-appraisal') ? 'active' : '' }}">Appearance</a></li>
                                        <li><a href="{{url('language')}}" class="{{ Request::is('language') ? 'active' : '' }}">Language</a></li>
                                        <li><a href="{{url('authentication-settings')}}" class="{{ Request::is('authentication-settings') ? 'active' : '' }}">Authentication</a></li>
                                        <li><a href="{{url('ai-settings')}}" class="{{ Request::is('ai-settings') ? 'active' : '' }}">AI Settings</a></li>
                                    </ul>
                                </li>
                                <li class="submenu">
                                    <a href="javascript:void(0);" class="{{ Request::is('salary-settings','approval-settings','invoice-settings','leave-type','custom-fields') ? 'active subdrop' : '' }}">App Settings<span class="menu-arrow"></span></a>
                                    <ul>
                                        <li><a href="{{url('salary-settings')}}" class="{{ Request::is('salary-settings') ? 'active' : '' }}">Salary Settings</a></li>
                                        <li><a href="{{url('approval-settings')}}" class="{{ Request::is('approval-settings') ? 'active' : '' }}">Approval Settings</a></li>
                                        <li><a href="{{url('invoice-settings')}}" class="{{ Request::is('invoice-settings') ? 'active' : '' }}">Invoice Settings</a></li>
                                        <li><a href="{{url('leave-type')}}" class="{{ Request::is('leave-type') ? 'active' : '' }}">Leave Type</a></li>
                                        <li><a href="{{url('custom-fields')}}" class="{{ Request::is('custom-fields') ? 'active' : '' }}">Custom Fields</a></li>
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
                                <li class="submenu">
                                    <a href="javascript:void(0);" class="{{ Request::is('payment-gateways','tax-rates','currencies') ? 'active subdrop' : '' }}">Financial Settings<span class="menu-arrow"></span></a>
                                    <ul>
                                        <li><a href="{{url('payment-gateways')}}" class="{{ Request::is('payment-gateways') ? 'active' : '' }}">Payment Gateways</a></li>
                                        <li><a href="{{url('tax-rates')}}" class="{{ Request::is('tax-rates') ? 'active' : '' }}">Tax Rate</a></li>
                                        <li><a href="{{url('currencies')}}" class="{{ Request::is('currencies') ? 'active' : '' }}">Currencies</a></li>
                                    </ul>
                                </li>
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
                            </ul>
                        </li>
@endif
                    </ul>
                </li>
                <li class="submenu">
                    <a href="#" class="{{ Request::is('starter','profile','gallery','search-result','timeline','pricing','coming-soon','under-maintenance','under-construction','api-keys','privacy-policy','terms-condition',
                    'pages','blogs','blog-categories','blog-comments','blog-tags','countries','states','cities','villages','testimonials','faq') ? 'active' : '' }}">
                        <i class="ti ti-page-break"></i><span>Pages</span>
                        <span class="menu-arrow"></span>
                    </a>
                    <ul>
                        <li><a href="{{url('starter')}}" class="{{ Request::is('starter') ? 'active' : '' }}"><span>Starter</span></a></li>
                        <li><a href="{{url('profile')}}" class="{{ Request::is('profile') ? 'active' : '' }}"><span>Profile</span></a></li>
                        <li><a href="{{url('gallery')}}" class="{{ Request::is('gallery') ? 'active' : '' }}"><span>Gallery</span></a></li>
                        <li><a href="{{url('search-result')}}" class="{{ Request::is('search-result') ? 'active' : '' }}"><span>Search Results</span></a></li>
                        <li><a href="{{url('timeline')}}" class="{{ Request::is('timeline') ? 'active' : '' }}"><span>Timeline</span></a></li>
                        <li><a href="{{url('pricing')}}"  class="{{ Request::is('pricing') ? 'active' : '' }}"><span>Pricing</span></a></li>
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
                                    <a href="javascript:void(0);" class="{{ Request::is('blogs','blog-categories','blog-comments','blog-tags') ? 'active' : '' }}">Blogs<span class="menu-arrow"></span></a>
                                    <ul>
                                        <li class="{{ Request::is('blogs') ? 'active' : '' }}"><a href="{{url('blogs')}}">All Blogs</a></li>
                                        <li class="{{ Request::is('blog-categories') ? 'active' : '' }}"><a href="{{url('blog-categories')}}">Categories</a></li>
                                        <li class="{{ Request::is('blog-comments') ? 'active' : '' }}"><a href="{{url('blog-comments')}}">Comments</a></li>
                                        <li class="{{ Request::is('blog-tags') ? 'active' : '' }}"><a href="{{url('blog-tags')}}">Tags</a></li>
                                    </ul>
                                </li>
                                <li class="submenu">
                                    <a href="javascript:void(0);" class="{{ Request::is('countries','states','cities','villages') ? 'active' : '' }}">Locations<span class="menu-arrow"></span></a>
                                    <ul>
                                        <li><a href="{{url('countries')}}" class="{{ Request::is('countries') ? 'active' : '' }}">Provinces</a></li>
                                        <li><a href="{{url('states')}}" class="{{ Request::is('states') ? 'active' : '' }}">Regencies</a></li>
                                        <li><a href="{{url('cities')}}" class="{{ Request::is('cities') ? 'active' : '' }}">Districts</a></li>
                                    </ul>
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
                                        <li><a href="{{url('register-3')}}" class="{{ Request::is('register-3') ? 'active' : '' }}">Basic</a></li>
                                    </ul>
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
                                        <li><a href="{{url('email-verification-3')}}" class="{{ Request::is('email-verification-3') ? 'active' : '' }}">Basic</a></li>
                                    </ul>
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
                                    <a href="javascript:void(0);"  class="{{ Request::is('form-basic-inputs',
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
                                            <a href="javascript:void(0);" class="{{ Request::is('form-basic-inputs',
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
            <div class="d-xl-flex align-items-center d-none">
                <a href="#" class="me-3 avatar avatar-sm">
                    <img src="{{URL::asset('build/img/profiles/avatar-07.jpg')}}" alt="profile" class="rounded-circle">
                </a>
                <a href="#" class="btn btn-icon btn-sm rounded-circle mode-toggle">
                    <i class="ti ti-sun"></i>
                </a>
            </div>
        </div>
    </div>
</div>
<!-- /Horizontal Menu -->
@endif

<!-- Two Col Sidebar -->
<div class="two-col-sidebar" id="two-col-sidebar">
    <div class="sidebar sidebar-twocol">
        <div class="twocol-mini">
            <a href="{{url('index')}}" class="logo-small">
                <img src="{{URL::asset('build/img/image111.png')}}" alt="Logo">
            </a>
            <div class="sidebar-left slimscroll">
                <div class="nav flex-column align-items-center nav-pills" id="sidebar-tabs" role="tablist" aria-orientation="vertical">
                    <a href="#" class="nav-link {{ Request::is('index','employee-dashboard','deals-dashboard','leads-dashboard') ? ' show active ' : '' }}" title="Dashboard" data-bs-toggle="tab" data-bs-target="#dashboard">
                        <i class="ti ti-smart-home"></i>
                    </a>
                    <a href="#" class="nav-link {{ Request::is('chat','voice-call','video-call','outgoing-call','incoming-call','call-history',
                            'calendar','email','todo','notes','social-feed','file-manager','kanban-view','invoices') ? ' show active ' : '' }}" title="Apps" data-bs-toggle="tab" data-bs-target="#application">
                        <i class="ti ti-layout-grid-add"></i>
                    </a>
                    @if ($isGlobalHcmAdmin)
                    <a href="#" class="nav-link {{ Request::is('dashboard','companies','subscription','packages','packages-grid','domain','purchase-transaction','saas/packages','saas/subscriptions','saas/domains','saas/transactions') ? 'show active' : '' }}" title="Super Admin" data-bs-toggle="tab" data-bs-target="#super-admin">
                        <i class="ti ti-user-star"></i>
                    </a>
                    @endif
                    <a href="#" class="nav-link {{ Request::is('layout-horizontal','layout-detached','layout-modern',
                    'layout-two-column','layout-hovered','layout-box','layout-horizontal-single','layout-horizontal-overlay','layout-horizontal-box',
                    'layout-horizontal-sidemenu','layout-vertical-transparent','layout-without-header','layout-rtl','layout-dark') ? 'show active' : '' }}" title="Layout" data-bs-toggle="tab" data-bs-target="#layout">
                        <i class="ti ti-layout-board-split"></i>
                    </a>
                    <a href="#" class="nav-link {{ Request::is('clients','projects-grid','clients-grid','tasks','task-board','project-details','projects') ? ' show active ' : '' }}" title="Projects" data-bs-toggle="tab" data-bs-target="#projects">
                        <i class="ti ti-users-group"></i>
                    </a>
                    <a href="#" class="nav-link {{ Request::is('contacts-grid','contacts','contact-details','companies-grid','companies-crm','company-details','deals-grid','deals-details','deals'
                            ,'leads-grid','leads-details','leads','pipeline','analytics','activity') ? 'show active' : '' }}" title="Crm" data-bs-toggle="tab" data-bs-target="#crm">
                        <i class="ti ti-user-shield"></i>
                    </a>
                    <a href="#" class="nav-link {{ Request::is('employees','employee-details','departments','designations','policy','tickets','tickets-grid','ticket-details','holidays',
                        'leaves','leaves-employee','leave-settings','attendance-admin','attendance-employee',
                            'timesheets','schedule-timing','shift-master','overtime-master','overtime','overtime-employee','performance-indicator','performance-review','performance-appraisal','goal-tracking','goal-type','training','trainers','training-type','promotion','resignation','termination') ? ' show active ' : '' }}" title="Hrm" data-bs-toggle="tab" data-bs-target="#hrm">
                        <i class="ti ti-user"></i>
                    </a>
                    <a href="#" class="nav-link {{Request::is('estimates','invoices','payments','expenses','provident-fund','taxes','categories','budgets','budget-expenses','budget-revenues','employee-salary','payslip','payroll','payroll-overtime','payroll-deduction','payroll-thr') ? ' show active ' : '' }}" title="Finance" data-bs-toggle="tab" data-bs-target="#finance">
                        <i class="ti ti-shopping-cart-dollar"></i>
                    </a>
                    <a href="#" class="nav-link {{ Request::is('assets','asset-categories','knowledgebase','knowledgebase/*','activity','users','roles-permissions',
                        'expenses-report','invoice-report','payment-report','project-report','task-report','user-report','employee-report','payslip-report','attendance-report','leave-report','daily-report',
                        'profile-settings','security-settings','notification-settings','connected-apps','business-settings','seo-settings','localization-settings','prefixes','preferences','performance-appraisal','language','authentication-settings','ai-settings',
                            'salary-settings','approval-settings','invoice-settings','leave-type','custom-fields','email-settings','email-template','sms-settings','sms-template','otp-settings','gdpr','maintenance-mode','payment-gateways','tax-rates','currencies','custom-css','custom-js','cronjob','storage-settings','ban-ip-address','backup','clear-cache') ? 'show active ' : '' }}" title="Administration" data-bs-toggle="tab" data-bs-target="#administration">
                        <i class="ti ti-cash"></i>
                    </a>
                    <a href="#" class="nav-link {{ Request::is('pages','blogs','blog-categories','blog-comments','blog-tags','countries','states','cities','villages','testimonials','faq') ? '  active subdrop' : '' }}" title="Content" data-bs-toggle="tab" data-bs-target="#content">
                        <i class="ti ti-license"></i>
                    </a>
                    <a href="#" class="nav-link {{ Request::is('starter','profile','gallery','search-result','timeline','pricing','coming-soon','under-maintenance','under-construction','api-keys','privacy-policy','terms-condition') ? '  active subdrop' : '' }}" title="Pages" data-bs-toggle="tab" data-bs-target="#pages">
                        <i class="ti ti-page-break"></i>
                    </a>
                    <a href="#" class="nav-link {{ Request::is('login','login-2','login-3','register','register-2','register-3',
                    'forgot-password','forgot-password-2','forgot-password-3','reset-password','reset-password-2','reset-password-3','email-verification','email-verification-2','email-verification-3',
                   'two-step-verification','two-step-verification-2','two-step-verification-3','lock-screen','error-404','error-500' ) ? ' show active' : '' }} " title="Authentication" data-bs-toggle="tab"
                        data-bs-target="#authentication">
                        <i class="ti ti-lock-check"></i>
                    </a>
                    <a href="#" class="nav-link {{ Request::is('ui-alerts',
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
                                

                                ) ? ' show active ' : '' }}" title="UI Elements" data-bs-toggle="tab"
                        data-bs-target="#ui-elements">
                        <i class="ti ti-ux-circle"></i>
                    </a>
                    <a href="#" class="nav-link {{ Request::is('maps-vector','maps-leaflet') ? 'active' : '' }}" title="Extras" data-bs-toggle="tab" data-bs-target="#extras">
                        <i class="ti ti-vector-triangle"></i>
                    </a>
                </div>
            </div>
        </div>
        <div class="sidebar-right">
            <div class="sidebar-logo mb-4">
                <a href="{{url('index')}}" class="logo logo-normal">
                    <img src="{{URL::asset('build/img/')}}" alt="Logo">
                </a>
                <a href="{{url('index')}}" class="dark-logo">
                    <img src="{{URL::asset('build/img/logo-white.svg')}}" alt="Logo">
                </a>
            </div>
            <div class="sidebar-scroll">
                <h6 class="mb-3">Welcome to SmartHR</h6>
                <div class="text-center rounded bg-light p-3 mb-4">
                    <div class="avatar avatar-lg online mb-3">
                        <img src="{{URL::asset('build/img/profiles/avatar-02.jpg')}}" alt="Img" class="img-fluid rounded-circle">
                    </div>
                    <h6 class="fs-12 fw-normal mb-1">Adrian Herman</h6>
                    <p class="fs-10">System Admin</p>
                </div>
                <div class="tab-content" id="v-pills-tabContent">
                    <div class="tab-pane fade {{ Request::is('index','employee-dashboard') ? ' show active ' : '' }}" id="dashboard">
                        <ul>
                            <li class="menu-title"><span>MAIN MENU</span></li>
                            <li><a href="{{url('index')}}" class="{{ Request::is('index') ? 'active' : '' }}">Admin Dashboard</a></li>
                            <li><a href="{{url('employee-dashboard')}}" class="{{ Request::is('employee-dashboard') ? 'active' : '' }}">Employee Dashboard</a></li>
                        </ul>
                    </div>
                    <div class="tab-pane fade {{ Request::is('chat','voice-call','video-call','outgoing-call','incoming-call','call-history',
                            'calendar','email','todo','notes','social-feed','file-manager','kanban-view','invoices','invoice-details') ? ' show active ' : '' }}" id="application">
                        <ul>
                            <li class="menu-title"><span>APPLICATION</span></li>
                            <li><a href="{{url('voice-call')}}"class="{{ Request::is('voice-call') ? 'active' : '' }}" >Voice Call</a></li>
                            <li><a href="{{url('video-call')}}" class="{{ Request::is('video-call') ? 'active' : '' }}">Video Call</a></li>
                            <li><a href="{{url('outgoing-call')}}" class="{{ Request::is('outgoing-call') ? 'active' : '' }}">Outgoing Call</a></li>
                            <li><a href="{{url('incoming-call')}}" class="{{ Request::is('incoming-call') ? 'active' : '' }}">Incoming Call</a></li>
                            <li><a href="{{url('call-history')}}" class="{{ Request::is('call-history') ? 'active' : '' }}">Call History</a></li>
                            <li><a href="{{url('calendar')}}" class="{{ Request::is('calendar') ? 'active' : '' }}">Calendar</a></li>
                            <li><a href="{{url('email')}}" class="{{ Request::is('email') ? 'active' : '' }}">Email</a></li>
                            <li><a href="{{url('todo')}}" class="{{ Request::is('todo') ? 'active' : '' }}">To Do</a></li>
                            <li><a href="{{url('notes')}}"class="{{ Request::is('notes') ? 'active' : '' }}">Notes</a></li>
                            <li><a href="{{url('social-active')}}" class="{{ Request::is('social-active') ? 'active' : '' }}">File Manager</a></li>
                            <li><a href="{{url('file-manager')}}" class="{{ Request::is('file-manager') ? 'active' : '' }}">File Manager</a></li>
                            <li><a href="{{url('kanban-view')}}" class="{{ Request::is('kanban-view') ? 'active' : '' }}">Kanban</a></li>
                            <li><a href="{{url('invoices')}}" class="{{ Request::is('invoices','invoice-details') ? 'active' : '' }}">Invoices</a></li>
                        </ul>
                    </div>
@if ($isGlobalHcmAdmin)
                    <div class="tab-pane fade {{ Request::is('dashboard','companies','subscription','packages','packages-grid','domain','purchase-transaction','saas/packages','saas/subscriptions','saas/domains','saas/transactions') ? '  show active' : '' }}" id="super-admin">
                        <ul>
                            <li class="menu-title"><span>SUPER ADMIN</span></li>
                            <li><a href="{{url('dashboard')}}" class="{{ Request::is('dashboard') ? 'active' : '' }}">Dashboard</a></li>
                            <li><a href="{{url('companies')}}" class="{{ Request::is('companies') ? 'active' : '' }}">Companies</a></li>
                            <li><a href="{{url('saas/subscriptions')}}" class="{{ Request::is('saas/subscriptions') ? 'active' : '' }}">Subscriptions</a></li>
                            <li><a href="{{url('packages')}}" class="{{ Request::is('packages','packages-grid','saas/packages') ? 'active' : '' }}">Packages</a></li>
                            <li><a href="{{url('domain')}}" class="{{ Request::is('domain','saas/domains') ? 'active' : '' }}">Domain</a></li>
                            <li><a href="{{url('purchase-transaction')}}" class="{{ Request::is('purchase-transaction','saas/transactions') ? 'active' : '' }}">Purchase Transaction</a></li>
                        </ul>
                    </div>
@endif
                    <div class="tab-pane fade {{ Request::is('layout-horizontal','layout-detached','layout-modern',
                    'layout-two-column','layout-hovered','layout-box','layout-horizontal-single','layout-horizontal-overlay','layout-horizontal-box',
                    'layout-horizontal-sidemenu','layout-vertical-transparent','layout-without-header','layout-rtl','layout-dark') ? 'show active' : '' }}" id="layout">
                        <ul>
                            <li class="menu-title"><span>LAYOUT</span></li>
                            <li><a href="{{url('layout-horizontal')}}" class="{{ Request::is('layout-horizontal') ? 'active' : '' }}"><span>Horizontal</span></a></li>
                            <li><a href="{{url('layout-detached')}}" class="{{ Request::is('layout-detached') ? 'active' : '' }}"><span>Detached</span></a></li>
                            <li><a href="{{url('layout-modern')}}" class="{{ Request::is('layout-modern') ? 'active' : '' }}"><span>Modern</span></a></li>
                            <li><a href="{{url('layout-two-column')}}" class="{{ Request::is('layout-two-column') ? 'active' : '' }}"><span>Two Column </span></a></li>
                            <li><a href="{{url('layout-hovered')}}" class="{{ Request::is('layout-hovered') ? 'active' : '' }}"><span>Hovered</span></a></li>
                            <li><a href="{{url('layout-box')}}" class="{{ Request::is('layout-box') ? 'active' : '' }}"><span>Boxed</span></a></li>
                            <li><a href="{{url('layout-horizontal-single')}}" class="{{ Request::is('layout-horizontal-single') ? 'active' : '' }}"><span>Horizontal Single</span></a></li>
                            <li><a href="{{url('layout-horizontal-overlay')}}" class="{{ Request::is('layout-horizontal-overlay') ? 'active' : '' }}"><span>Horizontal Overlay</span></a></li>
                            <li><a href="{{url('layout-horizontal-box')}}" class="{{ Request::is('layout-horizontal-box') ? 'active' : '' }}"><span>Horizontal Box</span></a></li>
                            <li><a href="{{url('layout-horizontal-sidemenu')}}" class="{{ Request::is('layout-horizontal-sidemenu') ? 'active' : '' }}"><span>Menu Aside</span></a></li>
                            <li><a href="{{url('layout-vertical-transparent')}}" class="{{ Request::is('layout-vertical-transparent') ? 'active' : '' }}"><span>Transparent</span></a></li>
                            <li><a href="{{url('layout-without-header')}}" class="{{ Request::is('layout-without-header') ? 'active' : '' }}"><span>Without Header</span></a></li>
                            <li><a href="{{url('layout-rtl')}}" class="{{ Request::is('layout-rtl') ? 'active' : '' }}"><span>RTL</span></a></li>
                            <li><a href="{{url('layout-dark')}}" class="{{ Request::is('layout-dark') ? 'active' : '' }}"><span>Dark</span></a></li>
                        </ul>
                    </div>
                    <div class="tab-pane fade {{ Request::is('projects-grid','clients-grid','clients','tasks','task-board','project-details','projects') ? 'show active ' : '' }}" id="projects">
                        <ul>
                            <li class="menu-title"><span>PROJECTS</span></li>
                            <li class="{{ Request::is('clients-grid','clients') ? 'active' : '' }}"><a href="{{url('clients-grid')}}">Clients</a></li>
                            <li class="submenu">
                                <a href="javascript:void(0);"><span>Projects</span>
                                    <span class="menu-arrow"></span>
                                </a>
                                <ul>
                                    <li><a href="{{url('projects-grid')}}" class="{{ Request::is('projects-grid','project-details','projects') ? 'active' : '' }}">Projects</a></li>
                                    <li><a href="{{url('tasks')}}" class="{{ Request::is('tasks','task-details') ? 'active' : '' }}">Tasks</a></li>
                                    <li><a href="{{url('task-board')}}" class="{{ Request::is('task-board') ? 'active' : '' }}">Task Board</a></li>
                                </ul>
                            </li>	
                        </ul>
                    </div>
                    <div class="tab-pane fade {{ Request::is('contacts-grid','contacts','contact-details','companies-grid','companies-crm','company-details','deals-grid','deals-details','deals'
                            ,'leads-grid','leads-details','leads','pipeline','analytics','activity') ? ' show active ' : '' }}" id="crm">
                        <ul>
                            <li class="menu-title"><span>CRM</span></li>
                            <li><a href="{{url('contacts-grid')}}" class="{{ Request::is('contacts-grid','contacts','contact-details') ? 'active' : '' }}" ><span>Contacts</span></a></li>
                            <li><a href="{{url('companies-grid')}}" class="{{ Request::is('companies-grid','companies-crm','company-details') ? 'active' : '' }}"><span>Companies</span></a></li>
                            <li><a href="{{url('deals-grid')}}" class="{{ Request::is('deals-grid','deals-details','deals') ? 'active' : '' }}"><span>Deals</span></a></li>
                            <li><a href="{{url('leads-grid')}}" class="{{ Request::is('leads-grid','leads-details','leads') ? 'active' : '' }}"><span>Leads</span></a></li>
                            <li><a href="{{url('pipeline')}}" class="{{ Request::is('pipeline') ? 'active' : '' }}"><span>Pipeline</span></a></li>
                            <li><a href="{{url('analytics')}}" class="{{ Request::is('analytics') ? 'active' : '' }}"><span>Analytics</span></a></li>
                            <li><a href="{{url('activity')}}" class="{{ Request::is('activity') ? 'active' : '' }}"><span>Activities</span></a></li>
                        </ul>
                    </div>
                    <div class="tab-pane fade {{ Request::is('employees','employee-details','departments','designations','policy',
                    'tickets','tickets-grid','ticket-details','holidays','leaves','leaves-employee','leave-settings','attendance-admin','attendance-employee',
                            'timesheets','schedule-timing','shift-master','overtime-master','overtime','overtime-employee','performance-indicator','performance-review','performance-appraisal','goal-tracking','goal-type','training','trainers','training-type','promotion','resignation','termination') ? ' show active' : '' }}" id="hrm">
                        <ul>
                            <li class="menu-title"><span>HRM</span></li>
                            <li class="submenu">
                                <a href="javascript:void(0);" class="{{ Request::is('employees','employee-details','departments','designations','policy') ? 'active subdrop' : '' }}"><span>Employees</span>
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
                            <li class="{{ Request::is('holidays') ? 'active' : '' }}"><a href="{{url('holidays')}}"><span>Holidays</span></a></li>
                            <li class="submenu">
                                <a href="javascript:void(0);" class="{{ Request::is('leaves','leaves-employee','leave-settings','attendance-admin','attendance-employee',
                            'timesheets','schedule-timing','shift-master','overtime-master','overtime','overtime-employee') ? 'active subdrop' : '' }}"><span>Attendance</span>
                                    <span class="menu-arrow"></span>
                                </a>
                                <ul>
                                    <li class="submenu submenu-two">
                                        <a href="javascript:void(0);" class="{{ Request::is('leaves','leaves-employee','leave-settings') ? 'active subdrop' : '' }}">Leaves<span
                                                class="menu-arrow inside-submenu"></span></a>
                                        <ul>
                                            <li><a href="{{url('leaves')}}" class="{{ Request::is('leaves') ? 'active' : '' }}">Leaves (Admin)</a></li>
                                            <li><a href="{{url('leaves-employee')}}" class="{{ Request::is('leaves-employee') ? 'active' : '' }}">Leave (Employee)</a></li>
                                            <li><a href="{{url('leave-settings')}}" class="{{ Request::is('leave-settings') ? 'active' : '' }}">Leave Settings</a></li>												
                                        </ul>												
                                    </li>
                                    <li><a href="{{url('attendance-admin')}}" class="{{ Request::is('attendance-admin') ? 'active' : '' }}">Attendance (Admin)</a></li>
                                    <li><a href="{{url('attendance-employee')}}" class="{{ Request::is('attendance-employee') ? 'active' : '' }}">Attendance (Employee)</a></li>
                                    <li><a href="{{url('timesheets')}}" class="{{ Request::is('timesheets') ? 'active' : '' }}">Timesheets</a></li>
                                    <li><a href="{{url('schedule-timing')}}" class="{{ Request::is('schedule-timing') ? 'active' : '' }}">Shift & Schedule</a></li>
                                    <li><a href="{{url('shift-master')}}" class="{{ Request::is('shift-master') ? 'active' : '' }}">Master Shift</a></li>
                                    <li class="submenu submenu-two">
                                        <a href="javascript:void(0);" class="{{ Request::is('overtime-master','overtime','overtime-employee') ? 'active subdrop' : '' }}">Overtime<span
                                                class="menu-arrow inside-submenu"></span></a>
                                        <ul>
                                            <li><a href="{{url('overtime-master')}}" class="{{ Request::is('overtime-master') ? 'active' : '' }}">Master Overtime</a></li>
                                            <li><a href="{{url('overtime')}}" class="{{ Request::is('overtime') ? 'active' : '' }}">Overtime (Admin)</a></li>
                                            <li><a href="{{url('overtime-employee')}}" class="{{ Request::is('overtime-employee') ? 'active' : '' }}">Overtime (Employee)</a></li>
                                        </ul>
                                    </li>
                                </ul>
                            </li>
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
                            <li class="submenu">
                                <a href="javascript:void(0);" class="{{ Request::is('training','trainers','training-type','promotion','resignation','termination') ? 'active subdrop' : '' }}"><span>Training</span>
                                    <span class="menu-arrow"></span>
                                </a>
                                <ul>
                                    <li><a href="{{url('training')}}" class="{{ Request::is('training') ? 'active' : '' }}">Training List</a></li>
                                    <li><a href="{{url('trainers')}}" class="{{ Request::is('trainers') ? 'active' : '' }}">Trainers</a></li>
                                    <li><a href="{{url('training-type')}}" class="{{ Request::is('training-type') ? 'active' : '' }}">Training Type</a></li>
                                </ul>
                            </li>
                            <li><a href="{{url('promotion')}}" class="{{ Request::is('promotion') ? 'active' : '' }}"><span>Promotion</span></a></li>
                            <li><a href="{{url('resignation')}}" class="{{ Request::is('resignation') ? 'active' : '' }}"><span>Resignation</span></a></li>
                            <li><a href="{{url('termination')}}" class="{{ Request::is('termination') ? 'active' : '' }}"><span>Termination</span></a></li>
                        </ul>
                    </div>
                    <div class="tab-pane fade {{ Request::is('estimates','invoices','payments','expenses','provident-fund','taxes','categories','budgets','budget-expenses','budget-revenues','employee-salary','payslip','payroll','payroll-overtime','payroll-deduction','payroll-thr') ? ' show active' : '' }}" id="finance">
                        <ul>
                            <li class="menu-title"><span>FINANCE & ACCOUNTS</span></li>
                            <li class="submenu">
                                <a href="javascript:void(0);" class="{{ Request::is('estimates','invoice','payments','expenses','provident-fund','taxes') ? 'active subdrop' : '' }}"><span>Sales</span>
                                    <span class="menu-arrow"></span>
                                </a>
                                <ul>
                                    <li><a href="{{url('estimates')}}" class="{{ Request::is('estimates') ? 'active' : '' }}">Estimates</a></li>
                                    <li><a href="{{url('invoice')}}" class="{{ Request::is('invoice') ? 'active' : '' }}">Invoices</a></li>
                                    <li><a href="{{url('payments')}}" class="{{ Request::is('payments') ? 'active' : '' }}">Payments</a></li>
                                    <li><a href="{{url('expenses')}}" class="{{ Request::is('expenses') ? 'active' : '' }}">Expenses</a></li>
                                    <li><a href="{{url('provident-fund')}}" class="{{ Request::is('provident-fund') ? 'active' : '' }}">Provident Fund</a></li>
                                    <li><a href="{{url('taxes')}}" class="{{ Request::is('taxes') ? 'active' : '' }}">Taxes</a></li>
                                </ul>
                            </li>
                            <li class="submenu">
                                <a href="javascript:void(0);" class="{{ Request::is('categories','budgets','budget-expenses','budget-revenues') ? 'active subdrop' : '' }}"><span>Accounting</span>
                                    <span class="menu-arrow"></span>
                                </a>
                                <ul>
                                    <li><a href="{{url('categories')}}" class="{{ Request::is('categories') ? 'active' : '' }}">Categories</a></li>
                                    <li><a href="{{url('budgets')}}" class="{{ Request::is('budgets') ? 'active' : '' }}">Budgets</a></li>
                                    <li><a href="{{url('budget-expenses')}}" class="{{ Request::is('budget-expenses') ? 'active' : '' }}">Budget Expenses</a></li>
                                    <li><a href="{{url('budget-revenues')}}" class="{{ Request::is('budget-revenues') ? 'active' : '' }}">Budget Revenues</a></li>
                                </ul>
                            </li>
                            <li class="submenu">
                                <a href="javascript:void(0);" class="{{ Request::is('employee-salary','payslip','payroll-run','payroll-run-history','salary-component-master','payroll','payroll-overtime','payroll-deduction','payroll-thr','payroll-pkwt-compensation') ? 'active subdrop' : '' }}"><span>Payroll</span>
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
                                        <a href="javascript:void(0);" class="{{ Request::is('payslip','payroll-run-history','salary-component-master') ? 'subdrop' : '' }}">Payroll Records &amp; Setup<span class="menu-arrow inside-submenu"></span></a>
                                        <ul>
                                            <li><a href="{{url('payslip')}}" class="{{ Request::is('payslip') ? 'active' : '' }}">Payslips</a></li>
                                            <li><a href="{{url('payroll-run-history')}}" class="{{ Request::is('payroll-run-history') ? 'active' : '' }}">Payroll History</a></li>
                                            <li><a href="{{url('salary-component-master')}}" class="{{ Request::is('salary-component-master') ? 'active' : '' }}">Salary Components</a></li>
                                        </ul>
                                    </li>
                                </ul>
                            </li>									
                        </ul>
                    </div>
                    <div class="tab-pane fade {{ Request::is('assets',
                    'asset-categories',
                    'knowledgebase','knowledgebase/*','activity',
                    'users','roles-permissions',
                    'expenses-report','invoice-report','payment-report','project-report','task-report','user-report','employee-report','payslip-report','attendance-report','leave-report','daily-report',
                    'profile-settings','security-settings','notification-settings','connected-apps',
                    'business-settings','seo-settings','localization-settings','prefixes','preferences','performance-appraisal','language','authentication-settings','ai-settings',
                    'salary-settings','approval-settings','invoice-settings','leave-type','custom-fields',
                    'email-settings','email-template','sms-settings','sms-template','otp-settings','gdpr','maintenance-mode',
                    'payment-gateways','tax-rates','currencies',
                    'custom-css','custom-js','cronjob','storage-settings','ban-ip-address','backup','clear-cache'
                    ) ? ' show active ' : '' }}" id="administration">
                        <ul>
                            <li class="menu-title"><span>ADMINISTRATION</span></li>
@if ($canSeeAssetManagementMenu)
                            <li class="submenu">
                                <a href="javascript:void(0);" class="{{ Request::is('assets','asset-categories') ? 'active subdrop' : '' }}"><span>Assets</span>
                                    <span class="menu-arrow"></span>
                                </a>
                                <ul>
                                    <li><a href="{{url('assets')}}" class="{{ Request::is('overtime') ? 'active' : '' }}">Assets</a></li>
                                    <li><a href="{{url('asset-categories')}}" class="{{ Request::is('overtime') ? 'active' : '' }}">Asset Categories</a></li>
                                </ul>
                            </li>
@endif
                            <li class="submenu">
                                <a href="javascript:void(0);" class="{{ Request::is('knowledgebase','knowledgebase/*','knowledgebase-details','activity') ? 'active subdrop' : '' }}"><span>Help & Supports</span>
                                    <span class="menu-arrow"></span>
                                </a>
                                <ul>
                                    <li><a href="{{url('knowledgebase')}}" class="{{ Request::is('knowledgebase','knowledgebase/*','knowledgebase-details') ? 'active' : '' }}">Knowledge Base</a></li>
                                    <li><a href="{{url('activity')}}" class="{{ Request::is('activity') ? 'active' : '' }}">Activities</a></li>
                                </ul>
                            </li>
@if ($isHcmAdmin)
                            <li class="submenu">
                                <a href="javascript:void(0);" class="{{ Request::is('users','roles-permissions') ? 'active subdrop' : '' }}"><span>User Management</span>
                                    <span class="menu-arrow"></span>
                                </a>
                                <ul>
                                    <li><a href="{{url('users')}}" class="{{ Request::is('users') ? 'active' : '' }}">Users</a></li>
                                    <li><a href="{{url('roles-permissions')}}" class="{{ Request::is('roles-permissions') ? 'active' : '' }}">Roles & Permissions</a></li>
                                </ul>
                            </li>
@endif
@if ($isHcmAdmin)
                            <li class="submenu">
                                <a href="javascript:void(0);" class="{{ Request::is('expenses-report','invoice-report','payment-report','project-report','task-report','user-report','employee-report','payslip-report','attendance-report','leave-report','daily-report') ? 'active subdrop' : '' }}"><span>Reports</span>
                                    <span class="menu-arrow"></span>
                                </a>
                                <ul>
                                    <li><a href="{{url('expenses-report')}}" class="{{ Request::is('expenses-report') ? 'active' : '' }}">Expense Report</a></li>
                                    <li><a href="{{url('invoice-report')}}" class="{{ Request::is('invoice-report') ? 'active' : '' }}">Invoice Report</a></li>
                                    <li><a href="{{url('payment-report')}}" class="{{ Request::is('payment-report') ? 'active' : '' }}">Payment Report</a></li>
                                    <li><a href="{{url('project-report')}}" class="{{ Request::is('project-report') ? 'active' : '' }}">Project Report</a></li>
                                    <li><a href="{{url('task-report')}}" class="{{ Request::is('task-report') ? 'active' : '' }}">Task Report</a></li>
                                    <li><a href="{{url('user-report')}}" class="{{ Request::is('user-report') ? 'active' : '' }}">User Report</a></li>
                                    <li><a href="{{url('employee-report')}}" class="{{ Request::is('employee-report') ? 'active' : '' }}">Employee Report</a></li>
                                    <li><a href="{{url('payslip-report')}}" class="{{ Request::is('payslip-report') ? 'active' : '' }}">Payslip Report</a></li>
                                    <li><a href="{{url('attendance-report')}}" class="{{ Request::is('attendance-repor') ? 'active' : '' }}">Attendance Report</a></li>
                                    <li><a href="{{url('leave-report')}}" class="{{ Request::is('leave-report') ? 'active' : '' }}">Leave Report</a></li>
                                    <li><a href="{{url('daily-report')}}" class="{{ Request::is('daily-report') ? 'active' : '' }}">Daily Report</a></li>
                                </ul>
                            </li>
                            <li class="submenu">
                                <a href="javascript:void(0);" class="{{ Request::is('profile-settings','security-settings','notification-settings','connected-apps') ? 'active subdrop' : '' }}">
                                    General Settings
                                    <span class="menu-arrow"></span>
                                </a>
                                <ul>
                                    <li><a href="{{url('profile-settings')}}" class="{{ Request::is('profile-settings') ? 'active' : '' }}">Profile</a></li>
                                    <li><a href="{{url('security-settings')}}" class="{{ Request::is('security-settings') ? 'active' : '' }}">Security</a></li>
                                    <li><a href="{{url('notification-settings')}}" class="{{ Request::is('notification-settings') ? 'active' : '' }}">Notifications</a></li>
                                    <li><a href="{{url('connected-apps')}}" class="{{ Request::is('connected-apps') ? 'active' : '' }}">Connected Apps</a></li>
                                </ul>
                            </li>
                            <li class="submenu">
                                <a href="javascript:void(0);" class="{{ Request::is('business-settings','seo-settings','localization-settings','prefixes','preferences','performance-appraisal','language','authentication-settings','ai-settings') ? 'active subdrop' : '' }}">
                                    Website Settings
                                    <span class="menu-arrow"></span>
                                </a>
                                <ul>
                                    <li><a href="{{url('business-settings')}}" class="{{ Request::is('business-settings') ? 'active' : '' }}">Business Settings</a></li>
                                    <li><a href="{{url('seo-settings')}}" class="{{ Request::is('seo-settings') ? 'active' : '' }}">SEO Settings</a></li>
                                    <li><a href="{{url('localization-settings')}}" class="{{ Request::is('localization-settings') ? 'active' : '' }}">Localization</a></li>
                                    <li><a href="{{url('prefixes')}}" class="{{ Request::is('prefixes') ? 'active' : '' }}">Prefixes</a></li>
                                    <li><a href="{{url('preferences')}}" class="{{ Request::is('preferences') ? 'active' : '' }}">Preferences</a></li>
                                    <li><a href="{{url('performance-appraisal')}}" class="{{ Request::is('performance-appraisal') ? 'active' : '' }}">Appearance</a></li>
                                    <li><a href="{{url('language')}}" class="{{ Request::is('language') ? 'active' : '' }}">Language</a></li>
                                    <li><a href="{{url('authentication-settings')}}" class="{{ Request::is('authentication-settings') ? 'active' : '' }}">Authentication</a></li>
                                    <li><a href="{{url('ai-settings')}}" class="{{ Request::is('ai-settings') ? 'active' : '' }}">AI Settings</a></li>
                                </ul>
                            </li>
                            <li class="submenu">
                                <a href="javascript:void(0);" class="{{ Request::is('salary-settings','approval-settings','invoice-settings','leave-type','custom-fields') ? 'active subdrop' : '' }}">App Settings<span class="menu-arrow"></span></a>
                                <ul>
                                    <li><a href="{{url('salary-settings')}}" class="{{ Request::is('salary-settings') ? 'active' : '' }}">Salary Settings</a></li>
                                    <li><a href="{{url('approval-settings')}}" class="{{ Request::is('approval-settings') ? 'active' : '' }}">Approval Settings</a></li>
                                    <li><a href="{{url('invoice-settings')}}" class="{{ Request::is('invoice-settings') ? 'active' : '' }}">Invoice Settings</a></li>
                                    <li><a href="{{url('leave-type')}}" class="{{ Request::is('leave-type') ? 'active' : '' }}">Leave Type</a></li>
                                    <li><a href="{{url('custom-fields')}}" class="{{ Request::is('custom-fields') ? 'active' : '' }}">Custom Fields</a></li>
                                </ul>
                            </li>
                            @if ($isGlobalHcmAdmin)
                            <li class="submenu">
                                <a href="javascript:void(0);" class="{{ Request::is('email-settings','email-template','sms-settings','sms-template','otp-settings','gdpr','maintenance-mode') ? 'active subdrop' : '' }}">
                                    System Settings
                                    <span class="menu-arrow"></span>
                                </a>
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
                            <li class="submenu">
                                <a href="javascript:void(0);" class="{{ Request::is('payment-gateways','tax-rates','currencies') ? 'active subdrop' : '' }}">
                                    Financial Settings
                                    <span class="menu-arrow"></span>
                                </a>
                                <ul>
                                    <li><a href="{{url('payment-gateways')}}" class="{{ Request::is('payment-gateways') ? 'active' : '' }}">Payment Gateways</a></li>
                                    <li><a href="{{url('tax-rates')}}" class="{{ Request::is('tax-rates') ? 'active' : '' }}">Tax Rate</a></li>
                                    <li><a href="{{url('currencies')}}" class="{{ Request::is('currencies') ? 'active' : '' }}">Currencies</a></li>
                                </ul>
                            </li>
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
                    </div>
                    <div class="tab-pane fade {{ Request::is('pages','blogs','blog-categories','blog-comments','blog-tags','countries','states','cities','villages','testimonials','faq') ? 'active' : '' }}" id="content">
                        <ul>
                            <li class="menu-title"><span>CONTENT</span></li>
                            @if ($isHcmAdmin)
                            <li class="{{ Request::is('pages') ? 'active' : '' }}"><a href="{{url('pages')}}">Pages</a></li>
                            @endif
                            <li class="submenu">
                                <a href="javascript:void(0);" class="{{ Request::is('blogs','blog-categories','blog-comments','blog-tags') ? 'active' : '' }}">
                                    Blogs
                                    <span class="menu-arrow"></span>
                                </a>
                                <ul>
                                    <li><a href="{{url('blogs')}}" class="{{ Request::is('blogs') ? 'active' : '' }}">All Blogs</a></li>
                                    <li><a href="{{url('blog-categories')}}" class="{{ Request::is('blog-categories') ? 'active' : '' }}">Categories</a></li>
                                    <li><a href="{{url('blog-comments')}}" class="{{ Request::is('blog-comments') ? 'active' : '' }}">Comments</a></li>
                                    <li><a href="{{url('blog-tags')}}" class="{{ Request::is('blog-tags') ? 'active' : '' }}">Blog Tags</a></li>
                                </ul>
                            </li>
                            <li class="submenu">
                                <a href="javascript:void(0);" class="{{ Request::is('countries','states','cities','villages') ? 'active' : '' }}">
                                    Locations
                                    <span class="menu-arrow"></span>
                                </a>
                                <ul>
                                    <li><a href="{{url('countries')}}" class="{{ Request::is('countries') ? 'active' : '' }}">Provinces</a></li>
                                    <li><a href="{{url('states')}}" class="{{ Request::is('states') ? 'active' : '' }}">Regencies</a></li>
                                    <li><a href="{{url('cities')}}" class="{{ Request::is('cities') ? 'active' : '' }}">Districts</a></li>
                                </ul>
                            </li>
                            <li><a href="{{url('testimonials')}}" class="{{ Request::is('testimonials') ? 'active' : '' }}">Testimonials</a></li>
                            <li><a href="{{url('faq')}}" class="{{ Request::is('faq') ? 'active' : '' }}">FAQ’S</a></li>
                        </ul>
                    </div>
                    <div class="tab-pane fade {{ Request::is('starter','profile','gallery','search-result','timeline','pricing','coming-soon','under-maintenance','under-construction','api-keys','privacy-policy','terms-condition'
                    ) ? ' show active' : '' }} " id="pages">
                        <ul>
                            <li class="menu-title"><span>PAGES</span></li>
                            <li><a href="{{url('starter')}}" class="{{ Request::is('starter') ? 'active' : '' }}"><span>Starter</span></a></li>
                            <li><a href="{{url('profile')}}" class="{{ Request::is('profile') ? 'active' : '' }}"><span>Profile</span></a></li>
                            <li><a href="{{url('gallery')}}" class="{{ Request::is('gallery') ? 'active' : '' }}"><span>Gallery</span></a></li>
                            <li><a href="{{url('search-result')}}" class="{{ Request::is('search-result') ? 'active' : '' }}"><span>Search Results</span></a></li>
                            <li><a href="{{url('timeline')}}" class="{{ Request::is('timeline') ? 'active' : '' }}"><span>Timeline</span></a></li>
                            <li><a href="{{url('pricing')}}" class="{{ Request::is('pricing') ? 'active' : '' }}"><span>Pricing</span></a></li>
                            <li><a href="{{url('coming-soon')}}" class="{{ Request::is('coming-soon') ? 'active' : '' }}"><span>Coming Soon</span></a></li>
                            <li><a href="{{url('under-maintenance')}}" class="{{ Request::is('under-maintenance') ? 'active' : '' }}"><span>Under Maintenance</span></a></li>
                            <li><a href="{{url('under-construction')}}" class="{{ Request::is('under-construction') ? 'active' : '' }}"><span>Under Construction</span></a></li>
                            <li><a href="{{url('api-keys')}}" class="{{ Request::is('api-keys') ? 'active' : '' }}"><span>API Keys</span></a></li>
                            <li><a href="{{url('privacy-policy')}}" class="{{ Request::is('privacy-policy') ? 'active' : '' }}"><span>Privacy Policy</span></a></li>
                            <li><a href="{{url('terms-condition')}}" class="{{ Request::is('terms-condition') ? 'active' : '' }}"><span>Terms & Conditions</span></a></li>
                        </ul>
                    </div>
                    @if ($showTemplateCatalogMenus)
                    <div class="tab-pane fade {{ Request::is('login','login-2','login-3','register','register-2','register-3',
                    'forgot-password','forgot-password-2','forgot-password-3','reset-password','reset-password-2','reset-password-3','email-verification','email-verification-2','email-verification-3',
                   'two-step-verification','two-step-verification-2','two-step-verification-3','lock-screen','error-404','error-500' ) ? ' show active' : '' }} " id="authentication">
                        <ul>
                            <li class="menu-title"><span>AUTHENTICATION</span></li>
                            <li class="submenu">
                                <a href="javascript:void(0);" class="{{ Request::is('login','login-2','login-3') ? 'active' : '' }}">
                                    Login<span class="menu-arrow"></span>
                                </a>
                                <ul>
                                    <li><a href="{{url('login')}}" class="{{ Request::is('login') ? 'active' : '' }}">Cover</a></li>
                                    <li><a href="{{url('login-2')}}" class="{{ Request::is('login-2') ? 'active' : '' }}">Illustration</a></li>
                                    <li><a href="{{url('login-3')}}" class="{{ Request::is('login-3') ? 'active' : '' }}">Basic</a></li>
                                </ul>
                            </li>
                            <li class="submenu">
                                <a href="javascript:void(0);" class="{{ Request::is('register','register-2','register-3') ? 'active' : '' }}">
                                    Register<span class="menu-arrow"></span>
                                </a>
                                <ul>
                                    <li><a href="{{url('register')}}" class="{{ Request::is('register') ? 'active' : '' }}">Cover</a></li>
                                    <li><a href="{{url('register-2')}}" class="{{ Request::is('register-2') ? 'active' : '' }}">Illustration</a></li>
                                    <li><a href="{{url('register-3')}}" class="{{ Request::is('register-3') ? 'active' : '' }}">Basic</a></li>
                                </ul>
                            </li>
                            <li class="submenu">
                                <a href="javascript:void(0);" class="{{ Request::is('forgot-password','forgot-password-2','forgot-password-3') ? 'active' : '' }}">
                                    Forgot Password<span class="menu-arrow"></span>
                                </a>
                                <ul>
                                    <li><a href="{{url('forgot-password')}}" class="{{ Request::is('forgot-password') ? 'active' : '' }}">Cover</a></li>
                                    <li><a href="{{url('forgot-password-2')}}" class="{{ Request::is('forgot-password-2') ? 'active' : '' }}">Illustration</a></li>
                                    <li><a href="{{url('forgot-password-3')}}" class="{{ Request::is('forgot-password-3') ? 'active' : '' }}">Basic</a></li>
                                </ul>
                            </li>
                            <li class="submenu">
                                <a href="javascript:void(0);" class="{{ Request::is('reset-password','reset-password-2','reset-password-3') ? 'active' : '' }}">
                                    Reset Password<span class="menu-arrow"></span>
                                </a>
                                <ul>
                                    <li><a href="{{url('reset-password')}}" class="{{ Request::is('reset-password') ? 'active' : '' }}">Cover</a></li>
                                    <li><a href="{{url('reset-password-2')}}" class="{{ Request::is('reset-password-2') ? 'active' : '' }}">Illustration</a></li>
                                    <li><a href="{{url('reset-password-3')}}" class="{{ Request::is('reset-password-3') ? 'active' : '' }}">Basic</a></li>
                                </ul>
                            </li>
                            <li class="submenu">
                                <a href="javascript:void(0);" class="{{ Request::is('email-verification','email-verification-2','email-verification-3') ? 'active' : '' }}">
                                    Email Verification<span class="menu-arrow"></span>
                                </a>
                                <ul>
                                    <li><a href="{{url('email-verification')}}" class="{{ Request::is('email-verification') ? 'active' : '' }}">Cover</a></li>
                                    <li><a href="{{url('email-verification-2')}}" class="{{ Request::is('email-verification-2') ? 'active' : '' }}">Illustration</a></li>
                                    <li><a href="{{url('email-verification-3')}}" class="{{ Request::is('email-verification-3') ? 'active' : '' }}">Basic</a></li>
                                </ul>
                            </li>
                            <li class="submenu">
                                <a href="javascript:void(0);" class="{{ Request::is('two-step-verification','two-step-verification-2','two-step-verification-3','lock-screen','error-404','error-500') ? 'active' : '' }}">
                                    2 Step Verification<span class="menu-arrow"></span>
                                </a>
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
                    </div>
                    @endif
                    <div class="tab-pane fade {{ Request::is('ui-alerts',
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
                                

                                ) ? ' show active ' : '' }}" id="ui-elements">
                        <ul>
                            <li class="menu-title"><span>UI INTERFACE</span></li>
                            <li class="submenu">
                                <a href="javascript:void(0);"  class="{{ Request::is('ui-alerts',
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
                                'ui-swiperjs') ? 'active subdrop' : '' }}">Base UI<span class="menu-arrow"></span>
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
                                <a href="javascript:void(0);"  class="{{ Request::is('ui-ribbon','ui-clipboard','ui-drag-drop',
                                'ui-rangeslider','ui-rating','ui-text-editor','ui-counter','ui-scrollbar','ui-stickynote','ui-timeline'
                                ) ? 'active subdrop' : '' }}"> Advanced UI <span class="menu-arrow"></span> </a>
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
                                ) ? 'active subdrop' : '' }}"> Forms <span class="menu-arrow"></span>
                                </a>
                                <ul>
                                    <li class="submenu submenu-two">
                                        <a href="javascript:void(0);" class="{{ Request::is('form-basic-inputs',
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
                                        ) ? 'active subdrop' : '' }}">Form Elements<span class="menu-arrow inside-submenu"></span></a>
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
                                        <a href="javascript:void(0);" class="{{ Request::is('form-horizontal','form-vertical','form-floating-labels') ? 'active subdrop' : '' }}">Layouts<span class="menu-arrow inside-submenu"></span></a>
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
                                <a href="javascript:void(0);" class="{{ Request::is('tables-basic','data-tables') ? 'active subdrop' : '' }}">Tables <span class="menu-arrow"></span></a>
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
                                <a href="javascript:void(0);" class="{{ Request::is('chart-apex','chart-c3','chart-js','chart-morris','chart-flot','chart-peity') ? 'active subdrop' : '' }}">Charts<span class="menu-arrow"></span> </a>
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
                                'icon-material','icon-pe7','icon-simpleline','icon-themify','icon-weather','icon-typicon','icon-flag') ? 'active subdrop' : '' }}">Icons<span class="menu-arrow"></span> </a>
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
                                <a href="javascript:void(0);" class="{{ Request::is('maps-vector','maps-leaflet') ? 'active' : '' }}">
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
                    </div>
                    <div class="tab-pane fade" id="extras">
                        <ul>
                            <li class="menu-title"><span>EXTRAS</span></li>
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
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- /Two Col Sidebar -->

<!-- Stacked Sidebar -->
<div class="stacked-sidebar" id="stacked-sidebar">
    <div class="sidebar sidebar-stacked" style="display: flex !important;">
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
                        <a href="{{url('chat')}}" class="btn btn-menubar position-relative">
                            <i class="ti ti-brand-hipchat"></i>
                            <span class="badge bg-info rounded-pill d-flex align-items-center justify-content-center header-badge">5</span>
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
                                <a href="#menu-dashboard" role="tab" class="nav-link {{ Request::is('index','employee-dashboard','deals-dashboard','leads-dashboard') ? ' show active ' : '' }}" title="Dashboard" data-bs-toggle="tab" data-bs-target="#menu-dashboard" aria-selected="true">
                                    <span><i class="ti ti-smart-home"></i></span>
                                    <p>Dashboard</p>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="#menu-application" role="tab" class="nav-link {{ Request::is('chat','voice-call','video-call','outgoing-call','incoming-call','call-history',
                            'calendar','email','todo','notes','social-feed','file-manager','kanban-view','invoices') ? ' show active ' : '' }} " title="Apps" data-bs-toggle="tab" data-bs-target="#menu-application" aria-selected="false">
                                    <span><i class="ti ti-layout-grid-add"></i></span>
                                    <p>Applications</p>
                                </a>
                            </div>
                            @if ($isGlobalHcmAdmin)
                            <div class="col-6">
                                <a href="#menu-superadmin" role="tab" class="nav-link {{ Request::is('dashboard','companies','subscription','packages','packages-grid','domain','purchase-transaction','saas/packages','saas/subscriptions','saas/domains','saas/transactions') ? 'show active' : '' }}" title="Apps" data-bs-toggle="tab" data-bs-target="#menu-superadmin" aria-selected="false">
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
                                <a href="#menu-project" role="tab" class="nav-link {{ Request::is('activity','clients',
                    'employees','employee-details','departments','designations','policy','tickets','tickets-grid','ticket-details','holidays','leaves','leaves-employee','leave-settings','attendance-admin','attendance-employee',
                            'timesheets','schedule-timing','shift-master','overtime-master','overtime','overtime-employee','leaves','leaves-employee','leave-settings','performance-indicator','performance-review','performance-appraisal','goal-tracking','goal-type','training','trainers','training-type','promotion','resignation','termination') ? ' show active ' : '' }}" title="Projects" data-bs-toggle="tab" data-bs-target="#menu-project" aria-selected="false">
                                    <span><i class="ti ti-folder"></i></span>
                                    <p>Projects</p>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="#menu-crm" role="tab" class="nav-link {{ Request::is('contacts-grid','contacts','contact-details','companies-grid','companies-crm','company-details','deals-grid','deals-details','deals'
                            ,'leads-grid','leads-details','leads','pipeline','analytics','activity') ? 'show active' : '' }}" title="CRM" data-bs-toggle="tab" data-bs-target="#menu-crm" aria-selected="false">
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
                                <a href="#menu-administration" role="tab" class="nav-link {{ Request::is('assets','asset-categories','knowledgebase','knowledgebase/*','activity','users','roles-permissions',
                        'expenses-report','invoice-report','payment-report','project-report','task-report','user-report','employee-report','payslip-report','attendance-report','leave-report','daily-report',
                        'profile-settings','security-settings','notification-settings','connected-apps','business-settings','seo-settings','localization-settings','prefixes','preferences','performance-appraisal','language','authentication-settings','ai-settings',
                            'salary-settings','approval-settings','invoice-settings','leave-type','custom-fields','email-settings','email-template','sms-settings','sms-template','otp-settings','gdpr','maintenance-mode','payment-gateways','tax-rates','currencies','custom-css','custom-js','cronjob','storage-settings','ban-ip-address','backup','clear-cache') ? 'show active ' : '' }}" title="Administration" data-bs-toggle="tab" data-bs-target="#menu-administration" aria-selected="false">
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
                                <a href="#menu-pages" role="tab" class="nav-link {{ Request::is('starter','profile','gallery','search-result','timeline','pricing','coming-soon','under-maintenance','under-construction','api-keys','privacy-policy','terms-condition') ? '  active subdrop' : '' }}" title="Pages"
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
                        <div class="tab-pane fade {{ Request::is('dashboard','companies','subscription','packages','packages-grid','domain','purchase-transaction','saas/packages','saas/subscriptions','saas/domains','saas/transactions') ? ' show active ' : '' }}" id="menu-superadmin">
                            <ul class="stack-submenu">
                        <li><a href="{{url('dashboard')}}" class="{{ Request::is('dashboard') ? 'active' : '' }}">Dashboard</a></li>
                        <li><a href="{{url('companies')}}"  class="{{ Request::is('companies') ? 'active' : '' }}">Companies</a></li>
                        <li><a href="{{url('saas/subscriptions')}}" class="{{ Request::is('saas/subscriptions') ? 'active' : '' }}">Subscriptions</a></li>
                        <li><a href="{{url('packages')}}" class="{{ Request::is('packages','packages-grid','saas/packages') ? 'active' : '' }}">Packages</a></li>
                        <li><a href="{{url('domain')}}" class="{{ Request::is('domain','saas/domains') ? 'active' : '' }}">Domain</a></li>
                        <li><a href="{{url('purchase-transaction')}}" class="{{ Request::is('purchase-transaction','saas/transactions') ? 'active' : '' }}">Purchase Transaction</a></li>

                            </ul>
                        </div>
@endif
                        <div class="tab-pane fade {{ Request::is('chat','voice-call','video-call','outgoing-call','incoming-call','call-history',
                            'calendar','email','todo','notes','social-feed','file-manager','kanban-view','invoices','invoice-details') ? ' show active ' : '' }}" id="menu-application">
                            <ul class="stack-submenu">
                                <li><a href="{{url('chat')}}" class="{{ Request::is('chat') ? 'active' : '' }}">Chat</a></li>
                                <li class="submenu submenu-two">
                                    <a href="{{url('call')}}" class="{{ Request::is('voice-call','video-call','outgoing-call','incoming-call','call-history') ? 'active' : '' }}">Calls<span
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
                                <li><a href="{{url('todo')}}" class="{{ Request::is('todo') ? 'active' : '' }}">To Do</a></li>
                                <li><a href="{{url('notes')}}" class="{{ Request::is('notes') ? 'active' : '' }}">Notes</a></li>
                                <li><a href="{{url('file-manager')}}" class="{{ Request::is('file-manager') ? 'active' : '' }}">File Manager</a></li>
                                <li><a href="{{url('kanban-view')}}" class="{{ Request::is('kanban-view') ? 'active' : '' }}">Kanban</a></li>
                                <li><a href="{{url('invoices')}}" class="{{ Request::is('invoices','invoice-details') ? 'active' : '' }}">Invoices</a></li>
                            </ul>
                        </div>
                        <div class="tab-pane fade {{ Request::is('layout-horizontal','layout-detached','layout-modern',
                    'layout-two-column','layout-hovered','layout-box','layout-horizontal-single','layout-horizontal-overlay','layout-horizontal-box',
                    'layout-horizontal-sidemenu','layout-vertical-transparent','layout-without-header','layout-rtl','layout-dark') ? 'show active' : '' }}" id="menu-layout">
                            <ul class="stack-submenu">
                                <li class="{{ Request::is('layout-horizontal') ? 'active' : '' }}">
                                    <a href="{{url('layout-horizontal')}}">
                                        <span>Horizontal</span>
                                    </a>
                                </li>
                                <li class="{{ Request::is('layout-detached') ? 'active' : '' }}">
                                    <a href="{{url('layout-detached')}}">
                                        <span>Detached</span>
                                    </a>
                                </li>
                                <li class="{{ Request::is('layout-modern') ? 'active' : '' }}">
                                    <a href="{{url('layout-modern')}}">
                                        <span>Modern</span>
                                    </a>
                                </li>
                                <li class="{{ Request::is('layout-two-column') ? 'active' : '' }}">
                                    <a href="{{url('layout-two-column')}}">
                                        <span>Two Column </span>
                                    </a>
                                </li>
                                <li class="{{ Request::is('layout-hovered') ? 'active' : '' }}">
                                    <a href="{{url('layout-hovered')}}">
                                        <span>Hovered</span>
                                    </a>
                                </li>
                                <li class="{{ Request::is('layout-box') ? 'active' : '' }}">
                                    <a href="{{url('layout-box')}}">
                                        <span>Boxed</span>
                                    </a>
                                </li>
                                <li class="{{ Request::is('layout-horizontal-single') ? 'active' : '' }}">
                                    <a href="{{url('layout-horizontal-single')}}">
                                        <span>Horizontal Single</span>
                                    </a>
                                </li>
                                <li class="{{ Request::is('layout-horizontal-overlay') ? 'active' : '' }}">
                                    <a href="{{url('layout-horizontal-overlay')}}">
                                        <span>Horizontal Overlay</span>
                                    </a>
                                </li>
                                <li class="{{ Request::is('layout-horizontal-box') ? 'active' : '' }}">
                                    <a href="{{url('layout-horizontal-box')}}">
                                        <span>Horizontal Box</span>
                                    </a>
                                </li>
                                <li class="{{ Request::is('layout-horizontal-sidemenu') ? 'active' : '' }}">
                                    <a href="{{url('layout-horizontal-sidemenu')}}">
                                        <span>Menu Aside</span>
                                    </a>
                                </li>
                                <li class="{{ Request::is('layout-vertical-transparent') ? 'active' : '' }}">
                                    <a href="{{url('layout-vertical-transparent')}}">
                                        <span>Transparent</span>
                                    </a>
                                </li>
                                <li class="{{ Request::is('layout-without-header') ? 'active' : '' }}">
                                    <a href="{{url('layout-without-header')}}">
                                        <span>Without Header</span>
                                    </a>
                                </li>
                                <li class="{{ Request::is('layout-rtl') ? 'active' : '' }}">
                                    <a href="{{url('layout-rtl')}}">
                                        <span>RTL</span>
                                    </a>
                                </li>
                                <li class="{{ Request::is('layout-dark') ? 'active' : '' }}"> 
                                    <a href="{{url('layout-dark')}}">
                                        <span>Dark</span>
                                    </a>
                                </li>
                            </ul>
                        </div>
                        <div class="tab-pane fade {{ Request::is('clients-grid','clients','projects-grid','projects','tasks','task-details','task-board','project-details') ? ' show active ' : '' }}" id="menu-project">
                            <ul class="stack-submenu">
                                <li class="{{ Request::is('clients-grid','clients') ? 'active' : '' }}"><a href="{{url('clients-grid')}}"><span>Clients</span></a></li>
                                <li class="submenu">
                                    <a href="javascript:void(0);" class="{{ Request::is('projects-grid','projects','tasks','task-details','task-board','project-details') ? 'active subdrop' : '' }}"><span>Projects</span>
                                        <span class="menu-arrow"></span>
                                    </a>
                                    <ul>
                                        <li><a href="{{url('projects-grid')}}" class="{{ Request::is('projects-grid','project-details','projects') ? 'active' : '' }}">Projects</a></li>
                                        <li><a href="{{url('tasks')}}" class="{{ Request::is('tasks','task-details') ? 'active' : '' }}">Tasks</a></li>
                                        <li><a href="{{url('task-board')}}" class="{{ Request::is('task-board') ? ' show active' : '' }}">Task Board</a></li>
                                    </ul>
                                </li>	
                            </ul>
                        </div>
                        <div class="tab-pane fade {{ Request::is('contacts-grid','contacts','contact-details','companies-grid','companies-crm','company-details','deals-grid','deals-details','deals'
                            ,'leads-grid','leads-details','leads','pipeline','analytics','activity') ? 'show active' : '' }}" id="menu-crm">
                            <ul class="stack-submenu">
                                <li class="{{ Request::is('contacts-grid','contacts','contact-details') ? 'active' : '' }}"><a href="{{url('contacts-grid')}}"><span>Contacts</span></a></li>
                                <li  class="{{ Request::is('companies-grid','companies-crm','company-details') ? 'active' : '' }}"><a href="{{url('companies-grid')}}"><span>Companies</span></a></li>
                                <li class="{{ Request::is('deals-grid','deals-details','deals') ? 'active' : '' }}"><a href="{{url('deals-grid')}}"><span>Deals</span></a></li>
                                <li class="{{ Request::is('leads-grid','leads-details','leads') ? 'active' : '' }}"><a href="{{url('leads-grid')}}"><span>Leads</span></a></li>
                                <li class="{{ Request::is('pipeline') ? 'active' : '' }}"><a href="{{url('pipeline')}}"><span>Pipeline</span></a></li>
                                <li class="{{ Request::is('analytics') ? 'active' : '' }}"><a href="{{url('analytics')}}"><span>Analytics</span></a></li>
                                <li class="{{ Request::is('activity') ? 'active' : '' }}"><a href="{{url('activity')}}"><span>Activities</span></a></li>

                            </ul>
                        </div>
                        <div class="tab-pane fade {{ Request::is('employees','employee-details','departments','designations','policy','tickets','tickets-grid','ticket-details','holidays',
                        'leaves','leaves-employee','leave-settings','attendance-admin','attendance-employee',
                            'timesheets','schedule-timing','shift-master','overtime-master','overtime','overtime-employee','performance-indicator','performance-review','performance-appraisal','goal-tracking','goal-type','training','trainers','training-type','promotion','resignation','termination') ? ' show active ' : '' }}" id="menu-hrm">
                            <ul class="stack-submenu">
                                <li class="submenu">
                                    <a href="javascript:void(0);" class="{{ Request::is('employees','employee-details','departments','designations','policy') ? 'active subdrop' : '' }}"><span>Employees</span>
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
                                <li class="submenu">
                                    <a href="javascript:void(0);" class="{{ Request::is('ticket-master','tickets-admin','tickets-employee','tickets-grid','ticket-details*') ? ' subdrop active ' : '' }}"><span>Tickets</span>
                                        <span class="menu-arrow"></span>
                                    </a>
                                    <ul>
                                        <li><a href="{{url('ticket-master')}}" class="{{ Request::is('ticket-master') ? 'active' : '' }}">Master Ticket</a></li>
                                        <li><a href="{{url('tickets-admin')}}" class="{{ Request::is('tickets-admin','tickets-grid') ? 'active' : '' }}">Ticket (Admin)</a></li>
                                        <li><a href="{{url('tickets-employee')}}" class="{{ Request::is('tickets-employee') ? 'active' : '' }}">Ticket (Employee)</a></li>
                                    </ul>
                                </li>
                                <li class="{{ Request::is('holidays') ? 'active' : '' }}"><a href="{{url('holidays')}}"><span>Holidays</span></a></li>
                                <li class="submenu">
                                    <a href="javascript:void(0);" class="{{ Request::is('leaves','leaves-employee','leave-settings','attendance-admin','attendance-employee',
                            'timesheets','schedule-timing','shift-master','overtime-master','overtime','overtime-employee') ? 'active subdrop' : '' }}"><span>Attendance</span>
                                        <span class="menu-arrow"></span>
                                    </a>
                                    <ul>
                                        <li class="submenu submenu-two">
                                            <a href="javascript:void(0);" class="{{ Request::is('leaves','leaves-employee','leave-settings') ? 'active subdrop' : '' }}">Leaves<span class="menu-arrow inside-submenu"></span></a>
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
                                        <li class="submenu submenu-two">
                                            <a href="javascript:void(0);" class="{{ Request::is('overtime-master','overtime','overtime-employee') ? 'active subdrop' : '' }}">Overtime<span class="menu-arrow inside-submenu"></span></a>
                                            <ul>
                                                <li><a href="{{url('overtime-master')}}" class="{{ Request::is('overtime-master') ? 'active' : '' }}">Master Overtime</a></li>
                                                <li><a href="{{url('overtime')}}" class="{{ Request::is('overtime') ? 'active' : '' }}">Overtime (Admin)</a></li>
                                                <li><a href="{{url('overtime-employee')}}" class="{{ Request::is('overtime-employee') ? 'active' : '' }}">Overtime (Employee)</a></li>
                                            </ul>
                                        </li>
                                </ul>
                                </li>
                                <li class="submenu">
                                    <a href="javascript:void(0);"  class="{{ Request::is('performance-indicator','performance-review','performance-appraisal','goal-tracking','goal-type') ? 'active subdrop' : '' }}"><span>Performance</span>
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
                                <li class="submenu">
                                    <a href="javascript:void(0);" class="{{ Request::is('training','trainers','training-type') ? 'active' : '' }}"><span>Training</span>
                                        <span class="menu-arrow"></span>
                                    </a>
                                    <ul>
                                        <li><a href="{{url('training')}}" class="{{ Request::is('training') ? 'active' : '' }}">Training List</a></li>
                                        <li><a href="{{url('trainers')}}" class="{{ Request::is('trainers') ? 'active' : '' }}">Trainers</a></li>
                                        <li><a href="{{url('training-type')}}" class="{{ Request::is('training-type') ? 'active' : '' }}">Training Type</a></li>
                                    </ul>
                                </li>
                                <li class="{{ Request::is('promotion') ? 'active' : '' }}"><a href="{{url('promotion')}}"><span>Promotion</span></a></li>
                                <li class="{{ Request::is('resignation') ? 'active' : '' }}"><a href="{{url('resignation')}}"><span>Resignation</span></a></li>
                                <li class="{{ Request::is('termination') ? 'active' : '' }}"><a href="{{url('termination')}}"><span>Termination</span></a></li>		
                            </ul>
                        </div>
                        <div class="tab-pane fade {{Request::is('estimates','invoices','payments','expenses','provident-fund','taxes','categories','budgets','budget-expenses','budget-revenues','employee-salary','payslip','payroll','payroll-overtime','payroll-deduction','payroll-thr') ? ' show active ' : '' }}" id="menu-finance">
                            <ul class="stack-submenu">
                                <li class="submenu">
                                    <a href="javascript:void(0);" class="{{ Request::is('estimates','invoice','payments','expenses','provident-fund','taxes') ? 'active subdrop' : '' }}"><span>Sales</span>
                                        <span class="menu-arrow"></span>
                                    </a>
                                    <ul>
                                        <li><a href="{{url('estimates')}}" class="{{ Request::is('estimates') ? 'active' : '' }}">Estimates</a></li>
                                <li><a href="{{url('invoice')}}" class="{{ Request::is('invoice') ? 'active' : '' }}">Invoices</a></li>
                                <li><a href="{{url('payments')}}" class="{{ Request::is('payments') ? 'active' : '' }}">Payments</a></li>
                                <li><a href="{{url('expenses')}}" class="{{ Request::is('expenses') ? 'active' : '' }}">Expenses</a></li>
                                <li><a href="{{url('provident-fund')}}" class="{{ Request::is('provident-fund') ? 'active' : '' }}">Provident Fund</a></li>
                                <li><a href="{{url('taxes')}}" class="{{ Request::is('taxes') ? 'active' : '' }}">Taxes</a></li>
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
                                <li class="submenu">
                                    <a href="javascript:void(0);" class="{{ Request::is('employee-salary','payslip','payroll-run','payroll-run-history','salary-component-master','payroll','payroll-overtime','payroll-deduction','payroll-thr','payroll-pkwt-compensation') ? 'active subdrop' : '' }}"><span>Payroll</span>
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
                                            <a href="javascript:void(0);" class="{{ Request::is('payslip','payroll-run-history','salary-component-master') ? 'subdrop' : '' }}">Payroll Records &amp; Setup<span class="menu-arrow inside-submenu"></span></a>
                                            <ul>
                                                <li><a href="{{url('payslip')}}" class="{{ Request::is('payslip') ? 'active' : '' }}">Payslips</a></li>
                                                <li><a href="{{url('payroll-run-history')}}" class="{{ Request::is('payroll-run-history') ? 'active' : '' }}">Payroll History</a></li>
                                                <li><a href="{{url('salary-component-master')}}" class="{{ Request::is('salary-component-master') ? 'active' : '' }}">Salary Components</a></li>
                                            </ul>
                                        </li>
                                    </ul>
                                </li>
                            </ul>
                        </div>
                        <div class="tab-pane fade {{ Request::is('assets','asset-categories','knowledgebase','knowledgebase/*','activity','users','roles-permissions',
                        'expenses-report','invoice-report','payment-report','project-report','task-report','user-report','employee-report','payslip-report','attendance-report','leave-report','daily-report',
                        'profile-settings','security-settings','notification-settings','connected-apps','business-settings','seo-settings','localization-settings','prefixes','preferences','performance-appraisal','language','authentication-settings','ai-settings',
                            'salary-settings','approval-settings','invoice-settings','leave-type','custom-fields','email-settings','email-template','sms-settings','sms-template','otp-settings','gdpr','maintenance-mode','payment-gateways','tax-rates','currencies','custom-css','custom-js','cronjob','storage-settings','ban-ip-address','backup','clear-cache') ? 'show active ' : '' }}" id="menu-administration">
                            <ul class="stack-submenu">
@if ($canSeeAssetManagementMenu)
                                <li class="submenu">
                                    <a href="javascript:void(0);" class="{{ Request::is('assets','asset-categories') ? 'active subdrop' : '' }}"><span>Assets</span>
                                        <span class="menu-arrow"></span>
                                    </a>
                                    <ul>
                                        <li><a href="{{url('assets')}}" class="{{ Request::is('assets') ? 'active' : '' }}">Assets</a></li>
                                        <li><a href="{{url('asset-categories')}}" class="{{ Request::is('asset-categories') ? 'active' : '' }}">Asset Categories</a></li>
                                    </ul>
                                </li>
@endif
                                <li class="submenu">
                                    <a href="javascript:void(0);" class="{{ Request::is('knowledgebase','knowledgebase/*','knowledgebase-details','activity') ? 'active subdrop' : '' }}"><span>Help & Supports</span>
                                        <span class="menu-arrow"></span>
                                    </a>
                                    <ul>
                                        <li><a href="{{url('knowledgebase')}}" class="{{ Request::is('knowledgebase','knowledgebase/*','knowledgebase-details') ? 'active' : '' }}">Knowledge Base</a></li>
                                        <li><a href="{{url('activity')}}" class="{{ Request::is('activity') ? 'active' : '' }}">Activities</a></li>
                                    </ul>
                                </li>
@if ($isHcmAdmin)
                                <li class="submenu">
                                    <a href="javascript:void(0);" class="{{ Request::is('users','roles-permissions') ? 'active subdrop' : '' }}"><span>User Management</span>
                                        <span class="menu-arrow"></span>
                                    </a>
                                    <ul>
                                        <li><a href="{{url('users')}}" class="{{ Request::is('users') ? 'active' : '' }}">Users</a></li>
                                        <li><a href="{{url('roles-permissions')}}" class="{{ Request::is('roles-permissions') ? 'active' : '' }}">Roles & Permissions</a></li>
                                    </ul>
                                </li>
@endif
@if ($isHcmAdmin)
                                <li class="submenu">
                                    <a href="javascript:void(0);" class="{{ Request::is('expenses-report','invoice-report','payment-report','project-report','task-report','user-report','employee-report','payslip-report','attendance-report','leave-report','daily-report') ? 'active subdrop' : '' }}"><span>Reports</span>
                                        <span class="menu-arrow"></span>
                                    </a>
                                    <ul>
                                        <li><a href="{{url('expenses-report')}}" class="{{ Request::is('expenses-report') ? 'active' : '' }}">Expense Report</a></li>
                                        <li><a href="{{url('invoice-report')}}" class="{{ Request::is('invoice-report') ? 'active' : '' }}">Invoice Report</a></li>
                                        <li><a href="{{url('payment-report')}}" class="{{ Request::is('payment-report') ? 'active' : '' }}">Payment Report</a></li>
                                        <li><a href="{{url('project-report')}}" class="{{ Request::is('project-report') ? 'active' : '' }}">Project Report</a></li>
                                        <li><a href="{{url('task-report')}}" class="{{ Request::is('task-report') ? 'active' : '' }}">Task Report</a></li>
                                        <li><a href="{{url('user-report')}}" class="{{ Request::is('user-report') ? 'active' : '' }}">User Report</a></li>
                                        <li><a href="{{url('employee-report')}}" class="{{ Request::is('employee-report') ? 'active' : '' }}">Employee Report</a></li>
                                        <li><a href="{{url('payslip-report')}}" class="{{ Request::is('payslip-report') ? 'active' : '' }}">Payslip Report</a></li>
                                        <li><a href="{{url('attendance-report')}}" class="{{ Request::is('attendance-report') ? 'active' : '' }}">Attendance Report</a></li>
                                        <li><a href="{{url('leave-report')}}" class="{{ Request::is('leave-report') ? 'active' : '' }}">Leave Report</a></li>
                                        <li><a href="{{url('daily-report')}}" class="{{ Request::is('daily-report') ? 'active' : '' }}">Daily Report</a></li> </ul>
                                </li>
                                <li class="submenu">
                                    <a href="javascript:void(0);" class="{{ Request::is('profile-settings','security-settings','notification-settings','connected-apps') ? 'active subdrop' : '' }}">
                                        General Settings
                                        <span class="menu-arrow"></span>
                                    </a>
                                    <ul>
                                        <li><a href="{{url('profile-settings')}}" class="{{ Request::is('profile-settings') ? 'active' : '' }}">Profile</a></li>
                                        <li><a href="{{url('security-settings')}}" class="{{ Request::is('security-settings') ? 'active' : '' }}">Security</a></li>
                                        <li><a href="{{url('notification-settings')}}" class="{{ Request::is('notification-settings') ? 'active' : '' }}">Notifications</a></li>
                                        <li><a href="{{url('connected-apps')}}" class="{{ Request::is('connected-apps') ? 'active' : '' }}">Connected Apps</a></li>
                                    </ul>
                                </li>
                                <li class="submenu">
                                    <a href="javascript:void(0);" class="{{ Request::is('business-settings','seo-settings','localization-settings','prefixes','preferences','performance-appraisal','language','authentication-settings','ai-settings') ? 'active subdrop' : '' }}">
                                        Website Settings
                                        <span class="menu-arrow"></span>
                                    </a>
                                    <ul>
                                        <li><a href="{{url('business-settings')}}" class="{{ Request::is('business-settings') ? 'active' : '' }}">Business Settings</a></li>
                                        <li><a href="{{url('seo-settings')}}" class="{{ Request::is('seo-settings') ? 'active' : '' }}">SEO Settings</a></li>
                                        <li><a href="{{url('localization-settings')}}" class="{{ Request::is('localization-settings') ? 'active' : '' }}">Localization</a></li>
                                        <li><a href="{{url('prefixes')}}" class="{{ Request::is('prefixes') ? 'active' : '' }}">Prefixes</a></li>
                                        <li><a href="{{url('preferences')}}" class="{{ Request::is('preferences') ? 'active' : '' }}">Preferences</a></li>
                                        <li><a href="{{url('performance-appraisal')}}" class="{{ Request::is('performance-appraisal') ? 'active' : '' }}">Appearance</a></li>
                                        <li><a href="{{url('language')}}" class="{{ Request::is('language') ? 'active' : '' }}">Language</a></li>
                                        <li><a href="{{url('authentication-settings')}}" class="{{ Request::is('authentication-settings') ? 'active' : '' }}">Authentication</a></li>
                                        <li><a href="{{url('ai-settings')}}" class="{{ Request::is('ai-settings') ? 'active' : '' }}">AI Settings</a></li>
                                    </ul>
                                </li>
                                <li class="submenu">
                                    <a href="javascript:void(0);" class="{{ Request::is('salary-settings','approval-settings','invoice-settings','leave-type','custom-fields') ? 'active subdrop' : '' }}">App Settings<span class="menu-arrow"></span></a>
                                    <ul>
                                        <li><a href="{{url('salary-settings')}}" class="{{ Request::is('salary-settings') ? 'active' : '' }}">Salary Settings</a></li>
                                        <li><a href="{{url('approval-settings')}}" class="{{ Request::is('approval-settings') ? 'active' : '' }}">Approval Settings</a></li>
                                        <li><a href="{{url('invoice-settings')}}" class="{{ Request::is('invoice-settings') ? 'active' : '' }}">Invoice Settings</a></li>
                                        <li><a href="{{url('leave-type')}}" class="{{ Request::is('leave-type') ? 'active' : '' }}">Leave Type</a></li>
                                        <li><a href="{{url('custom-fields')}}" class="{{ Request::is('custom-fields') ? 'active' : '' }}">Custom Fields</a></li>                                    </ul>
                                </li>
                                @if ($isGlobalHcmAdmin)
                                <li class="submenu">
                                    <a href="javascript:void(0);"  class="{{ Request::is('email-settings','email-template','sms-settings','sms-template','otp-settings','gdpr','maintenance-mode') ? 'active subdrop' : '' }}">
                                        System Settings
                                        <span class="menu-arrow"></span>
                                    </a>
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
                                <li class="submenu">
                                    <a href="javascript:void(0);" class="{{ Request::is('payment-gateways','tax-rates','currencies') ? 'active subdrop' : '' }}">
                                        Financial Settings
                                        <span class="menu-arrow"></span>
                                    </a>
                                    <ul>
                                        <li><a href="{{url('payment-gateways')}}" class="{{ Request::is('payment-gateways') ? 'active' : '' }}">Payment Gateways</a></li>
                                        <li><a href="{{url('tax-rates')}}" class="{{ Request::is('tax-rates') ? 'active' : '' }}">Tax Rate</a></li>
                                        <li><a href="{{url('currencies')}}" class="{{ Request::is('currencies') ? 'active' : '' }}">Currencies</a></li>
                                    </ul>
                                </li>
                                <li class="submenu">
                                    <a href="javascript:void(0);"  class="{{ Request::is('custom-css','custom-js','cronjob','storage-settings','ban-ip-address','backup','clear-cache') ? 'active subdrop' : '' }}">Other Settings<span class="menu-arrow"></span></a>
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
                        </div>
                        <div class="tab-pane fade {{ Request::is('blogs','blog-categories','blog-comments','blog-tags','countries','states','cities','villages','testimonials','faq') ? '  active subdrop' : '' }}" id="menu-content">
                            <ul class="stack-submenu">
                                <li class="submenu">
                                    <a href="javascript:void(0);" class="{{ Request::is('blogs','blog-categories','blog-comments','blog-tags') ? 'active' : '' }}">Blogs<span class="menu-arrow"></span></a>
                                    <ul>
                                        <li class="{{ Request::is('blogs') ? 'active' : '' }}"><a href="{{url('blogs')}}">All Blogs</a></li>
                                        <li class="{{ Request::is('blog-categories') ? 'active' : '' }}"><a href="{{url('blog-categories')}}">Categories</a></li>
                                        <li class="{{ Request::is('blog-comments') ? 'active' : '' }}"><a href="{{url('blog-comments')}}">Comments</a></li>
                                        <li class="{{ Request::is('blog-tags') ? 'active' : '' }}"><a href="{{url('blog-tags')}}">Tags</a></li>
                                    </ul>
                                </li>
                                <li class="submenu">
                                    <a href="javascript:void(0);" class="{{ Request::is('countries','states','cities','villages') ? 'active' : '' }}">Locations<span class="menu-arrow"></span></a>
                                    <ul>
                                        <li><a href="{{url('countries')}}" class="{{ Request::is('countries') ? 'active' : '' }}">Countries</a></li>
                                        <li><a href="{{url('states')}}" class="{{ Request::is('states') ? 'active' : '' }}">States</a></li>
                                        <li><a href="{{url('cities')}}" class="{{ Request::is('cities') ? 'active' : '' }}">Cities</a></li>

                                    </ul>
                                </li>
                                <li><a href="{{url('testimonials')}}" class="{{ Request::is('testimonials') ? 'active' : '' }}">Testimonials</a></li>
                                <li><a href="{{url('faq')}}" class="{{ Request::is('faq') ? 'active' : '' }}">FAQ’S</a></li>

                            </ul>
                        </div>
                        <div class="tab-pane fade {{ Request::is('starter','profile','gallery','search-result','timeline','pricing','coming-soon','under-maintenance','under-construction','api-keys','privacy-policy','terms-condition') ? '  active subdrop' : '' }}" id="menu-pages">
                            <ul class="stack-submenu">
                                <li class="{{ Request::is('starter') ? 'active' : '' }}"><a href="{{url('starter')}}"><span>Starter</span></a></li>
                        <li class="{{ Request::is('profile') ? 'active' : '' }}"><a href="{{url('profile')}}"><span>Profile</span></a></li>
                        <li class="{{ Request::is('gallery') ? 'active' : '' }}"><a href="{{url('gallery')}}"><span>Gallery</span></a></li>
                        <li class="{{ Request::is('search-result') ? 'active' : '' }}"><a href="{{url('search-result')}}"><span>Search Results</span></a></li>
                        <li class="{{ Request::is('timeline') ? 'active' : '' }}"><a href="{{url('timeline')}}"><span>Timeline</span></a></li>
                        <li class="{{ Request::is('pricing') ? 'active' : '' }}"><a href="{{url('pricing')}}"><span>Pricing</span></a></li>
                        <li class="{{ Request::is('coming-soon') ? 'active' : '' }}"><a href="{{url('coming-soon')}}"><span>Coming Soon</span></a></li>
                        <li class="{{ Request::is('under-maintenance') ? 'active' : '' }}"><a href="{{url('under-maintenance')}}"><span>Under Maintenance</span></a></li>
                        <li class="{{ Request::is('under-construction') ? 'active' : '' }}"><a href="{{url('under-construction')}}"><span>Under Construction</span></a></li>
                        <li class="{{ Request::is('api-keys') ? 'active' : '' }}"><a href="{{url('api-keys')}}"><span>API Keys</span></a></li>
                        <li class="{{ Request::is('privacy-policy') ? 'active' : '' }}"><a href="{{url('privacy-policy')}}"><span>Privacy Policy</span></a></li>
                        <li class="{{ Request::is('terms-condition') ? 'active' : '' }}"><a href="{{url('terms-condition')}}"><span>Terms & Conditions</span></a></li>
                            </ul>
                        </div>
                        @if ($showTemplateCatalogMenus)
                        <div class="tab-pane fade {{ Request::is('login','login-2','login-3','register','register-2','register-3',
                    'forgot-password','forgot-password-2','forgot-password-3','reset-password','reset-password-2','reset-password-3','email-verification','email-verification-2','email-verification-3',
                   'two-step-verification','two-step-verification-2','two-step-verification-3','lock-screen','error-404','error-500' ) ? ' show active' : '' }} " id="menu-authentication">
                            <ul class="stack-submenu">
                                <li class="submenu">
                                    <a href="javascript:void(0);" class="{{ Request::is('login','login-2','login-3') ? 'active' : '' }}">Login<span class="menu-arrow"></span></a>
                                    <ul>
                                        <li><a href="{{url('login')}}" class="{{ Request::is('login') ? 'active' : '' }}">Cover</a></li>
                                        <li><a href="{{url('login-2')}}" class="{{ Request::is('login-2') ? 'active' : '' }}">Illustration</a></li>
                                        <li><a href="{{url('login-3')}}" class="{{ Request::is('login-3') ? 'active' : '' }}">Basic</a></li>                                    </ul>
                                </li>
                                <li class="submenu">
                                    <a href="javascript:void(0);" class="{{ Request::is('register','register-2','register-3') ? 'active' : '' }}">Register<span class="menu-arrow"></span></a>
                                    <ul>
                                        <li><a href="{{url('register')}}" class="{{ Request::is('register') ? 'active' : '' }}">Cover</a></li>
                                        <li><a href="{{url('register-2')}}" class="{{ Request::is('register-2') ? 'active' : '' }}">Illustration</a></li>
                                        <li><a href="{{url('register-3')}}" class="{{ Request::is('register-3') ? 'active' : '' }}">Basic</a></li>

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
                                        <li><a href="{{url('email-verification-3')}}" class="{{ Request::is('email-verification-3') ? 'active' : '' }}">Basic</a></li>
                                    </ul>
                                </li>
                                <li class="submenu">
                                    <a href="javascript:void(0);" class="{{ Request::is('two-step-verification','two-step-verification-2','two-step-verification-3','lock-screen','error-404','error-500') ? 'active' : '' }}">2 Step Verification<span class="menu-arrow"></span></a>
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
                        </div>
                        @endif
                        <div class="tab-pane fade {{ Request::is('ui-alerts',
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
                                'form-mask',
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
                                

                                ) ? ' show active ' : '' }}" id="menu-ui-elements">
                            <ul class="stack-submenu">
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
                                    'ui-swiperjs') ? 'active subdrop' : '' }}">Base UI<span class="menu-arrow"></span></a>
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
                                    ) ? 'active subdrop' : '' }}"> Advanced UI<span class="menu-arrow"></span></a>
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
                                    ) ? 'active subdrop' : '' }}">Forms<span class="menu-arrow"></span> </a>
                                    <ul>
                                        <li class="submenu submenu-two">
                                            <a href="javascript:void(0);" class="{{ Request::is('form-basic-inputs',
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
                                            ) ? 'active subdrop' : '' }}">Form Elements<span class="menu-arrow inside-submenu"></span></a>
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
                                            <a href="javascript:void(0);" class="{{ Request::is('form-horizontal','form-vertical','form-floating-labels') ? 'active subdrop' : '' }}">Layouts<span class="menu-arrow inside-submenu"></span></a>
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
                                    <a href="javascript:void(0);" class="{{ Request::is('tables-basic','data-tables') ? 'active subdrop' : '' }}">Tables<span class="menu-arrow"></span></a>
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
                                    <a href="javascript:void(0);" class="{{ Request::is('chart-apex','chart-c3','chart-js','chart-morris','chart-flot','chart-peity') ? 'active subdrop' : '' }}">Charts<span class="menu-arrow"></span> </a>
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
                                    'icon-material','icon-pe7','icon-simpleline','icon-themify','icon-weather','icon-typicon','icon-flag') ? 'active subdrop' : '' }}">Icons<span class="menu-arrow"></span> </a>
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
                                    <a href="javascript:void(0);" class="{{ Request::is('maps-vector','maps-leaflet') ? 'active' : '' }}">
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
                        </div>
                    </div>
                </div>
            </div>
            <div class="p-3">
                <a href="javascript:void(0);" class="d-flex align-items-center fs-12 mb-3">Documentation</a>
                <a href="javascript:void(0);" class="d-flex align-items-center fs-12">Change Log<span class="badge bg-pink badge-xs text-white fs-10 ms-2">v4.0.2</span></a>
            </div>
        </div>
    </div>
</div>
<!-- /Stacked Sidebar -->
@endif


