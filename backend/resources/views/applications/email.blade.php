<?php $page = 'email'; ?>
@php($isGlobalAdmin = auth()->check() && method_exists(auth()->user(), 'isGlobalHcmAdmin') && auth()->user()->isGlobalHcmAdmin())
@php($shouldAutoOpenCompose = $errors->has('to') || $errors->has('subject') || $errors->has('message') || $errors->has('compose'))
@php($sentCount = isset($sentCount) ? (int) $sentCount : 0)
@php($inboxCount = isset($inboxCount) ? (int) $inboxCount : 0)
@php($totalCount = isset($totalCount) ? (int) $totalCount : $sentCount)
@php($sentItems = is_array($sentItems ?? null) ? $sentItems : [])
@php($inboxItems = is_array($inboxItems ?? null) ? $inboxItems : [])
@extends('layout.mainlayout')
@section('content')

    <!-- Page Wrapper -->
    <div class="page-wrapper">
        <div class="content p-0">
            <div class="d-md-flex">
                <div class="email-sidebar border-end border-bottom">
                    <div class="active slimscroll h-100">
                        <div class="slimscroll-active-sidebar">					
                            <div class="p-3">
                                <div class="shadow-md bg-white rounded p-2 mb-4">
                                    <div class="d-flex align-items-center">
                                        <a href="javascript:void(0);" class="avatar avatar-md flex-shrink-0 me-2">
                                            <img src="{{ URL::asset('build/img/profiles/avatar-02.jpg') }}" class="rounded-circle" alt="Img">
                                        </a>
                                        <div>
                                            <h6 class="mb-1"><a href="javascript:void(0);">Email Workspace</a></h6>
                                            <p>Runtime inbox account</p>
                                        </div>
                                    </div>
                                </div>
                                @if ($isGlobalAdmin)
                                    <a href="javascript:void(0);" class="btn btn-primary w-100" id="compose_mail"><i class="ti ti-edit me-2"></i>Compose</a>
                                @else
                                    <a href="javascript:void(0);" class="btn btn-secondary w-100 disabled" title="Compose email is available for global administrators only"><i class="ti ti-edit me-2"></i>Compose</a>
                                @endif
                                <div class="mt-4">
                                    <h5 class="mb-2">Emails</h5>
                                    <div class="d-block mb-4 pb-4 border-bottom email-tags">
                                        <a href="{{url('email')}}" class="d-flex align-items-center justify-content-between p-2 rounded active" data-email-folder="inbox">
                                            <span class="d-flex align-items-center fw-medium"><i class="ti ti-inbox text-gray me-2"></i>Inbox</span>
                                            <span class="badge badge-secondary rounded-pill badge-xs" data-email-count-inbox>{{ $inboxCount }}</span>
                                        </a>
                                        <a href="javascript:void(0);" class="d-flex align-items-center justify-content-between p-2 rounded" data-email-folder="starred">
                                            <span class="d-flex align-items-center fw-medium"><i class="ti ti-star text-gray me-2"></i>Starred</span>
                                            <span class="fw-semibold fs-12 badge text-gray rounded-pill">0</span>
                                        </a>
                                        <a href="javascript:void(0);" class="d-flex align-items-center justify-content-between p-2 rounded" data-email-folder="sent">
                                            <span class="d-flex align-items-center fw-medium"><i class="ti ti-rocket text-gray me-2"></i>Sent</span>
                                            <span class="badge text-gray rounded-pill" data-email-count-sent>{{ $sentCount }}</span>
                                        </a>
                                        <a href="javascript:void(0);" class="d-flex align-items-center justify-content-between p-2 rounded" data-email-folder="drafts">
                                            <span class="d-flex align-items-center fw-medium"><i class="ti ti-file text-gray me-2"></i>Drafts</span>
                                            <span class="badge text-gray rounded-pill">0</span>
                                        </a>
                                        <a href="javascript:void(0);" class="d-flex align-items-center justify-content-between p-2 rounded" data-email-folder="deleted">
                                            <span class="d-flex align-items-center fw-medium"><i class="ti ti-trash text-gray me-2"></i>Deleted</span>
                                            <span class="badge text-gray rounded-pill">0</span>
                                        </a>
                                        <a href="javascript:void(0);" class="d-flex align-items-center justify-content-between p-2 rounded" data-email-folder="spam">
                                            <span class="d-flex align-items-center fw-medium"><i class="ti ti-info-octagon text-gray me-2"></i>Spam</span>
                                            <span class="badge text-gray rounded-pill">0</span>
                                        </a>
                                        <div>
                                            <div class="more-menu">
                                                <a href="javascript:void(0);" class="d-flex align-items-center justify-content-between p-2 rounded">
                                                    <span class="d-flex align-items-center fw-medium"><i class="ti ti-location-up text-gray me-2"></i>Important</span>
                                                    <span class="badge text-gray rounded-pill">12</span>
                                                </a>
                                                <a href="javascript:void(0);" class="d-flex align-items-center justify-content-between p-2 rounded">
                                                    <span class="d-flex align-items-center fw-medium"><i class="ti ti-transition-top text-gray me-2"></i>All Emails</span>
                                                    <span class="badge text-gray rounded-pill">0</span>
                                                </a>
                                            </div>
                                            <div class="view-all mt-2">
                                                <a href="javascript:void(0);" class="viewall-button fw-medium"><span>Show More</span><i class="fa fa-chevron-down fs-10 ms-2"></i></a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="border-bottom mb-4 pb-4">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <h5>Labels</h5>
                                        <a href="javascript:void(0);"><i class="ti ti-square-rounded-plus-filled text-primary fs-16"></i></a>
                                    </div>
                                    <div>
                                        <a href="javascript:void(0);" class="fw-medium d-flex align-items-center text-dark py-1">
                                            <i class="ti ti-square-rounded text-success me-2"></i>
                                            Team Events
                                        </a>
                                        <a href="javascript:void(0);" class="fw-medium d-flex align-items-center text-dark py-1">
                                            <i class="ti ti-square-rounded text-warning me-2"></i>
                                            Work
                                        </a>
                                        <a href="javascript:void(0);" class="fw-medium d-flex align-items-center text-dark py-1">
                                            <i class="ti ti-square-rounded text-danger me-2"></i>
                                            External
                                        </a>	
                                        <a href="javascript:void(0);" class="fw-medium d-flex align-items-center text-dark py-1">
                                            <i class="ti ti-square-rounded text-skyblue me-2"></i>
                                            Projects
                                        </a>
                                        <div>
                                            <div class="more-menu-2">
                                                <a href="javascript:void(0);" class="fw-medium d-flex align-items-center text-dark py-1">
                                                    <i class="ti ti-square-rounded text-purple me-2"></i>
                                                    Applications
                                                </a>
                                                <a href="javascript:void(0);" class="fw-medium d-flex align-items-center text-dark py-1">
                                                    <i class="ti ti-square-rounded text-info me-2"></i>
                                                    Desgin
                                                </a>
                                            </div>
                                            <div class="view-all mt-2">
                                                <a href="javascript:void(0);" class="viewall-button-2 fw-medium"><span>Show More</span><i class="fa fa-chevron-down fs-10 ms-2"></i></a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="border-bottom mb-4 pb-4">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <h5>Folders</h5>
                                        <a href="javascript:void(0);"><i class="ti ti-square-rounded-plus-filled text-primary fs-16"></i></a>
                                    </div>
                                    <div>
                                        <a href="javascript:void(0);" class="fw-medium d-flex align-items-center text-dark py-1">
                                            <i class="ti ti-folder-filled text-danger me-2"></i>
                                            Projects
                                        </a>
                                        <a href="javascript:void(0);" class="fw-medium d-flex align-items-center text-dark py-1">
                                            <i class="ti ti-folder-filled text-warning me-2"></i>
                                            Personal
                                        </a>
                                        <a href="javascript:void(0);" class="fw-medium d-flex align-items-center text-dark py-1">
                                            <i class="ti ti-folder-filled text-success me-2"></i>
                                            Finance
                                        </a>	
                                        <div>
                                            <div class="more-menu-3">
                                                <a href="javascript:void(0);" class="fw-medium d-flex align-items-center text-dark py-1">
                                                    <i class="ti ti-folder-filled text-info me-2"></i>
                                                    Projects
                                                </a>
                                                <a href="javascript:void(0);" class="fw-medium d-flex align-items-center text-dark py-1">
                                                    <i class="ti ti-folder-filled text-primary me-2"></i>
                                                    Personal
                                                </a>
                                            </div>
                                            <div class="view-all mt-2">
                                                <a href="javascript:void(0);" class="viewall-button-3 fw-medium"><span>Show More</span><i class="fa fa-chevron-down fs-10 ms-2"></i></a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-white flex-fill border-end border-bottom mail-notifications">
                    <div class="active slimscroll h-100">
                        <div class="slimscroll-active-sidebar">	
                            <div class="p-3">
                                <div class="d-flex align-items-center justify-content-between flex-wrap row-gap-3">
                                    <div>
                                        <h5 class="mb-1">Inbox</h5>
                                        <div class="d-flex align-items-center">
                                            <span><span data-email-count-header>{{ $inboxCount }}</span> Emails</span>
                                            <i class="ti ti-point-filled text-primary mx-1"></i>
                                            <span>0 Unread</span>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center">
                                        <div class="position-relative input-icon me-3">
                                            <span class="input-icon-addon">
                                                <i class="ti ti-search"></i>
                                            </span>
                                            <input type="text" class="form-control" placeholder="Search Email">
                                        </div>
                                        <div class="d-flex align-items-center">
                                            <a href="javascript:void(0);" class="btn btn-icon btn-sm rounded-circle"><i class="ti ti-filter-edit"></i></a>
                                            <a href="javascript:void(0);" class="btn btn-icon btn-sm rounded-circle"><i class="ti ti-settings"></i></a>
                                            <a href="javascript:void(0);" class="btn btn-icon btn-sm rounded-circle"><i class="ti ti-refresh"></i></a>
                                        </div>
                                    </div>
                                </div>
                                @if (session('status'))
                                    <div class="alert alert-success mt-3 mb-0" role="alert">
                                        {{ session('status') }}
                                    </div>
                                @endif
                                @if ($errors->has('compose'))
                                    <div class="alert alert-danger mt-3 mb-0" role="alert">
                                        {{ $errors->first('compose') }}
                                    </div>
                                @endif
                                <div class="alert d-none mt-3 mb-0" role="alert" data-email-compose-feedback></div>
                            </div>
                            <div class="list-group list-group-flush mails-list">
                                @foreach ($inboxItems as $item)
                                    <div class="list-group-item email-message-row"
                                        data-email-item="1"
                                        data-folder="inbox"
                                        data-subject="{{ e($item['subject'] ?? '(No subject)') }}"
                                        data-contact-label="From"
                                        data-contact-value="{{ e($item['from'] ?? '-') }}"
                                        data-preview="{{ e($item['preview'] ?? '') }}"
                                        data-time="{{ e($item['receivedAt'] ?? '-') }}"
                                        data-time-iso="{{ e($item['receivedAtIso'] ?? '') }}"
                                        data-search-text="{{ strtolower(trim(($item['from'] ?? '').' '.($item['subject'] ?? '').' '.($item['preview'] ?? ''))) }}"
                                        role="button"
                                        tabindex="0"
                                        aria-label="Open email preview">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div>
                                                <h6 class="mb-1">{{ $item['subject'] ?? '(No subject)' }}</h6>
                                                <p class="mb-1 text-muted">From: {{ $item['from'] ?? '-' }}</p>
                                                <p class="mb-0 text-muted">{{ $item['preview'] ?? '' }}</p>
                                            </div>
                                            <small class="text-muted" title="{{ $item['receivedAtIso'] ?? '' }}">{{ $item['receivedAt'] ?? '-' }}</small>
                                        </div>
                                    </div>
                                @endforeach
                                @forelse ($sentItems as $item)
                                    <div class="list-group-item email-message-row"
                                        data-email-item="1"
                                        data-folder="sent"
                                        data-subject="{{ e($item['subject'] ?? '(No subject)') }}"
                                        data-contact-label="To"
                                        data-contact-value="{{ e($item['to'] ?? '-') }}"
                                        data-preview="{{ e($item['preview'] ?? '') }}"
                                        data-time="{{ e($item['sentAt'] ?? '-') }}"
                                        data-time-iso="{{ e($item['sentAtIso'] ?? '') }}"
                                        data-search-text="{{ strtolower(trim(($item['to'] ?? '').' '.($item['subject'] ?? '').' '.($item['preview'] ?? ''))) }}"
                                        role="button"
                                        tabindex="0"
                                        aria-label="Open email preview">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div>
                                                <h6 class="mb-1">{{ $item['subject'] ?? '(No subject)' }}</h6>
                                                <p class="mb-1 text-muted">To: {{ $item['to'] ?? '-' }}</p>
                                                <p class="mb-0 text-muted">{{ $item['preview'] ?? '' }}</p>
                                            </div>
                                            <small class="text-muted" title="{{ $item['sentAtIso'] ?? '' }}">{{ $item['sentAt'] ?? '-' }}</small>
                                        </div>
                                    </div>
                                @empty
                                    @if (count($inboxItems) === 0)
                                    <div class="list-group-item p-4 text-center text-muted email-empty-state">
                                        Belum ada email masuk maupun terkirim.
                                    </div>
                                    @endif
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-white flex-fill border-bottom email-preview-pane d-none d-lg-flex">
                    <div class="w-100 p-4 d-flex flex-column">
                        <div class="mb-3 pb-3 border-bottom">
                            <h5 class="mb-1" data-email-preview-subject>Select an email</h5>
                            <div class="d-flex align-items-center text-muted small">
                                <span data-email-preview-contact-label>From</span>
                                <span class="mx-1">:</span>
                                <span data-email-preview-contact-value>-</span>
                                <i class="ti ti-point-filled text-primary mx-2"></i>
                                <span data-email-preview-time>-</span>
                            </div>
                        </div>
                        <div class="flex-fill">
                            <p class="text-muted mb-0" data-email-preview-body>Pilih salah satu email di daftar untuk melihat preview isi pesan.</p>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
    <!-- /Page Wrapper -->

    @if ($isGlobalAdmin)
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
            <form action="{{ route('email', request()->filled('Label') ? ['Label' => request('Label')] : []) }}" method="POST" data-email-compose-form>
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
                        <button type="submit" class="btn btn-primary d-inline-flex align-items-center ms-2" data-email-compose-submit>Send <i class="ti ti-arrow-right ms-2"></i></button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    @endif
    
@endsection