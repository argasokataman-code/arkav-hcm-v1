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
                                    ) ? 'active subdrop' : '' }}">
                                        <i class="ti ti-hierarchy-3"></i>
                                        <span>Advanced UI</span>
                                        <span class="menu-arrow"></span>
                                    </a>
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
                                    <a href="javascript:void(0);"  class="{{ Request::is('form-basic-inputs',
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
                                    ) ? 'active subdrop' : '' }}">
                                        <i class="ti ti-input-search"></i>
                                        <span>Forms</span>
                                        <span class="menu-arrow"></span>
                                    </a>
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
                                            ) ? 'active subdrop' : '' }}">Form Elements <span class="menu-arrow inside-submenu"></span>
                                            </a>
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
                                            <a href="javascript:void(0);" class="{{ Request::is('form-horizontal','form-vertical','form-floating-labels') ? 'active subdrop' : '' }}">Layouts <span class="menu-arrow inside-submenu"></span>
                                            </a>
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
