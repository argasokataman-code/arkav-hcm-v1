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
                            <a href="javascript:void(0);"class="{{ Request::is('expenses-report','invoice-report','user-report','employee-report','payslip-report','attendance-report','leave-report','daily-report') ? 'active subdrop' : '' }}">
                                <i class="ti ti-user-star"></i><span>Reports</span>
                                <span class="menu-arrow"></span>
                            </a>
                            <ul>
                                <li><a href="{{url('expenses-report')}}" class="{{ Request::is('expenses-report') ? 'active' : '' }}">Expense Report</a></li>
                                <li><a href="{{url('invoice-report')}}" class="{{ Request::is('invoice-report') ? 'active' : '' }}">Invoice Report</a></li>
                                <li><a href="{{url('user-report')}}" class="{{ Request::is('user-report') ? 'active' : '' }}">User Report</a></li>
                                <li><a href="{{url('employee-report')}}" class="{{ Request::is('employee-report') ? 'active' : '' }}">Employee Report</a></li>
                                <li><a href="{{url('payslip-report')}}" class="{{ Request::is('payslip-report') ? 'active' : '' }}">Payslip Report (All Employees)</a></li>
                                <li><a href="{{url('attendance-report')}}" class="{{ Request::is('attendance-report') ? 'active' : '' }}">Attendance Report</a></li>
                                <li><a href="{{url('leave-report')}}" class="{{ Request::is('leave-report') ? 'active' : '' }}">Leave Report</a></li>
                                <li><a href="{{url('daily-report')}}" class="{{ Request::is('daily-report') ? 'active' : '' }}">Daily Report</a></li>
                            </ul>
                        </li>
@if ($isHcmAdmin && !$isGlobalHcmAdmin)
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
                            <a href="javascript:void(0);" class="{{ Request::is('profile-settings','security-settings','notification-settings',
                            'business-settings','seo-settings','localization-settings','prefixes','preferences','performance-appraisal','language','authentication-settings','ai-settings',
                            'approval-settings','leave-type','email-settings','email-template','sms-settings','sms-template','otp-settings','gdpr','maintenance-mode',
                            'payment-gateways','currencies','custom-css','custom-js','cronjob','storage-settings','ban-ip-address','backup','clear-cache'


                            ) ? 'active subdrop' : '' }}">
                                <i class="ti ti-settings"></i><span>Settings</span>
                                <span class="menu-arrow"></span>
                            </a>
                            <ul>
                                <li class="submenu submenu-two">
                                    <a href="javascript:void(0);" class="{{ Request::is('profile-settings','company-profile','security-settings','notification-settings') ? 'active subdrop' : '' }}">General Settings<span class="menu-arrow inside-submenu"></span></a>
                                    <ul>
                                        <li><a href="{{url('profile-settings')}}" class="{{ Request::is('profile-settings') ? 'active' : '' }}">Profile</a></li>
                                        <li><a href="{{url('company-profile')}}" class="{{ Request::is('company-profile') ? 'active' : '' }}">Company Profile</a></li>
                                        @if ($isGlobalHcmAdmin)
                                        <li><a href="{{url('security-settings')}}" class="{{ Request::is('security-settings') ? 'active' : '' }}">Security</a></li>
                                        @endif
                                        <li><a href="{{url('notification-settings')}}" class="{{ Request::is('notification-settings') ? 'active' : '' }}">Notifications</a></li>
                                    </ul>
                                </li>
                                <li class="submenu submenu-two">
                                    <a href="javascript:void(0);" class="{{ Request::is('business-settings','seo-settings','localization-settings','prefixes','preferences','performance-appraisal','language','authentication-settings','ai-settings') ? 'active subdrop' : '' }}">Website Settings<span class="menu-arrow inside-submenu"></span></a>
                                    <ul>
                                        @if ($isGlobalHcmAdmin)
                                        <li><a href="{{url('business-settings')}}" class="{{ Request::is('business-settings') ? 'active' : '' }}" >Business Settings</a></li>
                                        <li><a href="{{url('seo-settings')}}" class="{{ Request::is('seo-settings') ? 'active' : '' }}">SEO Settings</a></li>
                                        <li><a href="{{url('localization-settings')}}" class="{{ Request::is('localization-settings') ? 'active' : '' }}">Localization</a></li>
                                        <li><a href="{{url('language')}}" class="{{ Request::is('language') ? 'active' : '' }}">Language</a></li>
                                        <li><a href="{{url('authentication-settings')}}" class="{{ Request::is('authentication-settings') ? 'active' : '' }}">Authentication</a></li>
                                        <li><a href="{{url('ai-settings')}}" class="{{ Request::is('ai-settings') ? 'active' : '' }}">AI Settings</a></li>
                                        @endif
                                        @if ($isGlobalHcmAdmin)
                                        <li><a href="{{url('prefixes')}}" class="{{ Request::is('prefixes') ? 'active' : '' }}">Prefixes</a></li>
                                        @endif
                                        <li><a href="{{url('preferences')}}" class="{{ Request::is('preferences') ? 'active' : '' }}">Preferences</a></li>
                                        <li><a href="{{url('performance-appraisal')}}" class="{{ Request::is('performance-appraisal') ? 'active' : '' }}">Appearance</a></li>
                                    </ul>
                                </li>
                                <li class="submenu submenu-two">
                                    <a href="javascript:void(0);" class="{{ Request::is('approval-settings','leave-type') ? 'active subdrop' : '' }}">App Settings<span class="menu-arrow inside-submenu"></span></a>
                                    <ul>
                                        <li><a href="{{url('approval-settings')}}" class="{{ Request::is('approval-settings') ? 'active' : '' }}">Approval Settings</a></li>
                                        @if ($isGlobalHcmAdmin)<li><a href="{{url('invoice-settings')}}" class="{{ Request::is('invoice-settings') ? 'active' : '' }}">Invoice Settings</a></li>@endif
                                        <li><a href="{{url('leave-type')}}" class="{{ Request::is('leave-type') ? 'active' : '' }}">Leave Type</a></li>
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
                                @if ($isGlobalHcmAdmin)
                                <li><a href="{{ route('saas.pricing') }}" class="{{ Request::is('saas/pricing*') ? 'active' : '' }}">Pricing & Plans</a></li>
                                <li><a href="{{ route('platform-tax-compliance.policies') }}" class="{{ Request::is('platform-tax-compliance*') ? 'active' : '' }}">Platform Tax & Compliance</a></li>
                                @endif
                                <li class="submenu submenu-two">
                                    <a href="javascript:void(0);" class="{{ Request::is('payment-gateways','currencies') ? 'active subdrop' : '' }}" >Financial Settings<span class="menu-arrow inside-submenu"></span></a>
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
                                @endif
                            </ul>
                        </li>
@endif
                    </ul>
                </li>
@if ($isHcmAdmin && !$isGlobalHcmAdmin)
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
