                    <div class="chat-footer">
                        <form class="footer-form">
                            <div class="chat-footer-wrap">
                                <div class="form-item">
                                    <a href="#"  class="action-circle"><i class="ti ti-microphone"></i></a>
                                </div>
                                <div class="form-wrap">
                                    <input type="text" class="form-control" placeholder="Type Your Message">
                                </div>
                                <div class="form-item emoj-action-foot">
                                    <a href="#" class="action-circle"><i class="ti ti-mood-smile"></i></a>
                                    <div class="emoj-group-list-foot down-emoji-circle">
                                        <ul>
                                            <li><a href="javascript:void(0);" ><img src="{{ URL::asset('build/img/icons/emonji-02.svg') }}"  alt="Icon"></a></li>
                                            <li><a href="javascript:void(0);" ><img src="{{ URL::asset('build/img/icons/emonji-05.svg') }}"  alt="Icon"></a></li>
                                            <li><a href="javascript:void(0);" ><img src="{{ URL::asset('build/img/icons/emonji-06.svg') }}"  alt="Icon"></a></li>
                                            <li><a href="javascript:void(0);" ><img src="{{ URL::asset('build/img/icons/emonji-07.svg') }}"  alt="Icon"></a></li>
                                            <li><a href="javascript:void(0);" ><img src="{{ URL::asset('build/img/icons/emonji-08.svg') }}"  alt="Icon"></a></li>
                                            <li class="add-emoj"><a href="javascript:void(0);" ><i class="ti ti-plus"></i></a></li>
                                        </ul>
                                    </div>
                                </div>                            
                                <div class="form-item position-relative d-flex align-items-center justify-content-center ">
                                    <a href="#" class="action-circle file-action position-absolute">
                                        <i class="ti ti-folder"></i>
                                    </a>
                                    <input type="file" class="open-file position-relative" name="files" id="files">
                                </div>
                                <div class="form-item">
                                    <a href="#" data-bs-toggle="dropdown">
                                        <i class="ti ti-dots-vertical"></i>
                                    </a>
                                    <div class="dropdown-menu dropdown-menu-end p-3" >
                                        <a href="#" class="dropdown-item"><i class="ti ti-camera-selfie me-2"></i>Camera</a>
                                        <a href="#" class="dropdown-item"><i class="ti ti-photo-up me-2"></i>Gallery</a>
                                        <a href="#" class="dropdown-item" ><i class="ti ti-music me-2"></i>Audio</a>
                                        <a href="#" class="dropdown-item"><i class="ti ti-map-pin-share me-2"></i>Location</a>
                                        <a href="#" class="dropdown-item" ><i class="ti ti-user-check me-2"></i>Contact</a>
                                    </div>
                                </div>
                                <div class="form-btn">
                                    <button class="btn btn-primary" type="submit">
                                        <i class="ti ti-send"></i>
                                    </button>
                                </div>
                            </div>                        
                        </form>
                    </div>
                </div>
                <!-- /Chat -->
