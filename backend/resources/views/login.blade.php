<?php $page = 'login'; ?>
@extends('layout.mainlayout')
@section('content')
<div class="container-fuild">
    <div class="w-100 overflow-hidden position-relative flex-wrap d-block vh-100">
        <div class="row">
            <div class="col-lg-5">
                <div class="login-background position-relative d-lg-flex align-items-center justify-content-center d-none flex-wrap vh-100">
                    <div class="bg-overlay-img">
                        <img src="{{URL::asset('build/img/bg/bg-01.png')}}" class="bg-1" alt="Img">
                        <img src="{{URL::asset('build/img/bg/bg-02.png')}}" class="bg-2" alt="Img">
                        <!-- <img src="{{URL::asset('build/img/bg/bg-03.png')}}" class="bg-3" alt="Img"> -->
                    </div>
                    <div class="authentication-card w-100">
                        <div class="authen-overlay-item border w-200">
                            <h1 class="text-white display-1"><img src="build/img/image111.png" ></b><br>Human Capital Management</h1>
                            <!-- <div class="my-4 mx-auto authen-overlay-img">
                                <img src="{{URL::asset('build/img/bg/authentication-bg-01.png')}}" alt="Img">
                            </div> -->
                            <br>
                            <div>
                                <p class="text-white fs-20 fw-semibold text-center">Empowering your Organization People.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-7 col-md-12 col-sm-12">
                <div class="row justify-content-center align-items-center vh-100 overflow-auto flex-wrap">
                    <div class="col-md-7 mx-auto vh-100">
                        <form id="api-login-form" class="vh-100">
                            <div class="vh-100 d-flex flex-column justify-content-between p-2 pb-0">
                                <div class=" mx-auto mb-5 text-center">
                                    <img src="{{URL::asset('build/img/logologin2.png')}}"
                                        class="img-fluid" alt="Logo">
                                </div>
                                <div class="">
                                    <div class="text-center mb-4">
                                        <h2 class="mb-2">Sign In</h2>
                                        <p class="mb-0">Please enter your details to sign in</p>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Login Mode</label>
                                        <div class="d-flex align-items-center gap-4">
                                            <div class="form-check form-check-md mb-0">
                                                <input class="form-check-input" type="radio" name="login_mode" id="login_mode_regular" value="regular" checked>
                                                <label class="form-check-label mt-0" for="login_mode_regular">Regular Login</label>
                                            </div>
                                            <div class="form-check form-check-md mb-0">
                                                <input class="form-check-input" type="radio" name="login_mode" id="login_mode_company" value="company">
                                                <label class="form-check-label mt-0" for="login_mode_company">Login as Company</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div id="company-code-wrapper" class="mb-3 d-none">
                                        <label class="form-label">Company Code</label>
                                        <div class="input-group">
                                            <input id="login-company-code" type="text" class="form-control border-end-0" placeholder="e.g. default_company" autocomplete="off">
                                            <span class="input-group-text border-start-0">
                                                <i class="ti ti-building"></i>
                                            </span>
                                        </div>
                                        <small class="text-muted">Use your company code to enter tenant-specific workspace.</small>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Email Address</label>
                                        <div class="input-group">
                                            <input id="login-email" type="email" value="" class="form-control border-end-0" required>
                                            <span class="input-group-text border-start-0">
                                                <i class="ti ti-mail"></i>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Password</label>
                                        <div class="pass-group">
                                            <input id="login-password" type="password" class="pass-input form-control" required>
                                            <span class="ti toggle-password ti-eye-off"></span>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center justify-content-between mb-3">
                                        <div class="d-flex align-items-center">
                                            <div class="form-check form-check-md mb-0">
                                                <input class="form-check-input" id="remember_me" type="checkbox">
                                                <label for="remember_me" class="form-check-label mt-0">Remember Me</label>
                                            </div>
                                        </div>
                                        <div class="text-end">
                                            <a href="{{url('forgot-password')}}" class="link-danger">Forgot Password?</a>
                                        </div>
                                    </div>
                                    <div id="login-error" class="alert alert-danger d-none" role="alert"></div>
                                    <div class="mb-3">
                                        <button id="login-submit" type="submit" class="btn btn-primary w-100">Sign In</button>
                                    </div>
                                    <div class="text-center">
                                        <h6 class="fw-normal text-dark mb-0">Don’t have an account? 
                                            <a href="{{url('register')}}" class="hover-a"> Create Account</a>
                                        </h6>
                                    </div>
                                </div>
                                <div class="mt-5 pb-4 text-center">
                                    <p class="mb-0 text-gray-9">Copyright &copy; 2025 - Arkav.</p>
                                </div>
                            </div>
                        </form>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>


@endsection

<script src="{{ URL::asset('build/js/api-client.js') }}"></script>
<script src="{{ URL::asset('build/js/auth-login.js') }}"></script>