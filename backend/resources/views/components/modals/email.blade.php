@if (Route::is(['email-settings']))
    <!-- Add php mailer -->
    <div class="modal fade" id="phpmailersettings" data-email-settings-modal="mailtrap">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">
                        PHP Mailer
                    </h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="alert d-none" data-email-settings-modal-feedback="mailtrap"></div>
                    <form action="javascript:void(0);" data-email-settings-form="mailtrap">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">From Email Address</label>
                                    <input class="form-control" type="email" data-email-settings-field="fromAddress" data-provider="mailtrap">
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Mailtrap API Token</label>
                                    <input class="form-control" type="password" data-email-settings-field="mailtrap.apiToken" data-provider="mailtrap" autocomplete="new-password">
                                    <div class="form-text" data-email-settings-mask="mailtrap.apiToken">Belum ada token tersimpan.</div>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">From Email Name</label>
                                    <input class="form-control" type="text" data-email-settings-field="fromName" data-provider="mailtrap">
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Mailtrap Account ID</label>
                                    <input class="form-control" type="number" min="1" data-email-settings-field="mailtrap.accountId" data-provider="mailtrap">
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="rounded border bg-light p-3 d-none" data-email-settings-test-result="mailtrap"></div>
                            </div>
                        </div>
                        <div class="d-flex align-items-center justify-content-end">
                            <button type="button" class="btn btn-outline-light border me-3" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-outline-primary me-3" data-email-settings-test-button="mailtrap">Test Connection</button>
                            <button type="submit" class="btn btn-primary" data-email-settings-submit="mailtrap">Save</button>
                        </div>
                    </form>
                </div>       
            </div>
        </div>
    </div>
    <!-- /Add php mailer -->

      <!-- Add SMTP -->
    <div class="modal fade" id="smtpsettings" data-email-settings-modal="smtp">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">
                        SMTP
                    </h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="alert d-none" data-email-settings-modal-feedback="smtp"></div>
                    <form action="javascript:void(0);" data-email-settings-form="smtp">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">From Email Address</label>
                                    <input class="form-control" type="email" data-email-settings-field="fromAddress" data-provider="smtp">
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Email Password</label>
                                    <input class="form-control" type="password" data-email-settings-field="smtp.password" data-provider="smtp" autocomplete="new-password">
                                    <div class="form-text" data-email-settings-mask="smtp.password">Belum ada password tersimpan.</div>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Email Host</label>
                                    <input class="form-control" type="text" data-email-settings-field="smtp.host" data-provider="smtp">
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Port</label>
                                    <input class="form-control" type="number" min="1" max="65535" data-email-settings-field="smtp.port" data-provider="smtp">
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Encryption</label>
                                    <select class="form-select" data-email-settings-field="smtp.encryption" data-provider="smtp">
                                        <option value="tls">TLS</option>
                                        <option value="ssl">SSL</option>
                                        <option value="none">None</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Username</label>
                                    <input class="form-control" type="text" data-email-settings-field="smtp.username" data-provider="smtp">
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">From Email Name</label>
                                    <input class="form-control" type="text" data-email-settings-field="fromName" data-provider="smtp">
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="rounded border bg-light p-3 d-none" data-email-settings-test-result="smtp"></div>
                            </div>
                        </div>
                        <div class="d-flex align-items-center justify-content-end">
                            <button type="button" class="btn btn-outline-light border me-3" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-outline-primary me-3" data-email-settings-test-button="smtp">Test Connection</button>
                            <button type="submit" class="btn btn-primary" data-email-settings-submit="smtp">Save</button>
                        </div>
                    </form>
                </div>       
            </div>
        </div>
    </div>
    <!-- /Add  SMTP -->
@endif

@if (Route::is(['call-history']))
    <!-- Edit Employee -->
    <div class="modal fade" id="call_history">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <div class="d-flex align-items-center">
                        <h4 class="modal-title">Caller Details</h4>
                    </div>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{url('call-history')}}">
                    <div class="modal-body">
                        <div class="card bg-light-500">
                            <div class="card-body">
                                <div class="text-center">
                                    <div class="avatar avatar-xxxl mb-3">
                                        <img src="{{ URL::asset('build/img/users/user-32.jpg') }}" alt="img" class="rounded-circle">
                                    </div>
                                </div>
                                <div class="d-flex align-items-center justify-content-center">
                                    <a href="{{url('video-call')}}" class="btn btn-gray call-item p-0 d-flex align-items-center justify-content-center me-3"><i class="ti ti-video fs-20"></i></a>
                                    <a href="{{url('chat')}}" class="btn btn-gray call-item p-0 d-flex align-items-center justify-content-center me-3"><i class="ti ti-message fs-20"></i></a>
                                    <a href="{{url('voice-call')}}" class="btn btn-gray call-item p-0 d-flex align-items-center justify-content-center"><i class="ti ti-phone fs-20"></i></a>
                                </div>
                            </div>
                        </div>
                        <div class="row g-4">
                            <div class="col-md-6">
                                <div>
                                    <p class="mb-1">Name</p>
                                    <h6 class="fw-medium">Anthony Lewis</h6>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div>
                                    <p class="mb-1">Total Calls</p>
                                    <h6 class="fw-medium">20</h6>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div>
                                    <p class="mb-1">Phone</p>
                                    <h6 class="fw-medium">(123) 4567 890</h6>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div>
                                    <p class="mb-1">Average Call Timing</p>
                                    <h6 class="fw-medium">00:30</h6>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div>
                                    <p class="mb-1">Email</p>
                                    <h6 class="fw-medium">anthony@example.com</h6>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div>
                                    <p class="mb-1">Average Waiting Time</p>
                                    <h6 class="fw-medium">00:05</h6>
                                </div>
                            </div>
                        </div>	
                    </div>	
                </form>
            </div>
        </div>
    </div>
    <!-- /Edit Employee -->

    <!-- Delete Modal -->
    <div class="modal fade" id="delete_modal">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body text-center">
                    <span class="avatar avatar-xl bg-transparent-danger text-danger mb-3">
                        <i class="ti ti-trash-x fs-36"></i>
                    </span>
                    <h4 class="mb-1">Confirm Delete</h4>
                    <p class="mb-3">You want to delete all the marked items, this cant be undone once you delete.</p>
                    <div class="d-flex justify-content-center">
                        <a href="javascript:void(0);" class="btn btn-light me-3" data-bs-dismiss="modal">Cancel</a>
                        <a href="{{url('call-history')}}" class="btn btn-danger">Yes, Delete</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- /Delete Modal -->
@endif

@if (Route::is(['email', 'email-reply']))
    @php($shouldAutoOpenCompose = $errors->has('to') || $errors->has('subject') || $errors->has('message') || $errors->has('compose'))
    <!-- Compose Mail -->
    <div id="compose-view" data-auto-open="{{ $shouldAutoOpenCompose ? '1' : '0' }}">
        <div class="bg-white border-0 rounded compose-view">
            <div class="compose-header d-flex align-items-center justify-content-between bg-dark p-3">
                <h5 class="text-white">Compose New Email</h5>
                <div class="d-flex align-items-center">
                    <a href="javascript:void(0);" class="d-inline-flex me-2 text-white fs-16"><i class="ti ti-minus"></i></a>
                    <a href="javascript:void(0);" class="d-inline-flex me-2 fs-16 text-white"><i class="ti ti-maximize"></i></a>
                    <button type="button" class="btn-close custom-btn-close bg-transparent fs-16 text-white position-static" id="compose-close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
            </div>
            <form action="{{ route('email', request()->filled('Label') ? ['Label' => request('Label')] : []) }}" method="POST">
                @csrf
                @if (request()->filled('Label'))
                    <input type="hidden" name="Label" value="{{ request('Label') }}">
                @endif
                <div class="p-3 position-relative pb-2 border-bottom">
                    <div class="tag-with-img d-flex align-items-center">
                        <label class="form-label me-2">To</label>
                        <input class="input-tags form-control border-0 h-100{{ $errors->has('to') ? ' is-invalid' : '' }}" id="inputBox" type="text" data-role="tagsinput" name="to" value="{{ old('to') }}" placeholder="recipient@example.com">
                    </div>
                    @if ($errors->has('to'))
                        <div class="invalid-feedback d-block mt-2">{{ $errors->first('to') }}</div>
                    @endif
                    <div class="d-flex align-items-center email-cc">
                        <a href="javascript:void(0);" class="d-inline-flex me-2">Cc</a>
                        <a href="javascript:void(0);" class="d-inline-flex">Bcc</a>
                    </div>
                </div>
                <div class="p-3 border-bottom">
                    <div class="mb-3">
                        <input type="text" class="form-control{{ $errors->has('subject') ? ' is-invalid' : '' }}" name="subject" placeholder="Subject" value="{{ old('subject') }}">
                        @if ($errors->has('subject'))
                            <div class="invalid-feedback">{{ $errors->first('subject') }}</div>
                        @endif
                    </div>
                    <div class="mb-0">
                        <textarea rows="7" class="form-control{{ $errors->has('message') ? ' is-invalid' : '' }}" name="message" placeholder="Compose Email">{{ old('message') }}</textarea>
                        @if ($errors->has('message'))
                            <div class="invalid-feedback">{{ $errors->first('message') }}</div>
                        @endif
                    </div>
                </div>
                <div class="p-3 d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center">
                        <a href="javascript:void(0);" class="btn btn-icon btn-sm rounded-circle"><i class="ti ti-paperclip"></i></a>
                        <a href="javascript:void(0);" class="btn btn-icon btn-sm rounded-circle"><i class="ti ti-photo"></i></a>
                        <a href="javascript:void(0);" class="btn btn-icon btn-sm rounded-circle"><i class="ti ti-link"></i></a>
                        <a href="javascript:void(0);" class="btn btn-icon btn-sm rounded-circle"><i class="ti ti-pencil"></i></a>
                        <a href="javascript:void(0);" class="btn btn-icon btn-sm rounded-circle"><i class="ti ti-mood-smile"></i></a>
                    </div>
                    <div class="d-flex align-items-center compose-footer">
                        <a href="javascript:void(0);" class="btn btn-icon btn-sm rounded-circle"><i class="ti ti-calendar-repeat"></i></a>
                        <a href="javascript:void(0);" class="btn btn-icon btn-sm rounded-circle"><i class="ti ti-trash"></i></a>
                        <button type="submit" class="btn btn-primary d-inline-flex align-items-center ms-2">Send <i class="ti ti-arrow-right ms-2"></i></button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <!-- /Compose Mail -->
@endif
