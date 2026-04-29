                                            <a href="{{url('form-wizard')}}"  class="{{ Request::is('form-wizard') ? 'active' : '' }}">Form Wizard</a>
                                        </li>
                                        <li>
                                            <a href="{{url('form-pickers')}}"  class="{{ Request::is('form-pickers') ? 'active' : '' }}">Form Pickers</a>
                                        </li>
                                        
                                    </ul>
                                </li>
                                <li class="submenu">
                                    <a href="javascript:void(0);" class="{{ Request::is('tables-basic','data-tables') ? 'active subdrop' : '' }}">
                                        <i class="ti ti-table-plus"></i>
                                        <span>Tables</span>
                                        <span class="menu-arrow"></span>
                                    </a>
                                    <ul>
                                        <li>
                                            <a href="{{url('tables-basic')}}"  class="{{ Request::is('tables-basic') ? 'active' : '' }}">Basic Tables </a>
                                        </li>
                                        <li>
                                            <a href="{{url('data-tables')}}"  class="{{ Request::is('data-tables') ? 'active' : '' }}">Data Table </a>
                                        </li>
                                    </ul>
                                </li>
                                <li class="submenu">
                                    <a href="javascript:void(0);" class="{{ Request::is('chart-apex','chart-c3','chart-js','chart-morris','chart-flot','chart-peity') ? 'active subdrop' : '' }}">
                                        <i class="ti ti-chart-line"></i>
                                        <span>Charts</span>
                                        <span class="menu-arrow"></span>
                                    </a>
                                    <ul>
                                        <li>
                                            <a href="{{url('chart-apex')}}"  class="{{ Request::is('chart-apex') ? 'active' : '' }}">Apex Charts</a>
                                        </li>
                                        <li>
                                            <a href="{{url('chart-c3')}}" class="{{ Request::is('chart-c3') ? 'active' : '' }}">Chart C3</a>
                                        </li>
                                        <li>
                                            <a href="{{url('chart-js')}}" class="{{ Request::is('chart-js') ? 'active' : '' }}">Chart Js</a>
                                        </li>
                                        <li>
                                            <a href="{{url('chart-morris')}}" class="{{ Request::is('chart-morris') ? 'active' : '' }}">Morris Charts</a>
                                        </li>
                                        <li>
                                            <a href="{{url('chart-flot')}}" class="{{ Request::is('chart-flot') ? 'active' : '' }}">Flot Charts</a>
                                        </li>
                                        <li>
                                            <a href="{{url('chart-peity')}}" class="{{ Request::is('chart-peity') ? 'active' : '' }}">Peity Charts</a>
                                        </li>
                                    </ul>
                                </li>
                                <li class="submenu">
                                    <a href="javascript:void(0);" class="{{ Request::is('icon-fontawesome','icon-tabler','icon-bootstrap',
                                    'icon-remix','icon-feather','icon-ionic','icon-material','icon-pe7','icon-simpleline','icon-themify','icon-ionic',
                                    'icon-material','icon-pe7','icon-simpleline','icon-themify','icon-weather','icon-typicon','icon-flag') ? 'active subdrop' : '' }}">
                                        <i class="ti ti-icons"></i>
                                        <span>Icons</span>
                                        <span class="menu-arrow"></span>
                                    </a>
                                    <ul>
                                        <li>
                                            <a href="{{url('icon-fontawesome')}}" class="{{ Request::is('icon-fontawesome') ? 'active' : '' }}">Fontawesome Icons</a>
                                        </li>
                                        <li>
                                            <a href="{{url('icon-tabler')}}" class="{{ Request::is('icon-tabler') ? 'active' : '' }}">Tabler Icons</a>
                                        </li>
                                        <li>
                                            <a href="{{url('icon-bootstrap')}}" class="{{ Request::is('icon-bootstrap') ? 'active' : '' }}">Bootstrap Icons</a>
                                        </li>
                                        <li>
                                            <a href="{{url('icon-remix')}}" class="{{ Request::is('icon-remix') ? 'active' : '' }}">Remix Icons</a>
                                        </li>
                                        <li>
                                            <a href="{{url('icon-feather')}}" class="{{ Request::is('icon-feather') ? 'active' : '' }}">Feather Icons</a>
                                        </li>
                                        <li>
                                            <a href="{{url('icon-ionic')}}" class="{{ Request::is('icon-ionic') ? 'active' : '' }}">Ionic Icons</a>
                                        </li>
                                        <li>
                                            <a href="{{url('icon-material')}}" class="{{ Request::is('icon-material') ? 'active' : '' }}">Material Icons</a>
                                        </li>
                                        <li>
                                            <a href="{{url('icon-pe7')}}" class="{{ Request::is('icon-pe7') ? 'active' : '' }}">Pe7 Icons</a>
                                        </li>
                                        <li>
                                            <a href="{{url('icon-simpleline')}}" class="{{ Request::is('icon-simpleline') ? 'active' : '' }}">Simpleline Icons</a>
                                        </li>
                                        <li>
                                            <a href="{{url('icon-themify')}}" class="{{ Request::is('icon-themify') ? 'active' : '' }}">Themify Icons</a>
                                        </li>
                                        <li>
                                            <a href="{{url('icon-weather')}}" class="{{ Request::is('icon-weather') ? 'active' : '' }}">Weather Icons</a>
                                        </li>
                                        <li>
                                            <a href="{{url('icon-typicon')}}" class="{{ Request::is('icon-typicon') ? 'active' : '' }}">Typicon Icons</a>
                                        </li>
                                        <li>
                                            <a href="{{url('icon-flag')}}" class="{{ Request::is('icon-flag') ? 'active' : '' }}">Flag Icons</a>
                                        </li>
                                    </ul>
                                </li>
                                <li class="submenu">
                                    <a href="javascript:void(0);"  class="{{ Request::is('maps-vector','maps-leaflet') ? 'active' : '' }}">
                                        <i class="ti ti-table-plus"></i>
                                        <span>Maps</span>
                                        <span class="menu-arrow"></span>
                                    </a>
                                    <ul>
                                        <li>
                                            <a href="{{url('maps-vector')}}" class="{{ Request::is('maps-vector') ? 'active' : '' }}">Vector</a>
                                        </li>
                                        <li>
                                            <a href="{{url('maps-leaflet')}}" class="{{ Request::is('maps-leaflet') ? 'active' : '' }}">Leaflet</a>
                                        </li>
                                    </ul>
                                </li>
                            </ul>
                        </li>
                        <li><a href="#">Documentation</a></li>
                        <li><a href="#">Change Log</a></li>
                        <li class="submenu">
                            <a href="javascript:void(0);"><span>Multi Level</span><span class="menu-arrow"></span></a>
                            <ul>
                                <li><a href="javascript:void(0);">Multilevel 1</a></li>
                                <li class="submenu submenu-two">
                                    <a href="javascript:void(0);">Multilevel 2<span class="menu-arrow inside-submenu"></span></a>
                                    <ul>
                                        <li><a href="javascript:void(0);">Multilevel 2.1</a></li>
                                        <li class="submenu submenu-two submenu-three">
                                            <a href="javascript:void(0);">Multilevel 2.2<span class="menu-arrow inside-submenu inside-submenu-two"></span></a>
                                            <ul>
                                                <li><a href="javascript:void(0);">Multilevel 2.2.1</a></li>
                                                <li><a href="javascript:void(0);">Multilevel 2.2.2</a></li>
                                            </ul>
                                        </li>
                                    </ul>
                                </li>
                                <li><a href="javascript:void(0);">Multilevel 3</a></li>
                            </ul>
                        </li>
                    </ul>
                </li>
            </ul>
            <div class="d-xl-flex align-items-center d-none">
                <a href="#" class="me-3 avatar avatar-sm">
                    <img src="{{URL::asset('build/img/profiles/avatar-07.jpg')}}" alt="profile" class="rounded-circle">
                </a>
                <a href="#" class="btn btn-icon btn-sm rounded-circle mode-toggle">
                    <i class="ti ti-sun"></i>
                </a>
            </div>
        </div>
    </div>
</div>
<!-- /Horizontal Menu -->
@endif
