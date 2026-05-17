<?php $page = 'email-settings'; ?>
@php
    $isGlobalHcmAdmin = (bool) ((request()->user() ?: auth()->user())?->isGlobalHcmAdmin());
@endphp

@extends('layout.mainlayout')
@section('content')
<div class="page-wrapper">
    <div class="content" data-email-settings-page>
        <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
            <div class="my-auto mb-2">
                <h2 class="mb-1">Email Settings</h2>
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="{{ url('index') }}"><i class="ti ti-smart-home"></i></a></li>
                        <li class="breadcrumb-item">Administration</li>
                        <li class="breadcrumb-item active" aria-current="page">System Settings</li>
                    </ol>
                </nav>
            </div>
        </div>

        <ul class="nav nav-tabs nav-tabs-solid bg-transparent border-bottom mb-3">
            <li class="nav-item"><a class="nav-link" href="{{ url('profile-settings') }}"><i class="ti ti-settings me-2"></i>General Settings</a></li>
            @if ($isGlobalHcmAdmin)
            <li class="nav-item"><a class="nav-link" href="{{ url('business-settings') }}"><i class="ti ti-world-cog me-2"></i>Website Settings</a></li>
            @endif
            <li class="nav-item"><a class="nav-link active" href="{{ url('email-settings') }}"><i class="ti ti-server-cog me-2"></i>System Settings</a></li>
        </ul>

        <div class="row">
            <div class="col-xl-3 theiaStickySidebar">
                <div class="card"><div class="card-body"><div class="d-flex flex-column list-group settings-list">
                    <a href="{{ url('email-settings') }}" class="d-inline-flex align-items-center rounded active py-2 px-3"><i class="ti ti-arrow-badge-right me-2"></i>Email Settings</a>
                </div></div></div>
            </div>
            <div class="col-xl-9">
                <div id="email-settings-feedback" class="alert d-none mb-3" data-email-settings-feedback></div>

                <div class="card">
                    <div class="card-body">
                        <div class="border-bottom mb-3 pb-3">
                            <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
                                <div>
                                    <h4 class="mb-1">Email Runtime Control Plane</h4>
                                    <p class="text-muted mb-0">Kelola profile SMTP atau Mailtrap aktif, test connection, lalu pakai runtime ini saat DNS dan sender domain provider sudah ready.</p>
                                </div>
                                <div class="d-flex flex-wrap gap-2">
                                    <button type="button" class="btn btn-outline-light border" data-email-settings-refresh>
                                        <i class="ti ti-refresh me-1"></i>Refresh
                                    </button>
                                    <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#smtpsettings">
                                        <i class="ti ti-mail-cog me-1"></i>Edit SMTP
                                    </button>
                                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#phpmailersettings">
                                        <i class="ti ti-brand-mailgun me-1"></i>Edit Mailtrap
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-info mb-3 d-none" data-email-settings-loading>
                            Memuat profile email runtime...
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-12 col-lg-6">
                                <div class="border rounded p-3 h-100">
                                    <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
                                        <div>
                                            <h5 class="mb-1">Active Runtime Profile</h5>
                                            <p class="text-muted mb-0">Source of truth untuk mailer aplikasi saat ini.</p>
                                        </div>
                                        <span class="badge bg-outline-primary text-primary border" data-email-settings-summary-provider>Loading...</span>
                                    </div>

                                    <dl class="row mb-0 g-2">
                                        <dt class="col-sm-4 text-muted">Sender</dt>
                                        <dd class="col-sm-8 mb-0" data-email-settings-summary-sender>-</dd>

                                        <dt class="col-sm-4 text-muted">Transport</dt>
                                        <dd class="col-sm-8 mb-0" data-email-settings-summary-transport>-</dd>

                                        <dt class="col-sm-4 text-muted">Credential</dt>
                                        <dd class="col-sm-8 mb-0" data-email-settings-summary-credential>-</dd>

                                        <dt class="col-sm-4 text-muted">Last Save</dt>
                                        <dd class="col-sm-8 mb-0" data-email-settings-summary-updated-at>Belum ada update dari UI ini.</dd>
                                    </dl>
                                </div>
                            </div>

                            <div class="col-12 col-lg-6">
                                <div class="border rounded p-3 h-100">
                                    <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
                                        <div>
                                            <h5 class="mb-1">Mailtrap Health</h5>
                                            <p class="text-muted mb-0">Probe API token/account untuk memastikan koneksi provider siap.</p>
                                        </div>
                                        <button type="button" class="btn btn-outline-light btn-sm border" data-email-settings-refresh-status>
                                            <i class="ti ti-refresh me-1"></i>Check
                                        </button>
                                    </div>

                                    <dl class="row mb-3 g-2">
                                        <dt class="col-sm-4 text-muted">Status</dt>
                                        <dd class="col-sm-8 mb-0" data-email-settings-mailtrap-connected>-</dd>

                                        <dt class="col-sm-4 text-muted">Source</dt>
                                        <dd class="col-sm-8 mb-0" data-email-settings-mailtrap-source>-</dd>

                                        <dt class="col-sm-4 text-muted">Account</dt>
                                        <dd class="col-sm-8 mb-0" data-email-settings-mailtrap-account>-</dd>

                                        <dt class="col-sm-4 text-muted">Visible Tokens</dt>
                                        <dd class="col-sm-8 mb-0" data-email-settings-mailtrap-token-count>-</dd>
                                    </dl>

                                    <div class="rounded bg-light p-3 mb-2 d-none" data-email-settings-mailtrap-error></div>
                                    <div class="small text-muted" data-email-settings-mailtrap-tokens-empty>Belum ada token visible yang terbaca.</div>
                                    <div class="d-flex flex-wrap gap-2 d-none" data-email-settings-mailtrap-tokens></div>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-warning mb-3">
                            UI ini menyimpan profile runtime ke group <strong>settings=email</strong>. Deliverability real tetap bergantung pada sender domain dan DNS di provider yang Anda kelola sendiri.
                        </div>

                        <div class="border-top pt-3 mt-3">
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                                <div>
                                    <h5 class="mb-1">Deployment Fallback Reference</h5>
                                    <p class="text-muted mb-0">Nilai ENV/config yang masih tersedia sebagai baseline deploy dan fallback observability.</p>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-striped mb-0">
                                    <tbody>
                                        <tr><th style="width:240px;">MAIL_MAILER</th><td>{{ config('mail.default') }}</td></tr>
                                        <tr><th>MAIL_HOST</th><td>{{ config('mail.mailers.smtp.host') }}</td></tr>
                                        <tr><th>MAIL_PORT</th><td>{{ config('mail.mailers.smtp.port') }}</td></tr>
                                        <tr><th>MAIL_ENCRYPTION</th><td>{{ config('mail.mailers.smtp.encryption') }}</td></tr>
                                        <tr><th>MAIL_USERNAME</th><td>{{ config('mail.mailers.smtp.username') }}</td></tr>
                                        <tr><th>MAIL_FROM_ADDRESS</th><td>{{ config('mail.from.address') }}</td></tr>
                                        <tr><th>MAIL_FROM_NAME</th><td>{{ config('mail.from.name') }}</td></tr>
                                        <tr><th>MAILTRAP_ACCOUNT_ID</th><td>{{ config('services.mailtrap.account_id') }}</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@include('components.modals.email')
