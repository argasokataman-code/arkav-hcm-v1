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
                        <li><a href="{{ $isGlobalHcmAdmin ? route('super-admin.employees-monitor') : url('employee-dashboard') }}" class="{{ Request::is('employee-dashboard','super-admin/employees-monitor') ? 'active' : '' }}">{{ $isGlobalHcmAdmin ? 'Employee Monitor' : 'Employee Dashboard' }}</a></li>
                    </ul>
                </li>
@if ($isGlobalHcmAdmin)
                <li class="submenu">
                    <a href="#" class="{{ Request::is('dashboard','activity','companies','subscription','packages','packages-grid','domain','purchase-transaction','saas/packages','saas/subscriptions','saas/domains','saas/transactions','saas/invoices','saas/payments','saas/reports','saas/reminders','saas/billing-overview','saas/billing-overview/*','saas/renewal-monitoring','saas/pricing*','saas/platform-tax*','platform-tax-compliance*','notification-observability','cronjob-schedule','payment-report') ? 'active subdrop' : '' }}">
                        <i class="ti ti-user-star"></i><span>Super Admin</span>
                        <span class="menu-arrow"></span>
                    </a>
                    <ul>
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
                        <li><a href="{{ route('saas.platform-tax') }}" class="{{ Request::is('saas/platform-tax*') ? 'active' : '' }}">Tax Reporting (SPT Platform)</a></li>
                        @include('layout.partials.sidebar.sections.shared.notification-observability-link')
                        <li><a href="{{url('cronjob-schedule')}}" class="{{ Request::is('cronjob-schedule') ? 'active' : '' }}">Cronjob Schedule</a></li>
                        <li><a href="{{url('payment-report')}}" class="{{ Request::is('payment-report') ? 'active' : '' }}">Payment Report</a></li>
                    </ul>
                </li>
@endif
                <li class="submenu">
                        <a href="#" class="{{ Request::is('voice-call','video-call','outgoing-call','incoming-call','call-history',
                            'calendar','email','notes','social-feed','invoices','invoice-details') ? ' subdrop active ' : '' }}">
                        <i class="ti ti-layout-grid-add"></i><span>Applications</span>
                        <span class="menu-arrow"></span>
                    </a>
                    <ul>
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
                        <li><a href="{{url('notes')}}" class="{{ Request::is('notes') ? 'active' : '' }}">Notes</a></li>
                        <li><a href="{{url('social-feed')}}" class="{{ Request::is('social-feed') ? 'active' : '' }}">Social Feed</a></li>
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
                    <a href="#" class="{{ Request::is('clients-grid','clients',                   'contacts-grid','contacts','contact-details','companies-grid','companies-crm','company-details',                    'employees','employee-details','departments','designations','teams','teams/*','policy','tickets','tickets-grid','ticket-details','holidays','leaves','leaves-employee','leave-settings','attendance-admin','attendance-employee',
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
                            <a href="{{url('call')}}" class="{{ Request::is('contacts-grid','contacts','contact-details','companies-grid','companies-crm','company-details'
                            ) ? 'active subdrop' : '' }}">Crm<span class="menu-arrow"></span></a>
                            <ul>
                                <li><a href="{{url('contacts-grid')}}" class="{{ Request::is('contacts-grid','contacts','contact-details') ? 'active' : '' }}"><span>Contacts</span></a></li>
                                <li><a href="{{url('companies-grid')}}" class="{{ Request::is('companies-grid','companies-crm','company-details') ? 'active' : '' }}"><span>Companies</span></a></li>
                            </ul>
                        </li>
                        <li class="submenu">
                            <a href="javascript:void(0);" class="{{ Request::is('employees','employee-details','departments','designations','teams','teams/*','policy') ? 'active subdrop' : '' }}"><span>Employees</span>
                                <span class="menu-arrow"></span>
                            </a>
                            <ul>
                                <li><a href="{{url('employees')}}" class="{{ Request::is('employees') ? 'active' : '' }}">Employee Lists</a></li>
                                                                <li><a href="{{url('employee-details')}}" class="{{ Request::is('employee-details') ? 'active' : '' }}">Employee Details</a></li>
                                <li><a href="{{url('departments')}}" class="{{ Request::is('departments') ? 'active' : '' }}">Departments</a></li>
                                <li><a href="{{url('designations')}}" class="{{ Request::is('designations') ? 'active' : '' }}">Designations</a></li>
                                <li><a href="{{url('teams')}}" class="{{ Request::is('teams','teams/*') ? 'active' : '' }}">Teams</a></li>
                                <li><a href="{{url('policy')}}" class="{{ Request::is('policy') ? 'active' : '' }}">Policies</a></li>
                            </ul>
                        </li>
@if ($canSeeTicketsMenu)
                        <li class="submenu">
                            <a href="javascript:void(0);"  class="{{ Request::is('ticket-master','tickets-admin','tickets-employee','tickets-grid','ticket-details*') ? 'active subdrop' : '' }}"><span>Tickets</span>
                                <span class="menu-arrow"></span>
                            </a>
                            <ul>
                                <li><a href="{{url('ticket-master')}}" class="{{ Request::is('ticket-master') ? 'active' : '' }}">Master Ticket</a></li>
                                <li><a href="{{url('tickets-admin')}}" class="{{ Request::is('tickets-admin','tickets-grid') ? 'active' : '' }}">Ticket (Admin)</a></li>
@if (in_array(strtolower(trim((string) request()->attributes->get('activeCompanyRole', ''))), ['employee', 'member'], true))
                                <li><a href="{{url('tickets-employee')}}" class="{{ Request::is('tickets-employee') ? 'active' : '' }}">Ticket (Employee)</a></li>
@endif
                            </ul>
                        </li>
@endif
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
@if (in_array(strtolower(trim((string) request()->attributes->get('activeCompanyRole', ''))), ['employee', 'member'], true))
                                        <li><a href="{{url('leaves-employee')}}" class="{{ Request::is('leaves-employee') ? 'active' : '' }}">Leave (Employee)</a></li>
@endif
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
