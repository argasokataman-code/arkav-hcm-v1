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
