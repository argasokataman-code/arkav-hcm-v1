                                            <a href="{{url('ui-tooltips')}}" class="{{ Request::is('ui-tooltips') ? 'active' : '' }}">Tooltips</a>
                                        </li>
                                        <li>
                                            <a href="{{url('ui-typography')}}" class="{{ Request::is('ui-typography') ? 'active' : '' }}">Typography</a>
                                        </li>
                                        <li>
                                            <a href="{{url('ui-video')}}" class="{{ Request::is('ui-video') ? 'active' : '' }}">Video</a>
                                        </li>
                                        <li>
                                            <a href="{{url('ui-sortable')}}" class="{{ Request::is('ui-sortable') ? 'active' : '' }}">Sortable</a>
                                        </li>
                                        <li>
                                            <a href="{{url('ui-swiperjs')}}" class="{{ Request::is('ui-swiperjs') ? 'active' : '' }}">Swiperjs</a>
                                        </li>
                                    </ul>
                                </li>
                                <li class="submenu">
                                    <a href="javascript:void(0);" class="{{ Request::is('ui-ribbon','ui-clipboard','ui-drag-drop',
                                    'ui-rangeslider','ui-rating','ui-text-editor','ui-counter','ui-scrollbar','ui-stickynote','ui-timeline'
                                    ) ? 'active subdrop' : '' }}"> Advanced UI<span class="menu-arrow"></span></a>
                                    <ul>
                                        <li>
                                            <a href="{{url('ui-ribbon')}}" class="{{ Request::is('ui-ribbon') ? 'active' : '' }}">Ribbon</a>
                                        </li>
                                        <li>
                                            <a href="{{url('ui-clipboard')}}" class="{{ Request::is('ui-clipboard') ? 'active' : '' }}">Clipboard</a>
                                        </li>
                                        <li>
                                            <a href="{{url('ui-drag-drop')}}" class="{{ Request::is('ui-drag-drop') ? 'active' : '' }}">Drag & Drop</a>
                                        </li>
                                        <li>
                                            <a href="{{url('ui-rangeslider')}}" class="{{ Request::is('ui-rangeslider') ? 'active' : '' }}">Range Slider</a>
                                        </li>
                                        <li>
                                            <a href="{{url('ui-rating')}}" class="{{ Request::is('ui-rating') ? 'active' : '' }}">Rating</a>
                                        </li>
                                        <li>
                                            <a href="{{url('ui-text-editor')}}" class="{{ Request::is('ui-text-editor') ? 'active' : '' }}">Text Editor</a>
                                        </li>
                                        <li>
                                            <a href="{{url('ui-counter')}}" class="{{ Request::is('ui-counter') ? 'active' : '' }}">Counter</a>
                                        </li>
                                        <li>
                                            <a href="{{url('ui-scrollbar')}}" class="{{ Request::is('ui-scrollbar') ? 'active' : '' }}">Scrollbar</a>
                                        </li>
                                        <li>
                                            <a href="{{url('ui-stickynote')}}" class="{{ Request::is('ui-stickynote') ? 'active' : '' }}">Sticky Note</a>
                                        </li>
                                        <li>
                                            <a href="{{url('ui-timeline')}}" class="{{ Request::is('ui-timeline') ? 'active' : '' }}">Timeline</a>
                                        </li>
                                    </ul>
                                </li>
                                <li class="submenu">
                                    <a href="javascript:void(0);" class="{{ Request::is('form-basic-inputs',
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
                                    'form-pickers'
                                    ) ? 'active subdrop' : '' }}">Forms<span class="menu-arrow"></span> </a>
                                    <ul>
                                        <li class="submenu submenu-two">
                                            <a href="javascript:void(0);" class="{{ Request::is('form-basic-inputs',
                                            'form-checkbox-radios',
                                            'form-input-groups',
                                            'form-grid-gutters',
                                            'form-select',
                                            'form-mask',
                                            'form-fileupload',
                                            'form-validation',
                                            'form-select2',
                                            'form-wizard',
                                            'form-pickers'
                                            ) ? 'active subdrop' : '' }}">Form Elements<span class="menu-arrow inside-submenu"></span></a>
                                            <ul>
                                                <li>
                                                    <a href="{{url('form-basic-inputs')}}" class="{{ Request::is('form-basic-inputs') ? 'active' : '' }}">Basic Inputs</a>
                                                </li>
                                                <li>
                                                    <a href="{{url('form-checkbox-radios')}}" class="{{ Request::is('form-checkbox-radios') ? 'active' : '' }}">Checkbox & Radios</a>
                                                </li>
                                                <li>
                                                    <a href="{{url('form-input-groups')}}" class="{{ Request::is('form-input-groups') ? 'active' : '' }}">Input Groups</a>
                                                </li>
                                                <li>
                                                    <a href="{{url('form-grid-gutters')}}" class="{{ Request::is('form-grid-gutters') ? 'active' : '' }}">Grid & Gutters</a>
                                                </li>
                                                <li>
                                                    <a href="{{url('form-select')}}" class="{{ Request::is('form-select') ? 'active' : '' }}">Form Select</a>
                                                </li>
                                                <li>
                                                    <a href="{{url('form-mask')}}" class="{{ Request::is('form-mask') ? 'active' : '' }}">Input Masks</a>
                                                </li>
                                                <li>
                                                    <a href="{{url('form-fileupload')}}" class="{{ Request::is('form-fileupload') ? 'active' : '' }}">File Uploads</a>
                                                </li>
                                            </ul>
                                        </li>
                                        <li class="submenu submenu-two">
                                            <a href="javascript:void(0);" class="{{ Request::is('form-horizontal','form-vertical','form-floating-labels') ? 'active subdrop' : '' }}">Layouts<span class="menu-arrow inside-submenu"></span></a>
                                            <ul>
                                                <li>
                                                    <a href="{{url('form-horizontal')}}"  class="{{ Request::is('form-horizontal') ? 'active' : '' }}">Horizontal Form</a>
                                                </li>
                                                <li>
                                                    <a href="{{url('form-vertical')}}"  class="{{ Request::is('form-vertical') ? 'active' : '' }}">Vertical Form</a>
                                                </li>
                                                <li>
                                                    <a href="{{url('form-floating-labels')}}"  class="{{ Request::is('form-floating-labels') ? 'active' : '' }}">Floating Labels</a>
                                                </li>
                                            </ul>
                                        </li>
                                        <li>
                                            <a href="{{url('form-validation')}}"  class="{{ Request::is('form-validation') ? 'active' : '' }}">Form Validation</a>
                                        </li>
                                        <li>
                                            <a href="{{url('form-select2')}}"  class="{{ Request::is('form-select2') ? 'active' : '' }}">Select2</a>
                                        </li>
                                        <li>
                                            <a href="{{url('form-wizard')}}"  class="{{ Request::is('form-wizard') ? 'active' : '' }}">Form Wizard</a>
                                        </li>
                                        <li>
                                            <a href="{{url('form-pickers')}}"  class="{{ Request::is('form-pickers') ? 'active' : '' }}">Form Pickers</a>
                                        </li>
                                    </ul>
                                </li>
                                <li class="submenu">
                                    <a href="javascript:void(0);" class="{{ Request::is('tables-basic','data-tables') ? 'active subdrop' : '' }}">Tables<span class="menu-arrow"></span></a>
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
                                    <a href="javascript:void(0);" class="{{ Request::is('chart-apex','chart-c3','chart-js','chart-morris','chart-flot','chart-peity') ? 'active subdrop' : '' }}">Charts<span class="menu-arrow"></span> </a>
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
                                    'icon-material','icon-pe7','icon-simpleline','icon-themify','icon-weather','icon-typicon','icon-flag') ? 'active subdrop' : '' }}">Icons<span class="menu-arrow"></span> </a>
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
                                    <a href="javascript:void(0);" class="{{ Request::is('maps-vector','maps-leaflet') ? 'active' : '' }}">
                                        <i class="ti ti-table-plus"></i>
                                        <span>Maps</span>
                                        <span class="menu-arrow"></span>
