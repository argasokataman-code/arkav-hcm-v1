                <div class="chat chat-messages show" id="middle">
                    <div>
                        <div class="chat-header">
                            <div class="user-details">
                                <div class="d-xl-none">
                                    <a class="text-muted chat-close me-2" href="#">
                                        <i class="fas fa-arrow-left"></i>
                                    </a>
                                </div>
                                <div class="avatar avatar-lg online flex-shrink-0">
                                    <img src="{{ URL::asset('build/img/profiles/avatar-29.jpg') }}" class="rounded-circle" alt="image">
                                </div>
                                <div class="ms-2 overflow-hidden">
                                    <h6>Anthony Lewis</h6>
                                    <span class="last-seen">Online</span>
                                </div>
                            </div>
                            <div class="chat-options">
                                <ul>
                                    <li>
                                        <a href="javascript:void(0)" class="btn chat-search-btn" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Search">
                                            <i class="ti ti-search" ></i>
                                        </a>
                                    </li>
                                    <li>
                                        <a class="btn no-bg" href="#" data-bs-toggle="dropdown">
                                            <i class="ti ti-dots-vertical" ></i>
                                        </a>
                                        <ul class="dropdown-menu dropdown-menu-end p-3">
                                            <li><a href="#" class="dropdown-item"><i class="ti ti-volume-off me-2"></i>Mute Notification</a></li>
                                            <li><a href="#" class="dropdown-item"><i class="ti ti-clock-hour-4 me-2"></i>Disappearing Message</a></li>
                                            <li><a href="#" class="dropdown-item"><i class="ti ti-clear-all me-2"></i>Clear Message</a></li>
                                            <li><a href="#" class="dropdown-item"><i class="ti ti-trash me-2"></i>Delete Chat</a></li>
                                            <li><a href="#" class="dropdown-item"><i class="ti ti-ban me-2"></i>Block</a></li>
                                        </ul>
                                    </li>
                                </ul>
                            </div>
                            <!-- Chat Search -->
                            <div class="chat-search search-wrap contact-search">
                                <form>
                                    <div class="input-group">
                                        <input type="text" class="form-control" placeholder="Search Contacts">
                                        <span class="input-group-text"><i class="ti ti-search"></i></span>
                                    </div>
                                </form>
                            </div>
                            <!-- /Chat Search -->
                        </div>
