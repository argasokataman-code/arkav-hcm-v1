                        <li><a href="{{url('purchase-transaction')}}" class="{{ Request::is('purchase-transaction','saas/transactions') ? 'active' : '' }}">Purchase Transaction</a></li>

                            </ul>
                        </div>
@endif
                        <div class="tab-pane fade {{ Request::is('voice-call','video-call','outgoing-call','incoming-call','call-history',
                            'calendar','email','notes','social-feed','invoices','invoice-details') ? ' show active ' : '' }}" id="menu-application">
                            <ul class="stack-submenu">
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
                                <li><a href="{{url('notes')}}" class="{{ Request::is('notes') ? 'active' : '' }}">Notes</a></li>
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
                        <div class="tab-pane fade {{ Request::is('clients-grid','clients') ? ' show active ' : '' }}" id="menu-project">
                            <ul class="stack-submenu">
                                <li class="{{ Request::is('clients-grid','clients') ? 'active' : '' }}"><a href="{{url('clients-grid')}}"><span>Clients</span></a></li>
                            </ul>
                        </div>
                        <div class="tab-pane fade {{ Request::is('contacts-grid','contacts','contact-details','companies-grid','companies-crm','company-details'
                            ) ? 'show active' : '' }}" id="menu-crm">
                            <ul class="stack-submenu">
                                <li class="{{ Request::is('contacts-grid','contacts','contact-details') ? 'active' : '' }}"><a href="{{url('contacts-grid')}}"><span>Contacts</span></a></li>
                                <li  class="{{ Request::is('companies-grid','companies-crm','company-details') ? 'active' : '' }}"><a href="{{url('companies-grid')}}"><span>Companies</span></a></li>

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
        @if ($isHcmAdmin)
                                        <li><a href="{{url('employees')}}" class="{{ Request::is('employees') ? 'active' : '' }}">Employee Lists</a></li>
        @endif
                                                                                <li><a href="{{url('employee-details')}}" class="{{ Request::is('employee-details') ? 'active' : '' }}">Employee Details</a></li>
        @if ($isHcmAdmin)
                                        <li><a href="{{url('departments')}}" class="{{ Request::is('departments') ? 'active' : '' }}">Departments</a></li>
                                        <li><a href="{{url('designations')}}" class="{{ Request::is('designations') ? 'active' : '' }}">Designations</a></li>
                                        <li><a href="{{url('policy')}}" class="{{ Request::is('policy') ? 'active' : '' }}">Policies</a></li>
        @endif
        
                                    </ul>
                                </li>
@if ($canSeeTicketsMenu)
                                <li class="submenu">
                                    <a href="javascript:void(0);" class="{{ Request::is('ticket-master','tickets-admin','tickets-employee','tickets-grid','ticket-details*') ? ' subdrop active ' : '' }}"><span>Tickets</span>
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
@if ($isHcmAdmin)
                                <li class="{{ Request::is('holidays') ? 'active' : '' }}"><a href="{{url('holidays')}}"><span>Holidays</span></a></li>
@endif
                                <li class="submenu">
                                    <a href="javascript:void(0);" class="{{ Request::is('leaves','leaves-employee','leave-settings','attendance-admin','attendance-employee',
                            'timesheets','schedule-timing','shift-master','overtime-master','overtime','overtime-employee') ? 'active subdrop' : '' }}"><span>Attendance</span>
                                        <span class="menu-arrow"></span>
                                    </a>
                                    <ul>
                                        <li class="submenu submenu-two">
                                            <a href="javascript:void(0);" class="{{ Request::is('leaves','leaves-employee','leave-settings') ? 'active subdrop' : '' }}">Leaves<span class="menu-arrow inside-submenu"></span></a>
                                            <ul>
@if ($isHcmAdmin)
                                                <li><a href="{{url('leaves')}}" class="{{ Request::is('leaves') ? 'active' : '' }}" >Leaves (Admin)</a></li>
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
                                        <li><a href="{{url('attendance-admin')}}" class="{{ Request::is('attendance-admin') ? 'active' : '' }}">Attendance (Admin)</a></li>
@endif
                                <li><a href="{{url('attendance-employee')}}" class="{{ Request::is('attendance-employee') ? 'active' : '' }}">Attendance (Employee)</a></li>
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
@endif
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
@if ($canViewTrainingMenu)
                                <li class="submenu">
                                    <a href="javascript:void(0);" class="{{ Request::is('training','trainers','training-type') ? 'active' : '' }}"><span>Training</span>
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
