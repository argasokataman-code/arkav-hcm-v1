                                        <li><a href="{{url('cities')}}" class="{{ Request::is('cities') ? 'active' : '' }}">Cities</a></li>

                                    </ul>
                                </li>
                                <li><a href="{{url('testimonials')}}" class="{{ Request::is('testimonials') ? 'active' : '' }}">Testimonials</a></li>
                                <li><a href="{{url('faq')}}" class="{{ Request::is('faq') ? 'active' : '' }}">FAQ’S</a></li>

                            </ul>
                        </div>
                        <div class="tab-pane fade {{ Request::is('starter','profile','gallery','search-result','timeline','coming-soon','under-maintenance','under-construction','api-keys','privacy-policy','terms-condition') ? '  active subdrop' : '' }}" id="menu-pages">
                            <ul class="stack-submenu">
                                <li class="{{ Request::is('starter') ? 'active' : '' }}"><a href="{{url('starter')}}"><span>Starter</span></a></li>
                        <li class="{{ Request::is('profile') ? 'active' : '' }}"><a href="{{url('profile')}}"><span>Profile</span></a></li>
                        <li class="{{ Request::is('gallery') ? 'active' : '' }}"><a href="{{url('gallery')}}"><span>Gallery</span></a></li>
                        <li class="{{ Request::is('search-result') ? 'active' : '' }}"><a href="{{url('search-result')}}"><span>Search Results</span></a></li>
                        <li class="{{ Request::is('timeline') ? 'active' : '' }}"><a href="{{url('timeline')}}"><span>Timeline</span></a></li>
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
