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
            <div class="me-3">
                <a href="{{url('chat')}}" class="btn btn-menubar position-relative">
                    <i class="ti ti-brand-hipchat"></i>
                    <span class="badge bg-info rounded-pill d-flex align-items-center justify-content-center header-badge">5</span>
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
                        <li class="submenu">
                            <a href="javascript:void(0);" class="{{ Request::is('index','employee-dashboard') ? 'active subdrop' : '' }}">
                                <i class="ti ti-smart-home"></i>
                                <span>Dashboard</span>
                                <span class="badge badge-danger fs-10 fw-medium text-white p-1">Hot</span>
                                <span class="menu-arrow"></span>
                            </a>
                            <ul>
@if ($isHcmAdmin)
                                <li><a href="{{url('index')}}" class="{{ Request::is('index') ? 'active' : '' }}">Admin Dashboard</a></li>
@endif
                                <li><a href="{{url('employee-dashboard')}}" class="{{ Request::is('employee-dashboard') ? 'active' : '' }}">Employee Dashboard</a></li>
                            </ul>
                        </li>
                        <!-- <li class="submenu">
                            <a href="javascript:void(0);" class="{{ Request::is('chat','voice-call','video-call','outgoing-call','incoming-call','call-history',
                            'calendar','email','notes','social-feed','file-manager','invoices','invoice-details') ? 'active subdrop' : '' }}">
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
                                <li><a href="{{url('notes')}}"  class="{{ Request::is('notes') ? 'active' : '' }}">Notes</a></li>
                                <li><a href="{{url('social-feed')}}"  class="{{ Request::is('social-feed') ? 'active' : '' }}">Social Feed</a></li>
                                <li><a href="{{url('file-manager')}}"  class="{{ Request::is('file-manager') ? 'active' : '' }}">File Manager</a></li>
                                <li><a href="{{url('invoices')}}"  class="{{ Request::is('invoices','invoice-details') ? 'active' : '' }}">Invoices</a></li>
                            </ul>
                        </li> -->
@if ($isGlobalHcmAdmin)
                        <li class="submenu">
                            <a href="#" class="{{ Request::is('dashboard','activity','companies','subscription','packages','packages-grid','domain','purchase-transaction','saas/packages','saas/subscriptions','saas/domains','saas/transactions','saas/invoices','saas/payments','saas/reports','saas/reminders','saas/billing-overview','saas/billing-overview/*','saas/pricing*','platform-tax-compliance*','notification-observability','cronjob-schedule','payment-report') ? 'active subdrop' : '' }}">
                                <i class="ti ti-user-star"></i><span>Super Admin</span>
                                <span class="menu-arrow"></span>
                            </a>
                            <ul>
                                <li><a href="{{url('dashboard')}}"  class="{{ Request::is('dashboard') ? 'active' : '' }}">Dashboard</a></li>
@if ($isPrimarySuperAdmin)
                                <li><a href="{{url('activity')}}"  class="{{ Request::is('activity') ? 'active' : '' }}">Activities</a></li>
@endif
                                <li><a href="{{url('companies')}}"  class="{{ Request::is('companies') ? 'active' : '' }}">Companies</a></li>
                                <li><a href="{{url('saas/billing-overview')}}"  class="{{ Request::is('saas/billing-overview','saas/billing-overview/*') ? 'active' : '' }}">Trial & Billing</a></li>
                                <li><a href="{{url('saas/subscriptions')}}"  class="{{ Request::is('saas/subscriptions') ? 'active' : '' }}">Subscriptions</a></li>
                                <li><a href="{{url('packages')}}"  class="{{ Request::is('packages','packages-grid','saas/packages') ? 'active' : '' }}">Packages</a></li>
                                <li><a href="{{url('domain')}}"  class="{{ Request::is('domain','saas/domains') ? 'active' : '' }}">Domain</a></li>
                                <li><a href="{{url('purchase-transaction')}}"  class="{{ Request::is('purchase-transaction','saas/transactions') ? 'active' : '' }}">Purchase Transaction</a></li>
                                <li><a href="{{url('saas/invoices')}}"  class="{{ Request::is('saas/invoices') ? 'active' : '' }}">SaaS Invoices</a></li>
                                <li><a href="{{url('saas/payments')}}"  class="{{ Request::is('saas/payments') ? 'active' : '' }}">SaaS Payments</a></li>
                                <li><a href="{{url('saas/reports')}}"  class="{{ Request::is('saas/reports') ? 'active' : '' }}">SaaS Reports</a></li>
                                <li><a href="{{url('saas/reminders')}}"  class="{{ Request::is('saas/reminders') ? 'active' : '' }}">SaaS Reminders</a></li>
                                <li><a href="{{ route('saas.pricing') }}"  class="{{ Request::is('saas/pricing*') ? 'active' : '' }}">Pricing & Plans</a></li>
                                <li><a href="{{ route('platform-tax-compliance.policies') }}" class="{{ Request::is('platform-tax-compliance*') ? 'active' : '' }}">Platform Tax & Compliance</a></li>
                                @include('layout.partials.sidebar.sections.shared.notification-observability-link')
                                <li><a href="{{url('cronjob-schedule')}}"  class="{{ Request::is('cronjob-schedule') ? 'active' : '' }}">Cronjob Schedule</a></li>
                                <li><a href="{{url('payment-report')}}"  class="{{ Request::is('payment-report') ? 'active' : '' }}">Payment Report</a></li>
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
