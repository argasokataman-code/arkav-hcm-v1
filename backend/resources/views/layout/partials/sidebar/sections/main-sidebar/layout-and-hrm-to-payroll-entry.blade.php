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
                                <li><a href="{{url('tickets-employee')}}" class="{{ Request::is('tickets-employee') ? 'active' : '' }}">Ticket (Employee)</a></li>
                            </ul>
                        </li>
@endif
@if ($isHcmAdmin)
                        <li class="{{ Request::is('holidays') ? 'active' : '' }}">
                            <a href="{{url('holidays')}}">
                                <i class="ti ti-calendar-event"></i><span>Holidays</span>
                            </a>
                        </li>
@endif
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
                                        <li><a href="{{url('leaves-employee')}}" class="{{ Request::is('leaves-employee') ? 'active' : '' }}">Leave (Employee)</a></li>
@if ($isHcmAdmin)
                                        <li><a href="{{url('leave-settings')}}" class="{{ Request::is('leave-settings') ? 'active' : '' }}">Leave Settings</a></li>
@endif
                                    </ul>												
                                </li>
@if ($isHcmAdmin)
                                <li><a href="{{url('attendance-admin')}}" class="{{ Request::is('attendance-admin') ? 'active subdrop' : '' }}">Attendance (Admin)</a></li>
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
@if ($isHcmAdmin)
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
                            <a href="javascript:void(0);" class="{{ Request::is('employee-salary','payslip','payroll-run','payroll-run-history','payroll','payroll-overtime','payroll-deduction','payroll-thr','payroll-pkwt-compensation') ? 'active subdrop' : '' }}">
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
