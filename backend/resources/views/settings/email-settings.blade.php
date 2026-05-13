<?php $page = 'email-settings'; ?>
@php
    $isGlobalHcmAdmin = (bool) ((request()->user() ?: auth()->user())?->isGlobalHcmAdmin());
@endphp

@extends('layout.mainlayout')
@section('content')
<div class="page-wrapper">
    <div class="content">
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
                <div class="card">
                    <div class="card-body">
                        <div class="border-bottom mb-3 pb-3">
                            <h4>Email Transport (ENV Managed)</h4>
                        </div>

                        <div class="alert alert-info mb-3">
                            Konfigurasi email sekarang dikelola via <strong>.env</strong> untuk keamanan runtime. Perubahan dari UI dinonaktifkan.
                        </div>

                        <div class="table-responsive">
                            <table class="table table-striped">
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
@endsection
