                                <li class="{{ Request::is('promotion') ? 'active' : '' }}"><a href="{{url('promotion')}}"><span>Promotion</span></a></li>
                                <li class="{{ Request::is('resignation') ? 'active' : '' }}"><a href="{{url('resignation')}}"><span>Resignation</span></a></li>
                                <li class="{{ Request::is('termination') ? 'active' : '' }}"><a href="{{url('termination')}}"><span>Termination</span></a></li>		
                            </ul>
                        </div>
                        <div class="tab-pane fade {{Request::is('estimates','invoices','payments','expenses','provident-fund','taxes','categories','budgets','budget-expenses','budget-revenues','employee-salary','payslip','payroll-overtime','payroll-thr','salary-component-master') ? ' show active ' : '' }}" id="menu-finance">
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
@if ($canSeePayrollMenu)
                                <li class="submenu">
                                    <a href="javascript:void(0);" class="{{ Request::is('employee-salary','payslip','payroll-run','payroll-run-history','payroll-overtime','payroll-thr','payroll-pkwt-compensation','salary-component-master') ? 'active subdrop' : '' }}"><span>Payroll</span>
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
                                                <li><a href="{{url('payslip')}}" class="{{ Request::is('payslip') ? 'active' : '' }}">Payslips</a></li>
                                                <li><a href="{{url('payroll-run-history')}}" class="{{ Request::is('payroll-run-history') ? 'active' : '' }}">Payroll History</a></li>
                                                <li><a href="{{url('payroll-overtime')}}" class="{{ Request::is('payroll-overtime') ? 'active' : '' }}">Overtime</a></li>
                                        <li><a href="{{url('salary-component-master')}}" class="{{ Request::is('salary-component-master') ? 'active' : '' }}">Salary Components</a></li>
                                            </ul>
                                        </li>
                                    </ul>
                                </li>
@endif
                            </ul>
                        </div>
                        <div class="tab-pane fade {{ Request::is('assets','asset-categories','knowledgebase','knowledgebase/*','users','roles-permissions',
                        'expenses-report','invoice-report','payment-report','user-report','employee-report','payslip-report','attendance-report','leave-report','daily-report',
                        'profile-settings','company-profile','security-settings','notification-settings','business-settings','seo-settings','localization-settings','prefixes','preferences','performance-appraisal','language','authentication-settings','ai-settings',
                            'approval-settings','leave-type','email-settings','email-template','sms-settings','sms-template','otp-settings','gdpr','maintenance-mode','payment-gateways','currencies','custom-css','custom-js','cronjob','storage-settings','ban-ip-address','backup','clear-cache') ? 'show active ' : '' }}" id="menu-administration">
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
                                    <a href="javascript:void(0);" class="{{ Request::is('knowledgebase','knowledgebase/*','knowledgebase-details') ? 'active subdrop' : '' }}"><span>Help & Supports</span>
                                        <span class="menu-arrow"></span>
                                    </a>
                                    <ul>
                                        <li><a href="{{url('knowledgebase')}}" class="{{ Request::is('knowledgebase','knowledgebase/*','knowledgebase-details') ? 'active' : '' }}">Knowledge Base</a></li>
                                    </ul>
                                </li>
@if ($isHcmAdmin && ! $hideTenantOperationalReports)
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
@if ($isHcmAdmin && ! $hideTenantOperationalReports)
                                <li class="submenu">
                                    <a href="javascript:void(0);" class="{{ Request::is('expenses-report','invoice-report','user-report','employee-report','payslip-report','attendance-report','leave-report','daily-report') ? 'active subdrop' : '' }}"><span>Reports</span>
                                        <span class="menu-arrow"></span>
                                    </a>
                                    <ul>
                                        <li><a href="{{url('user-report')}}" class="{{ Request::is('user-report') ? 'active' : '' }}">User Report</a></li>
                                        <li><a href="{{url('employee-report')}}" class="{{ Request::is('employee-report') ? 'active' : '' }}">Employee Report</a></li>
                                        <li><a href="{{url('payslip-report')}}" class="{{ Request::is('payslip-report') ? 'active' : '' }}">Payslip Report</a></li>
                                        <li><a href="{{url('attendance-report')}}" class="{{ Request::is('attendance-report') ? 'active' : '' }}">Attendance Report</a></li>
                                        <li><a href="{{url('leave-report')}}" class="{{ Request::is('leave-report') ? 'active' : '' }}">Leave Report</a></li>
                                        <li><a href="{{url('daily-report')}}" class="{{ Request::is('daily-report') ? 'active' : '' }}">Daily Report</a></li> </ul>
                                </li>
                                <li class="submenu">
                                    <a href="javascript:void(0);" class="{{ Request::is('profile-settings','company-profile','security-settings','notification-settings') ? 'active subdrop' : '' }}">
                                        General Settings
                                        <span class="menu-arrow"></span>
                                    </a>
                                    <ul>
                                        <li><a href="{{ strtolower(trim((string) request()->attributes->get('activeCompanyRole', ''))) === 'owner' ? url('company-profile') : url('profile-settings') }}" class="{{ Request::is('profile-settings','company-profile') ? 'active' : '' }}">{{ strtolower(trim((string) request()->attributes->get('activeCompanyRole', ''))) === 'owner' ? 'Company Profile' : 'Profile' }}</a></li>
                                        <li><a href="{{url('company-profile')}}" class="{{ Request::is('company-profile') ? 'active' : '' }}">Company Profile</a></li>
                                        @if ($isGlobalHcmAdmin)
                                        <li><a href="{{url('security-settings')}}" class="{{ Request::is('security-settings') ? 'active' : '' }}">Security</a></li>
                                        @endif
                                        <li><a href="{{url('notification-settings')}}" class="{{ Request::is('notification-settings') ? 'active' : '' }}">Notifications</a></li>
                                    </ul>
                                </li>
                                <li class="submenu">
                                    <a href="javascript:void(0);" class="{{ Request::is('business-settings','seo-settings','localization-settings','prefixes','preferences','performance-appraisal','language','authentication-settings','ai-settings') ? 'active subdrop' : '' }}">
                                        Website Settings
                                        <span class="menu-arrow"></span>
                                    </a>
                                    <ul>
                                        @if ($isGlobalHcmAdmin)
                                        <li><a href="{{url('business-settings')}}" class="{{ Request::is('business-settings') ? 'active' : '' }}">Business Settings</a></li>
                                        <li><a href="{{url('seo-settings')}}" class="{{ Request::is('seo-settings') ? 'active' : '' }}">SEO Settings</a></li>
                                        <li><a href="{{url('localization-settings')}}" class="{{ Request::is('localization-settings') ? 'active' : '' }}">Localization</a></li>
                                        <li><a href="{{url('language')}}" class="{{ Request::is('language') ? 'active' : '' }}">Language</a></li>
                                        <li><a href="{{url('authentication-settings')}}" class="{{ Request::is('authentication-settings') ? 'active' : '' }}">Authentication</a></li>
                                        <li><a href="{{url('ai-settings')}}" class="{{ Request::is('ai-settings') ? 'active' : '' }}">AI Settings</a></li>
                                        @endif
                                        @if (!$isGlobalHcmAdmin)
                                        <li><a href="{{url('preferences')}}" class="{{ Request::is('preferences') ? 'active' : '' }}">Preferences</a></li>
                                        <li><a href="{{url('performance-appraisal')}}" class="{{ Request::is('performance-appraisal') ? 'active' : '' }}">Appearance</a></li>
                                        @endif
                                    </ul>
                                </li>
                                <li class="submenu">
                                    <a href="javascript:void(0);" class="{{ Request::is('approval-settings','leave-type') ? 'active subdrop' : '' }}">App Settings<span class="menu-arrow"></span></a>
                                    <ul>
                                        <li><a href="{{url('approval-settings')}}" class="{{ Request::is('approval-settings') ? 'active' : '' }}">Approval Settings</a></li>
                                        @if ($isGlobalHcmAdmin)<li><a href="{{url('invoice-settings')}}" class="{{ Request::is('invoice-settings') ? 'active' : '' }}">Invoice Settings</a></li>@endif
                                        <li><a href="{{url('leave-type')}}" class="{{ Request::is('leave-type') ? 'active' : '' }}">Leave Type</a></li>
                                </li>
                                @if ($isGlobalHcmAdmin)
                                <li class="submenu">
                                    <a href="javascript:void(0);"  class="{{ Request::is('email-settings','email-template','sms-settings','sms-template','otp-settings','gdpr','maintenance-mode') ? 'active subdrop' : '' }}">
                                        System Settings
                                        <span class="menu-arrow"></span>
                                    </a>
                                    <ul>
                                        <li><a href="{{url('sms-settings')}}" class="{{ Request::is('sms-settings') ? 'active' : '' }}">SMS Settings</a></li>
                                        <li><a href="{{url('sms-template')}}" class="{{ Request::is('sms-template') ? 'active' : '' }}">SMS Templates</a></li>
                                        <li><a href="{{url('otp-settings')}}" class="{{ Request::is('otp-settings') ? 'active' : '' }}">OTP</a></li>
                                        <li><a href="{{url('gdpr')}}" class="{{ Request::is('gdpr') ? 'active' : '' }}">GDPR Cookies</a></li>
                                        <li><a href="{{url('maintenance-mode')}}" class="{{ Request::is('maintenance-mode') ? 'active' : '' }}">Maintenance Mode</a></li>

                                    </ul>
                                </li>
                                <li class="submenu">
                                    <a href="javascript:void(0);" class="{{ Request::is('payment-gateways','currencies') ? 'active subdrop' : '' }}">
                                        Financial Settings
                                        <span class="menu-arrow"></span>
                                    </a>
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
                                @endif
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
