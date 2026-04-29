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
                                'ui-rangeslider','ui-rating','ui-text-editor','ui-counter','ui-scrollbar','ui-stickynote','ui-timeline',
                                'form-basic-inputs',
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
                                'tables-basic',
                                'data-tables',
                                'chart-apex','chart-c3','chart-js','chart-morris','chart-flot','chart-peity',
                                'icon-fontawesome','icon-tabler','icon-bootstrap',
                                'icon-remix','icon-feather','icon-ionic','icon-material','icon-pe7','icon-simpleline','icon-themify','icon-ionic',
                                'icon-material','icon-pe7','icon-simpleline','icon-themify','icon-weather','icon-typicon','icon-flag'
                                

                                ) ? ' show active ' : '' }}" id="ui-elements">
                        <ul>
                            <li class="menu-title"><span>UI INTERFACE</span></li>
                            <li class="submenu">
                                <a href="javascript:void(0);"  class="{{ Request::is('ui-alerts',
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
                                'ui-swiperjs') ? 'active subdrop' : '' }}">Base UI<span class="menu-arrow"></span>
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
                                <a href="javascript:void(0);"  class="{{ Request::is('ui-ribbon','ui-clipboard','ui-drag-drop',
                                'ui-rangeslider','ui-rating','ui-text-editor','ui-counter','ui-scrollbar','ui-stickynote','ui-timeline'
                                ) ? 'active subdrop' : '' }}"> Advanced UI <span class="menu-arrow"></span> </a>
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
