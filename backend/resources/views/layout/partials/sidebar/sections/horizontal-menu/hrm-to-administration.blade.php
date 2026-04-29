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
                        <li><a href="{{url('promotion')}}" class="{{ Request::is('promotion') ? 'active' : '' }}"><span>Promotion</span></a></li>
                        <li><a href="{{url('resignation')}}" class="{{ Request::is('resignation') ? 'active' : '' }}"><span>Resignation</span></a></li>
                        <li><a href="{{url('termination')}}"  class="{{ Request::is('termination') ? 'active' : '' }}"><span>Termination</span></a></li>														
                    </ul>
                </li>
                <li class="submenu">
                    <a href="#" class="{{ Request::is('estimates','invoices','payments','expenses','provident-fund','taxes','categories','budgets','budget-expenses','budget-revenues','employee-salary','payslip','payroll','payroll-overtime','payroll-deduction','payroll-thr','payroll-pkwt-compensation',
                   'assets','asset-categories','knowledgebase','knowledgebase/*','users','roles-permissions','expenses-report','invoice-report','payment-report','user-report','employee-report','payslip-report','attendance-report','leave-report','daily-report',
                   'profile-settings','company-profile','security-settings','notification-settings','connected-apps','business-settings','seo-settings','localization-settings','prefixes','preferences','performance-appraisal','language','authentication-settings','ai-settings',
                    'salary-settings','approval-settings','invoice-settings','leave-type','custom-fields','email-settings','email-template','sms-settings','sms-template','otp-settings','gdpr','maintenance-mode','payment-gateways','currencies','custom-css','custom-js','cronjob','storage-settings','ban-ip-address','backup','clear-cache') ? 'active subdrop' : '' }}">
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
@if ($canSeePayrollMenu)
                        <li class="submenu">
                            <a href="javascript:void(0);" class="{{ Request::is('employee-salary','payslip','payroll-run','payroll-run-history','payroll','payroll-overtime','payroll-deduction','payroll-thr','payroll-pkwt-compensation') ? 'active subdrop' : '' }}"><span>Payroll</span>
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
                                    <a href="javascript:void(0);" class="{{ Request::is('payslip','payroll-run-history','payroll','payroll-overtime','payroll-deduction') ? 'subdrop' : '' }}">Payroll Records &amp; Setup<span class="menu-arrow inside-submenu"></span></a>
                                    <ul>
                                        <li><a href="{{url('payslip')}}" class="{{ Request::is('payslip') ? 'active' : '' }}">My Payslip</a></li>
                                        <li><a href="{{url('payroll-run-history')}}" class="{{ Request::is('payroll-run-history') ? 'active' : '' }}">Payroll Run History</a></li>
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
                            <a href="javascript:void(0);" class="{{ Request::is('knowledgebase','knowledgebase/*','knowledgebase-details') ? 'active subdrop' : '' }}"><span>Help & Supports</span>
                                <span class="menu-arrow"></span>
                            </a>
                            <ul>
                                <li><a href="{{url('knowledgebase')}}" class="{{ Request::is('knowledgebase','knowledgebase/*','knowledgebase-details') ? 'active' : '' }}">Knowledge Base</a></li>
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
                            <a href="javascript:void(0);" class="{{ Request::is('expenses-report','invoice-report','payment-report','user-report','employee-report','payslip-report','attendance-report','leave-report','daily-report') ? 'active subdrop' : '' }}"><span>Reports</span>
                                <span class="menu-arrow"></span>
                            </a>
                            <ul>
                                <li><a href="{{url('expenses-report')}}" class="{{ Request::is('expenses-report') ? 'active' : '' }}">Expense Report</a></li>
                                <li><a href="{{url('invoice-report')}}" class="{{ Request::is('invoice-report') ? 'active' : '' }}">Invoice Report</a></li>
                                <li><a href="{{url('payment-report')}}" class="{{ Request::is('payment-report') ? 'active' : '' }}">Payment Report</a></li>
                                <li><a href="{{url('user-report')}}" class="{{ Request::is('user-report') ? 'active' : '' }}">User Report</a></li>
                                <li><a href="{{url('employee-report')}}" class="{{ Request::is('employee-report') ? 'active' : '' }}">Employee Report</a></li>
                                <li><a href="{{url('payslip-report')}}" class="{{ Request::is('payslip-report') ? 'active' : '' }}">Payslip Report (All Employees)</a></li>
                                <li><a href="{{url('attendance-report')}}" class="{{ Request::is('attendance-report') ? 'active' : '' }}">Attendance Report</a></li>
                                <li><a href="{{url('leave-report')}}" class="{{ Request::is('leave-report') ? 'active' : '' }}">Leave Report</a></li>
                                <li><a href="{{url('daily-report')}}" class="{{ Request::is('daily-report') ? 'active' : '' }}">Daily Report</a></li>
                            </ul>
                        </li>
                        <li class="submenu">
                            <a href="javascript:void(0);" class="{{ Request::is('profile-settings','company-profile','security-settings','notification-settings','connected-apps','business-settings','seo-settings','localization-settings','prefixes','preferences','performance-appraisal','language','authentication-settings','ai-settings',
                            'salary-settings','approval-settings','invoice-settings','leave-type','custom-fields','email-settings','email-template','sms-settings','sms-template','otp-settings','gdpr','maintenance-mode','payment-gateways','currencies','custom-css','custom-js','cronjob','storage-settings','ban-ip-address','backup','clear-cache') ? 'active subdrop' : '' }}"><span>Settings</span>
                                <span class="menu-arrow"></span>
                            </a>
                            <ul>
                                <li class="submenu">
                                    <a href="javascript:void(0);" class="{{ Request::is('profile-settings','company-profile','security-settings','notification-settings','connected-apps') ? 'active subdrop' : '' }}">General Settings<span class="menu-arrow"></span></a>
                                    <ul>
                                        <li><a href="{{url('profile-settings')}}" class="{{ Request::is('profile-settings') ? 'active' : '' }}">Profile</a></li>
                                        <li><a href="{{url('company-profile')}}" class="{{ Request::is('company-profile') ? 'active' : '' }}">Company Profile</a></li>
                                        <li><a href="{{url('security-settings')}}" class="{{ Request::is('security-settings') ? 'active' : '' }}">Security</a></li>
                                        <li><a href="{{url('notification-settings')}}" class="{{ Request::is('notification-settings') ? 'active' : '' }}">Notifications</a></li>
                                        <li><a href="{{url('connected-apps')}}" class="{{ Request::is('connected-apps') ? 'active' : '' }}">Connected Apps</a></li>
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
                                @if ($isGlobalHcmAdmin)
                                                <li><a href="{{ route('saas.pricing') }}" class="{{ Request::is('saas/pricing*') ? 'active' : '' }}">Pricing & Plans</a></li>
                                                <li><a href="{{ route('platform-tax-compliance.policies') }}" class="{{ Request::is('platform-tax-compliance*') ? 'active' : '' }}">Platform Finance - Government Tax & Compliance</a></li>
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
