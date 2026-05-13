<div class="sidebar" id="sidebar">
    @php
        $mainSidebarActiveCompanyRole = strtolower(trim((string) request()->attributes->get('activeCompanyRole', '')));
        $mainSidebarProfileUrl = $mainSidebarActiveCompanyRole === 'owner' ? url('company-profile') : url('profile-settings');
        $mainSidebarProfileLabel = $mainSidebarActiveCompanyRole === 'owner' ? 'Company Profile' : 'Profile';
    @endphp
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
        <div class="input-group input-group-flat d-inline-flex mb-4" data-hcm-global-search>
            <span class="input-icon-addon">
                <i class="ti ti-search"></i>
            </span>
            <input type="text" class="form-control" placeholder="Search in HRMS" autocomplete="off" data-hcm-global-search-input>
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
            <div class="me-3 notification-item">
                <a href="javascript:void(0);" class="btn btn-menubar position-relative me-1">
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
@if ($isHcmAdmin)
                        <li class="{{ Request::is('index') ? 'active' : '' }}">
                            <a href="{{url('index')}}">
                                <i class="ti ti-smart-home"></i>
                                <span>Dashboard</span>
                                <span class="badge badge-danger fs-10 fw-medium text-white p-1">Hot</span>
                            </a>
                        </li>
@else
                        <li class="{{ Request::is('employee-dashboard','super-admin/employees-monitor') ? 'active' : '' }}">
                            <a href="{{ $isGlobalHcmAdmin && Route::has('super-admin.employees-monitor') ? route('super-admin.employees-monitor') : url('employee-dashboard') }}">
                                <i class="ti ti-smart-home"></i>
                                <span>{{ $isGlobalHcmAdmin ? 'Employee Monitor' : 'Dashboard' }}</span>
                                <span class="badge badge-danger fs-10 fw-medium text-white p-1">Hot</span>
                            </a>
                        </li>
@endif
                        <!-- <li class="submenu">
                            <a href="javascript:void(0);" class="{{ Request::is('voice-call','video-call','outgoing-call','incoming-call','call-history',
                            'calendar','email','notes','social-feed','invoices','invoice-details') ? 'active subdrop' : '' }}">
                                <i class="ti ti-layout-grid-add"></i><span>Applications</span>
                                <span class="menu-arrow"></span>
                            </a>
                            <ul>
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
                                <li><a href="{{url('notes')}}"  class="{{ Request::is('notes') ? 'active' : '' }}">Notes</a></li>
                                <li><a href="{{url('social-feed')}}"  class="{{ Request::is('social-feed') ? 'active' : '' }}">Social Feed</a></li>
                                <li><a href="{{url('invoices')}}"  class="{{ Request::is('invoices','invoice-details') ? 'active' : '' }}">Invoices</a></li>
                            </ul>
                        </li> -->
@if ($isGlobalHcmAdmin)
                        <li class="submenu">
                            <a href="#" class="{{ Request::is('dashboard','activity','companies','subscription','packages','packages-grid','domain','purchase-transaction','saas/packages','saas/subscriptions','saas/domains','saas/transactions','saas/invoices','saas/payments','saas/reports','saas/reminders','saas/billing-overview','saas/billing-overview/*','saas/pricing*','saas/platform-tax*','platform-tax-compliance*','notification-observability','cronjob','payment-report') ? 'active subdrop' : '' }}">
                                <i class="ti ti-user-star"></i><span>Super Admin</span>
                                <span class="menu-arrow"></span>
                            </a>
                            <ul>
                                <li><a href="{{url('dashboard')}}"  class="{{ Request::is('dashboard') ? 'active' : '' }}">Dashboard</a></li>
@if ($isPrimarySuperAdmin)
                                <li><a href="{{url('activity')}}"  class="{{ Request::is('activity') ? 'active' : '' }}">Activities</a></li>
@endif
                                <li><a href="{{url('companies')}}"  class="{{ Request::is('companies') ? 'active' : '' }}">Companies</a></li>
                                <li><a href="{{url('saas/billing-overview')}}"  class="{{ Request::is('saas/billing-overview','saas/billing-overview/*') ? 'active' : '' }}">Trial & Billing Dashboard</a></li>
                                <li><a href="{{url('saas/subscriptions')}}"  class="{{ Request::is('saas/subscriptions') ? 'active' : '' }}">Subscriptions</a></li>
                                <li><a href="{{ route('super-admin.package-compliance') }}" class="{{ Request::is('super-admin/package-compliance') ? 'active' : '' }}">Package Compliance</a></li>
                                <li><a href="{{url('packages')}}"  class="{{ Request::is('packages','packages-grid','saas/packages') ? 'active' : '' }}">Packages</a></li>
                                <li><a href="{{url('domain')}}"  class="{{ Request::is('domain','saas/domains') ? 'active' : '' }}">Domain</a></li>
                                <li><a href="{{url('purchase-transaction')}}"  class="{{ Request::is('purchase-transaction','saas/transactions') ? 'active' : '' }}">Purchase Transaction</a></li>
                                <li><a href="{{url('saas/invoices')}}"  class="{{ Request::is('saas/invoices') ? 'active' : '' }}">SaaS Invoices</a></li>
                                <li><a href="{{url('saas/payments')}}"  class="{{ Request::is('saas/payments') ? 'active' : '' }}">SaaS Payments</a></li>
                                <li><a href="{{url('saas/reports')}}"  class="{{ Request::is('saas/reports') ? 'active' : '' }}">SaaS Reports</a></li>
                                <li><a href="{{url('saas/reminders')}}"  class="{{ Request::is('saas/reminders') ? 'active' : '' }}">SaaS Reminders</a></li>
                                <li><a href="{{ route('saas.pricing') }}"  class="{{ Request::is('saas/pricing*') ? 'active' : '' }}">Pricing & Plans</a></li>
                                <li><a href="{{ route('platform-tax-compliance.policies') }}" class="{{ Request::is('platform-tax-compliance*') ? 'active' : '' }}">Platform Tax Compliance Settings</a></li>
                                <li><a href="{{ route('saas.platform-tax') }}" class="{{ Request::is('saas/platform-tax*') ? 'active' : '' }}">Tax Reporting (SPT Platform)</a></li>
                                @include('layout.partials.sidebar.sections.shared.notification-observability-link')
                                <li><a href="{{url('cronjob')}}"  class="{{ Request::is('cronjob') ? 'active' : '' }}">Cronjob</a></li>
                                <li><a href="{{url('payment-report')}}"  class="{{ Request::is('payment-report') ? 'active' : '' }}">Payment Report</a></li>
                            </ul>
                        </li>
                        <li class="submenu">
                            <a href="javascript:void(0);" class="{{ Request::is('approval-settings','invoice-settings','leave-type','appearance','business-settings','seo-settings','localization-settings','language','authentication-settings','ai-settings','email-settings') ? 'active subdrop' : '' }}">
                                <i class="ti ti-settings"></i><span>Settings</span>
                                <span class="menu-arrow"></span>
                            </a>
                            <ul>
                                <li class="submenu submenu-two">
                                    <a href="javascript:void(0);" class="{{ Request::is('business-settings') ? 'active subdrop' : '' }}">Website Settings<span class="menu-arrow inside-submenu"></span></a>
                                    <ul>
                                        <li><a href="{{url('business-settings')}}" class="{{ Request::is('business-settings') ? 'active' : '' }}">Business Settings</a></li>
                                    </ul>
                                </li>
                                <li class="submenu submenu-two">
                                    <a href="javascript:void(0);" class="{{ Request::is('approval-settings','invoice-settings','leave-type','appearance') ? 'active subdrop' : '' }}">App Settings<span class="menu-arrow inside-submenu"></span></a>
                                    <ul>
                                        <li><a href="{{url('approval-settings')}}" class="{{ Request::is('approval-settings') ? 'active' : '' }}">Approval Settings</a></li>
                                        <li><a href="{{url('invoice-settings')}}" class="{{ Request::is('invoice-settings') ? 'active' : '' }}">Invoice Settings</a></li>
                                        <li><a href="{{url('leave-type')}}" class="{{ Request::is('leave-type') ? 'active' : '' }}">Leave Type</a></li>
                                        <li><a href="{{url('appearance')}}" class="{{ Request::is('appearance') ? 'active' : '' }}">Appearance</a></li>
                                    </ul>
                                </li>
                                <li class="submenu submenu-two">
                                    <a href="javascript:void(0);" class="{{ Request::is('email-settings') ? 'active subdrop' : '' }}">System Settings<span class="menu-arrow inside-submenu"></span></a>
                                    <ul>
                                        @include('layout.partials.sidebar.sections.shared.system-settings-links')
                                    </ul>
                                </li>
                            </ul>
                        </li>
@elseif ($hasCompanyBillingAccess)
                        <li class="submenu">
                            <a href="#" class="{{ Request::is('company/invoices','upgrade') ? 'active subdrop' : '' }}">
                                <i class="ti ti-receipt-2"></i><span>Billing</span>
                                <span class="menu-arrow"></span>
                            </a>
                            <ul>
                                <li><a href="{{url('company/invoices')}}" class="{{ Request::is('company/invoices') ? 'active' : '' }}">My Invoices</a></li>
                                <li><a href="{{ route('upgrade') }}" class="{{ Request::is('upgrade') ? 'active' : '' }}">Upgrade Plan</a></li>
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
                <li class="menu-title"><span>HRM</span></li>
                <li>
                    <ul>
    @if ($activeCompanyRole === 'owner')
                            <li>
                                <a href="{{ url('company-overview') }}" class="{{ Request::is('company-overview') ? 'active' : '' }}">
                                    <i class="ti ti-building-community"></i><span>Company Profile</span>
                                </a>
                            </li>
    @endif
@if ($canSeeEmployeesMenu)
                        <li class="submenu">
                            <a href="javascript:void(0);" class="{{ Request::is('employees','employee-details','departments','designations','teams','teams/*','policy') ? 'active subdrop' : '' }}">
                                <i class="ti ti-users"></i><span>Employees</span>
                                <span class="menu-arrow"></span>
                            </a>
                            <ul>
@if ($isHcmAdmin)
                                <li><a href="{{url('employees')}}" class="{{ Request::is('employees') ? 'active' : '' }}">Employee Lists</a></li>
@endif
                                                                <li><a href="{{url('employee-details')}}" class="{{ Request::is('employee-details') ? 'active' : '' }}">Employee Details</a></li>
@if ($isHcmAdmin)
                                <li><a href="{{url('departments')}}" class="{{ Request::is('departments') ? 'active' : '' }}">Departments</a></li>
                                <li><a href="{{url('designations')}}" class="{{ Request::is('designations') ? 'active' : '' }}">Designations</a></li>
                                <li><a href="{{url('teams')}}" class="{{ Request::is('teams','teams/*') ? 'active' : '' }}">Teams</a></li>
                                <li><a href="{{url('policy')}}" class="{{ Request::is('policy') ? 'active' : '' }}">Policies</a></li>
@endif
                            </ul>
                        </li>
@endif
@if ($canSeeTicketsMenu)
                        <li class="submenu">
                            <a href="javascript:void(0);"  class="{{ Request::is('ticket-master','tickets-admin','tickets-employee','tickets-grid','ticket-details*') ? 'active' : '' }}">
                                <i class="ti ti-ticket"></i><span>Tickets</span>
                                <span class="menu-arrow"></span>
                            </a>
                            <ul>
@if ($isHcmAdmin)
                                <li><a href="{{url('ticket-master')}}" class="{{ Request::is('ticket-master') ? 'active' : '' }}">Master Ticket</a></li>
                                <li><a href="{{url('tickets-admin')}}" class="{{ Request::is('tickets-admin','tickets-grid') ? 'active' : '' }}">Ticket (Admin)</a></li>
@endif
@if (in_array(strtolower(trim((string) request()->attributes->get('activeCompanyRole', ''))), ['employee', 'member'], true))
                                <li><a href="{{url('tickets-employee')}}" class="{{ Request::is('tickets-employee') ? 'active' : '' }}">Ticket (Employee)</a></li>
@endif
                            </ul>
                        </li>
@endif
@if ($canSeeHolidaysMenu)
                        <li class="{{ Request::is('holidays') ? 'active' : '' }}">
                            <a href="{{url('holidays')}}">
                                <i class="ti ti-calendar-event"></i><span>Holidays</span>
                            </a>
                        </li>
@endif
@if ($canSeeAttendanceMenu)
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
@if ($isHcmAdmin)
                                        <li><a href="{{url('leaves')}}" class="{{ Request::is('leaves') ? 'active' : '' }}">Leaves (Admin)</a></li>
@endif
@if (in_array(strtolower(trim((string) request()->attributes->get('activeCompanyRole', ''))), ['employee', 'member'], true))
                                        <li><a href="{{url('leaves-employee')}}" class="{{ Request::is('leaves-employee') ? 'active' : '' }}">Leave (Employee)</a></li>
@endif
@if ($isHcmAdmin)
                                        <li><a href="{{url('leave-settings')}}" class="{{ Request::is('leave-settings') ? 'active' : '' }}">Leave Settings</a></li>
@endif
                                    </ul>												
                                </li>
@if ($isHcmAdmin)
                                <li><a href="{{url('attendance-admin')}}" class="{{ Request::is('attendance-admin') ? 'active subdrop' : '' }}">Attendance (Admin)</a></li>
@else
                                <li><a href="{{url('attendance-employee')}}" class="{{ Request::is('attendance-employee') ? 'active' : '' }}">Attendance (Employee)</a></li>
@endif
@if ($isHcmAdmin)
                                <li><a href="{{url('timesheets')}}" class="{{ Request::is('timesheets') ? 'active' : '' }}">Timesheets</a></li>
                                <li><a href="{{url('schedule-timing')}}" class="{{ Request::is('schedule-timing') ? 'active' : '' }}">Shift & Schedule</a></li>
                                <li><a href="{{url('shift-master')}}" class="{{ Request::is('shift-master') ? 'active' : '' }}">Master Shift</a></li>
@endif
                                <li class="submenu submenu-two">
                                    <a href="javascript:void(0);" class="{{ Request::is('overtime-master','overtime','overtime-employee') ? 'active subdrop' : '' }}">Overtime<span class="menu-arrow inside-submenu"></span></a>
                                    <ul>
@if ($isHcmAdmin)
                                        <li><a href="{{url('overtime-master')}}" class="{{ Request::is('overtime-master') ? 'active' : '' }}">Master Overtime</a></li>
                                        <li><a href="{{url('overtime')}}" class="{{ Request::is('overtime') ? 'active' : '' }}">Overtime (Admin)</a></li>
@else
                                        <li><a href="{{url('overtime-employee')}}" class="{{ Request::is('overtime-employee') ? 'active' : '' }}">Overtime (Employee)</a></li>
@endif
                                    </ul>
                                </li>
                            </ul>
                        </li>
@endif
@if ($canSeePerformanceMenu)
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
@if ($canViewTrainingMenu)
                        <li class="submenu">
                            <a href="javascript:void(0);" class="{{ Request::is('training','trainers','training-type') ? 'active subdrop' : '' }}">
                                <i class="ti ti-edit"></i><span>Training</span>
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
@if ($canViewDocumentCenterMenu)
                        <li class="{{ Request::is('document-center') ? 'active' : '' }}">
                            <a href="{{url('document-center')}}">
                                <i class="ti ti-files"></i><span>Document Center</span>
                            </a>
                        </li>
@endif
@if ($canSeeEmployeeLifecycleMenu)
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
@endif
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
@if ($canSeePayrollMenu)
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
@if ($canSeePayrollMenu)
                        <li class="submenu">
                            <a href="javascript:void(0);" class="{{ Request::is('employee-salary','payslip','payroll-run','payroll-run-history','payroll-overtime','payroll-thr','payroll-pkwt-compensation','salary-component-master') ? 'active subdrop' : '' }}">
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
                                        <li><a href="{{url('employee-salary')}}" class="{{ Request::is('employee-salary') ? 'active' : '' }}">Employee Salary</a></li>
                                    </ul>
                                </li>
                                <li class="submenu submenu-two">
                                    <a href="javascript:void(0);" class="{{ Request::is('payslip','payroll-run-history','payroll-overtime','salary-component-master') ? 'subdrop' : '' }}">Payroll Records &amp; Setup<span class="menu-arrow inside-submenu"></span></a>
                                    <ul>
                                        <li><a href="{{url('payslip')}}" class="{{ Request::is('payslip') ? 'active' : '' }}">My Payslip</a></li>
                                        <li><a href="{{url('payroll-run-history')}}" class="{{ Request::is('payroll-run-history') ? 'active' : '' }}">Payroll Run History</a></li>
                                        <li><a href="{{url('salary-component-master')}}" class="{{ Request::is('salary-component-master') ? 'active' : '' }}">Salary Components</a></li>
                                    </ul>
                                </li>
                            </ul>
                        </li>
@endif
                    </ul>
                </li>
@endif
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
                            <a href="javascript:void(0);" class="{{ Request::is('knowledgebase','knowledgebase/*','knowledgebase-details') ? 'active subdrop' : '' }}">
                                <i class="ti ti-headset"></i><span>Help & Supports</span>
                                <span class="menu-arrow"></span>
                            </a>
                            <ul>
                                <li><a href="{{url('knowledgebase')}}" class="{{ Request::is('knowledgebase','knowledgebase/*','knowledgebase-details') ? 'active' : '' }}">Knowledge Base</a></li>
                            </ul>
                        </li>
@if ($isHcmAdmin && ! $hideTenantOperationalReports)
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
@if ($isHcmAdmin && ! $hideTenantOperationalReports)
                        <li class="submenu">
                            <a href="javascript:void(0);"class="{{ Request::is('expenses-report','invoice-report','user-report','employee-report','payslip-report','attendance-report','leave-report','daily-report') ? 'active subdrop' : '' }}">
                                <i class="ti ti-user-star"></i><span>Reports</span>
                                <span class="menu-arrow"></span>
                            </a>
                            <ul>
                                <li><a href="{{url('user-report')}}" class="{{ Request::is('user-report') ? 'active' : '' }}">User Report</a></li>
                                <li><a href="{{url('employee-report')}}" class="{{ Request::is('employee-report') ? 'active' : '' }}">Employee Report</a></li>
                                <li><a href="{{url('payslip-report')}}" class="{{ Request::is('payslip-report') ? 'active' : '' }}">Payslip Report (All Employees)</a></li>
                                <li><a href="{{url('attendance-report')}}" class="{{ Request::is('attendance-report') ? 'active' : '' }}">Attendance Report</a></li>
                                <li><a href="{{url('leave-report')}}" class="{{ Request::is('leave-report') ? 'active' : '' }}">Leave Report</a></li>
                                <li><a href="{{url('daily-report')}}" class="{{ Request::is('daily-report') ? 'active' : '' }}">Daily Report</a></li>
                            </ul>
                        </li>
                        <li class="submenu">
                            <a href="javascript:void(0);" class="{{ Request::is('profile-settings','security-settings','notification-settings','tax-employees*','taxes','bpjs-governance*','employee-allowance-governance*','spt-masa-pph21*',
                            'business-settings','seo-settings','localization-settings','prefixes','preferences','performance-appraisal','language','authentication-settings','ai-settings',
                            'approval-settings','invoice-settings','leave-type','email-settings',
                            


                            ) ? 'active subdrop' : '' }}">
                                <i class="ti ti-settings"></i><span>Settings</span>
                                <span class="menu-arrow"></span>
                            </a>
                            <ul>
                                <li class="submenu submenu-two">
                                    <a href="javascript:void(0);" class="{{ Request::is('profile-settings','company-profile','security-settings','notification-settings','tax-employees*','taxes','bpjs-governance*','employee-allowance-governance*','spt-masa-pph21*') ? 'active subdrop' : '' }}">General Settings<span class="menu-arrow inside-submenu"></span></a>
                                    <ul>
                                        <li><a href="{{ $mainSidebarProfileUrl }}" class="{{ Request::is('profile-settings','company-profile') ? 'active' : '' }}">{{ $mainSidebarProfileLabel }}</a></li>
                                        @if ($mainSidebarActiveCompanyRole !== 'owner')
                                        <li><a href="{{url('company-profile')}}" class="{{ Request::is('company-profile') ? 'active' : '' }}">Company Profile</a></li>
                                        @endif
                                        <li><a href="{{url('security-settings')}}" class="{{ Request::is('security-settings') ? 'active' : '' }}">Security</a></li>
                                        <li><a href="{{url('notification-settings')}}" class="{{ Request::is('notification-settings') ? 'active' : '' }}">Notifications</a></li>
                                        @if (!$isGlobalHcmAdmin)
                                        <li><a href="{{url('tax-employees')}}" class="{{ Request::is('tax-employees*','taxes') ? 'active' : '' }}">PPh 21 Governance</a></li>
                                        <li><a href="{{ route('bpjs-governance.index') }}" class="{{ Request::is('bpjs-governance*') ? 'active' : '' }}">BPJS Governance</a></li>
                                        <li><a href="{{ route('employee-allowance-governance.index') }}" class="{{ Request::is('employee-allowance-governance*') ? 'active' : '' }}">Allowance Governance</a></li>
                                        <li><a href="{{ route('spt-masa-pph21.index') }}" class="{{ Request::is('spt-masa-pph21*') ? 'active' : '' }}">SPT Masa PPh 21</a></li>
                                        @endif
                                    </ul>
                                </li>
                                @if ($isGlobalHcmAdmin)
                                <li class="submenu submenu-two">
                                    <a href="javascript:void(0);" class="{{ Request::is('business-settings') ? 'active subdrop' : '' }}">Website Settings<span class="menu-arrow inside-submenu"></span></a>
                                    <ul>
                                        <li><a href="{{url('business-settings')}}" class="{{ Request::is('business-settings') ? 'active' : '' }}" >Business Settings</a></li>
                                    </ul>
                                </li>
                                @endif
                                <li class="submenu submenu-two">
                                    <a href="javascript:void(0);" class="{{ Request::is('approval-settings','invoice-settings','leave-type','appearance') ? 'active subdrop' : '' }}">App Settings<span class="menu-arrow inside-submenu"></span></a>
                                    <ul>
                                        <li><a href="{{url('approval-settings')}}" class="{{ Request::is('approval-settings') ? 'active' : '' }}">Approval Settings</a></li>
                                        @if ($isGlobalHcmAdmin)<li><a href="{{url('invoice-settings')}}" class="{{ Request::is('invoice-settings') ? 'active' : '' }}">Invoice Settings</a></li>@endif
                                        <li><a href="{{url('leave-type')}}" class="{{ Request::is('leave-type') ? 'active' : '' }}">Leave Type</a></li>
                                        <li><a href="{{url('appearance')}}" class="{{ Request::is('appearance') ? 'active' : '' }}">Appearance</a></li>
                                    </ul>
                                </li>
                                @if ($isGlobalHcmAdmin)
                                <li class="submenu submenu-two">
                                    <a href="javascript:void(0);" class="{{ Request::is('email-settings') ? 'active subdrop' : '' }}">System Settings<span class="menu-arrow inside-submenu"></span></a>
                                    <ul>
                                        @include('layout.partials.sidebar.sections.shared.system-settings-links')
                                    </ul>
                                </li>
                                @endif
                                @if ($isGlobalHcmAdmin)
                                @endif
                            </ul>
                        </li>
@endif
                    </ul>
                </li>
@if ($isGlobalHcmAdmin)
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
