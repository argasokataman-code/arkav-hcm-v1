<?php $page = 'login'; ?>
@extends('layout.mainlayout')

@section('content')
@php
    $companyName = \App\Support\WebsiteSettings::businessCompanyName();
    $darkLogoUrl = \App\Support\WebsiteSettings::brandingUrl('dark_logo', URL::asset('build/img/image111.png'));
    $whiteLogoUrl = \App\Support\WebsiteSettings::brandingUrl('white_logo', $darkLogoUrl);
@endphp

<style>
    .arcav-login-page {
        min-height: 100vh;
        background:
            radial-gradient(circle at top left, rgba(242, 101, 34, 0.22), transparent 34%),
            radial-gradient(circle at bottom right, rgba(59, 112, 128, 0.18), transparent 30%),
            linear-gradient(180deg, #fff8f4 0%, #fff 100%);
        overflow: hidden;
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 2rem 1rem;
    }

    .arcav-login-page::before,
    .arcav-login-page::after {
        content: "";
        position: absolute;
        width: 28rem;
        height: 28rem;
        background: rgba(242, 101, 34, 0.08);
        filter: blur(24px);
        pointer-events: none;
    }

    .arcav-login-page::before {
        top: -10rem;
        right: -8rem;
    }

    .arcav-login-page::after {
        left: -10rem;
        bottom: -12rem;
        background: rgba(59, 112, 128, 0.08);
    }

    .arcav-login-shell {
        position: relative;
        z-index: 1;
        width: min(100%, 44rem);
    }

    .arcav-login-card-logo img {
        width: 100%;
        height: 100%;
        object-fit: contain;
        display: block;
    }

    .arcav-login-card {
        width: 100%;
        padding: clamp(1.75rem, 3vw, 2.75rem);
        background: rgba(255, 255, 255, 0.9);
        border: 1px solid rgba(242, 101, 34, 0.12);
        box-shadow: 0 1.5rem 3rem rgba(15, 23, 42, 0.08);
        backdrop-filter: blur(16px);
    }

    .arcav-login-card-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1.75rem;
        padding-bottom: 1.25rem;
        border-bottom: 1px solid rgba(148, 163, 184, 0.18);
    }

    .arcav-login-card-logo {
        width: 10.5rem;
        height: 3.4rem;
    }

    .arcav-login-card-kicker {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        padding: 0.55rem 0.8rem;
        background: #fef0e9;
        color: #de500d;
        border: 1px solid rgba(242, 101, 34, 0.14);
        font-size: 0.78rem;
        font-weight: 700;
    }

    .arcav-login-form-intro h2 {
        margin-bottom: 0.5rem;
        color: #0f172a;
        letter-spacing: -0.03em;
        text-align: left;
        font-size: clamp(1.9rem, 3vw, 2.4rem);
    }

    .arcav-login-form-intro p,
    .arcav-login-link-row,
    .arcav-login-copyright {
        color: #64748b;
    }

    .arcav-login-form-grid {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
        gap: 1rem;
    }

    .arcav-login-mode-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.8rem;
    }

    .arcav-login-mode {
        position: relative;
    }

    .arcav-login-mode input {
        position: absolute;
        inset: 0;
        opacity: 0;
        cursor: pointer;
    }

    .arcav-login-mode label {
        width: 100%;
        min-height: 100%;
        margin: 0;
        padding: 1rem;
        display: grid;
        gap: 0.3rem;
        border: 1px solid rgba(148, 163, 184, 0.24);
        background: #fff;
        transition: border-color 0.2s ease, background 0.2s ease, box-shadow 0.2s ease;
    }

    .arcav-login-mode label small,
    #company-code-wrapper small {
        color: #64748b;
    }

    .arcav-login-mode input:checked + label {
        background: #fef0e9;
        border-color: rgba(242, 101, 34, 0.24);
        box-shadow: inset 0 0 0 1px rgba(242, 101, 34, 0.12);
    }

    .arcav-login-input,
    .arcav-login-input .input-group-text,
    .arcav-login-password,
    .arcav-login-submit,
    .arcav-login-page::before,
    .arcav-login-page::after,
    .arcav-login-card,
    .arcav-login-mode label,
    .arcav-login-card-kicker {
        border-radius: 0.2rem;
    }

    .arcav-login-input {
        border-right: 0;
        height: 3rem;
    }

    .arcav-login-input:focus,
    .arcav-login-password:focus {
        border-color: rgba(242, 101, 34, 0.4);
        box-shadow: 0 0 0 0.2rem rgba(242, 101, 34, 0.12);
    }

    .arcav-login-input-group .input-group-text {
        border-left: 0;
        background: #fff;
        color: #64748b;
    }

    .arcav-login-password {
        min-height: 3rem;
    }

    .arcav-login-password .toggle-password {
        right: 1rem;
    }

    .arcav-login-submit {
        min-height: 3.2rem;
        border: 0;
        background: linear-gradient(180deg, #f26522, #de500d);
        box-shadow: 0 0.9rem 1.6rem rgba(242, 101, 34, 0.18);
        font-weight: 700;
    }

    .arcav-login-submit:hover,
    .arcav-login-submit:focus {
        background: linear-gradient(180deg, #ff6f28, #de500d);
    }

    .arcav-login-links a {
        color: #de500d;
        font-weight: 600;
        text-decoration: none;
    }

    .arcav-login-links a:hover {
        color: #f26522;
    }

    .arcav-login-actions {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        flex-wrap: wrap;
    }

    @media (max-width: 991.98px) {
        .arcav-login-page {
            padding: 1.5rem 1rem;
        }
    }

    @media (max-width: 767.98px) {
        .arcav-login-page {
            min-height: auto;
        }

        .arcav-login-mode-grid,
        .arcav-login-form-grid {
            grid-template-columns: minmax(0, 1fr);
        }

        .arcav-login-card-top,
        .arcav-login-actions {
            align-items: flex-start;
            flex-direction: column;
        }

        .arcav-login-card {
            width: 100%;
        }
    }
</style>

<div class="arcav-login-page">
    <div class="arcav-login-shell">
        <div class="arcav-login-card">
            <div class="arcav-login-card-top">
                <div class="arcav-login-card-logo">
                    <img src="{{ $darkLogoUrl }}" alt="{{ $companyName }} logo">
                </div>
                <span class="arcav-login-card-kicker"><i class="ti ti-lock"></i> Secure Login</span>
            </div>

            <form id="api-login-form">
                <div class="arcav-login-form-intro mb-4">
                    <h2>Sign In</h2>
                    <p class="mb-0">Masuk ke workspace {{ $companyName }} dengan akun employee atau company workspace kamu.</p>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold">Login Mode</label>
                    <div class="arcav-login-mode-grid">
                        <div class="arcav-login-mode">
                            <input class="form-check-input" type="radio" name="login_mode" id="login_mode_regular" value="regular" checked>
                            <label for="login_mode_regular">
                                <strong>Login Employee</strong>
                                <small>Akses employee pribadi.</small>
                            </label>
                        </div>
                        <div class="arcav-login-mode">
                            <input class="form-check-input" type="radio" name="login_mode" id="login_mode_company" value="company">
                            <label for="login_mode_company">
                                <strong>Login Company</strong>
                                <small>Masuk dengan company code.</small>
                            </label>
                        </div>
                    </div>
                </div>

                <div id="company-code-wrapper" class="mb-3 d-none">
                    <label class="form-label fw-semibold">Company Code</label>
                    <div class="input-group arcav-login-input-group">
                        <input id="login-company-code" type="text" class="form-control arcav-login-input" placeholder="e.g. default_company" autocomplete="off">
                        <span class="input-group-text"><i class="ti ti-building"></i></span>
                    </div>
                    <small>Masukkan kode company kamu.</small>
                </div>

                <div class="arcav-login-form-grid mb-3">
                    <div>
                        <label class="form-label fw-semibold">Email Address</label>
                        <div class="input-group arcav-login-input-group">
                            <input id="login-email" type="email" value="" class="form-control arcav-login-input" autocomplete="username" required>
                            <span class="input-group-text"><i class="ti ti-mail"></i></span>
                        </div>
                    </div>

                    <div>
                        <label class="form-label fw-semibold">Password</label>
                        <div class="pass-group">
                            <input id="login-password" type="password" class="pass-input form-control arcav-login-password" autocomplete="current-password" required>
                            <span class="ti toggle-password ti-eye-off"></span>
                        </div>
                    </div>
                </div>

                <div class="arcav-login-actions mb-3 arcav-login-links">
                    <div class="form-check form-check-md mb-0">
                        <input class="form-check-input" id="remember_me" type="checkbox">
                        <label for="remember_me" class="form-check-label mt-0">Remember Me</label>
                    </div>
                    <a href="{{ url('forgot-password') }}">Forgot Password?</a>
                </div>

                <div id="login-error" class="alert alert-danger d-none" role="alert"></div>

                <div class="mb-3">
                    <button id="login-submit" type="submit" class="btn text-white w-100 arcav-login-submit">Sign In</button>
                </div>

                <div class="arcav-login-link-row text-center arcav-login-links">
                    Belum punya akun?
                    <a href="{{ route('register') }}">Daftarkan company di sini</a>
                </div>
            </form>

            <div class="mt-4 pt-3 border-top arcav-login-copyright text-center">
                Copyright &copy; 2026 - {{ $companyName }}.
            </div>
        </div>
    </div>
</div>

<script src="{{ URL::asset('build/js/core/api-client.js') }}"></script>
<script src="{{ URL::asset('build/js/core/auth-login.js') }}"></script>
@endsection