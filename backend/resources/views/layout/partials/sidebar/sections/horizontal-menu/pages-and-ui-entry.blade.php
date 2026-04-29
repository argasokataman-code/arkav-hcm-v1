                                </li>
                                @endif
                            </ul>
                        </li>
@endif
                    </ul>
                </li>
                <li class="submenu">
                    <a href="#" class="{{ Request::is('starter','profile','gallery','search-result','timeline','coming-soon','under-maintenance','under-construction','api-keys','privacy-policy','terms-condition',
                    'pages','blogs','blog-categories','blog-comments','blog-tags','countries','states','cities','villages','testimonials','faq') ? 'active' : '' }}">
                        <i class="ti ti-page-break"></i><span>Pages</span>
                        <span class="menu-arrow"></span>
                    </a>
                    <ul>
                        <li><a href="{{url('starter')}}" class="{{ Request::is('starter') ? 'active' : '' }}"><span>Starter</span></a></li>
                        <li><a href="{{url('profile')}}" class="{{ Request::is('profile') ? 'active' : '' }}"><span>Profile</span></a></li>
                        <li><a href="{{url('gallery')}}" class="{{ Request::is('gallery') ? 'active' : '' }}"><span>Gallery</span></a></li>
                        <li><a href="{{url('search-result')}}" class="{{ Request::is('search-result') ? 'active' : '' }}"><span>Search Results</span></a></li>
                        <li><a href="{{url('timeline')}}" class="{{ Request::is('timeline') ? 'active' : '' }}"><span>Timeline</span></a></li>
                        <li><a href="{{url('coming-soon')}}" class="{{ Request::is('coming-soon') ? 'active' : '' }}"><span>Coming Soon</span></a></li>
                        <li><a href="{{url('under-maintenance')}}" class="{{ Request::is('under-maintenance') ? 'active' : '' }}"><span>Under Maintenance</span></a></li>
                        <li><a href="{{url('under-construction')}}" class="{{ Request::is('under-construction') ? 'active' : '' }}"><span>Under Construction</span></a></li>
                        <li><a href="{{url('api-keys')}}" class="{{ Request::is('api-keys') ? 'active' : '' }}"><span>API Keys</span></a></li>
                        <li><a href="{{url('privacy-policy')}}" class="{{ Request::is('privacy-policy') ? 'active' : '' }}"><span>Privacy Policy</span></a></li>
                        <li><a href="{{url('terms-condition')}}"  class="{{ Request::is('terms-condition') ? 'active' : '' }}"><span>Terms & Conditions</span></a></li>
                        <li class="submenu">
                            <a href="#" class="{{ Request::is('pages','blogs','blog-categories','blog-comments','blog-tags') ? 'active' : '' }}"><span>Content</span> <span class="menu-arrow"></span></a>
                            <ul>
                                @if ($isHcmAdmin)
                                <li class="{{ Request::is('pages') ? 'active' : '' }}"><a href="{{url('pages')}}">Pages</a></li>
                                @endif
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
                                        <li><a href="{{url('countries')}}" class="{{ Request::is('countries') ? 'active' : '' }}">Provinces</a></li>
                                        <li><a href="{{url('states')}}" class="{{ Request::is('states') ? 'active' : '' }}">Regencies</a></li>
                                        <li><a href="{{url('cities')}}" class="{{ Request::is('cities') ? 'active' : '' }}">Districts</a></li>
                                    </ul>
                                </li>
                                <li><a href="{{url('testimonials')}}" class="{{ Request::is('testimonials') ? 'active' : '' }}">Testimonials</a></li>
                                <li><a href="{{url('faq')}}" class="{{ Request::is('faq') ? 'active' : '' }}">FAQ’S</a></li>
                            </ul>
                        </li>
                        @if ($showTemplateCatalogMenus)
                        <li class="submenu">
                            <a href="#" class="{{ Request::is('login','login-2','login-3','register','register-2','register-3','forgot-password','forgot-password-2','forgot-password-3',
                            'reset-password','reset-password-2','reset-password-3','email-verification','email-verification-2','email-verification-3','two-step-verification','two-step-verification-2','two-step-verification-3',
                           'lock-screen','error-404','error-500' ) ? 'active' : '' }}">
                                <span>Authentication</span>
                                <span class="menu-arrow"></span>
                            </a>
                            <ul>
                                <li class="submenu">
                                    <a href="javascript:void(0);" class="{{ Request::is('login','login-2','login-3') ? 'active' : '' }}">Login<span class="menu-arrow"></span></a>
                                    <ul>
                                        <li><a href="{{url('login')}}" class="{{ Request::is('login') ? 'active' : '' }}">Cover</a></li>
                                        <li><a href="{{url('login-2')}}" class="{{ Request::is('login-2') ? 'active' : '' }}">Illustration</a></li>
                                        <li><a href="{{url('login-3')}}" class="{{ Request::is('login-3') ? 'active' : '' }}">Basic</a></li>
                                    </ul>
                                </li>
                                <li class="submenu">
                                    <a href="javascript:void(0);" class="{{ Request::is('register','register-2','register-3') ? 'active' : '' }}">Register<span class="menu-arrow"></span></a>
                                    <ul>
                                        <li><a href="{{url('register')}}" class="{{ Request::is('register') ? 'active' : '' }}">Cover</a></li>
                                        <li><a href="{{url('register-2')}}" class="{{ Request::is('register-2') ? 'active' : '' }}">Illustration</a></li>
                                        <li><a href="{{url('register-3')}}" class="{{ Request::is('register-3') ? 'active' : '' }}">Basic</a></li>
                                    </ul>
                                </li>
                                <li class="submenu"><a href="javascript:void(0);" class="{{ Request::is('forgot-password','forgot-password-2','forgot-password-3') ? 'active' : '' }}">Forgot Password<span class="menu-arrow"></span></a>
                                    <ul>
                                        <li><a href="{{url('forgot-password')}}" class="{{ Request::is('forgot-password') ? 'active' : '' }}">Cover</a></li>
                                        <li><a href="{{url('forgot-password-2')}}" class="{{ Request::is('forgot-password-2') ? 'active' : '' }}">Illustration</a></li>
                                        <li><a href="{{url('forgot-password-3')}}" class="{{ Request::is('forgot-password-3') ? 'active' : '' }}">Basic</a></li>
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
                                    <a href="javascript:void(0);" class="{{ Request::is('two-step-verification','two-step-verification-2','two-step-verification-3') ? 'active' : '' }}">2 Step Verification<span class="menu-arrow"></span></a>
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
                        </li>
                        @endif
                        <li class="submenu">
                            <a href="#" class="{{ Request::is('ui-alerts',
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
                            'ui-swiperjs',
                            'ui-ribbon','ui-clipboard','ui-drag-drop',
                            'ui-rangeslider','ui-rating','ui-text-editor','ui-counter','ui-scrollbar','ui-stickynote','ui-timeline','form-basic-inputs',
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
                            'form-pickers',
                            'form-basic-inputs',
                                    'form-checkbox-radios',
                                    'form-input-groups',
                                    'form-grid-gutters',
                                    'form-select',
                                    'form-mask',
                                    'form-fileupload',
                                    'form-validation',
                                    'form-select2',
                                    'form-wizard',
                                    'form-pickers',
                                    'tables-basic','data-tables',
                                    'chart-apex',
                                    'chart-c3',
                                    'chart-js',
                                    'chart-morris',
                                    'chart-flot',
                                    'chart-peity',
                                    'icon-fontawesome','icon-tabler','icon-bootstrap',
                            'icon-remix','icon-feather','icon-ionic','icon-material','icon-pe7','icon-simpleline','icon-themify','icon-ionic',
                            'icon-material','icon-pe7','icon-simpleline','icon-themify','icon-weather','icon-typicon','icon-flag','maps-vector','maps-leaflet') ? 'active subdrop' : '' }}">
                                <span>UI Interface</span>
                                <span class="menu-arrow"></span>
                            </a>
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
