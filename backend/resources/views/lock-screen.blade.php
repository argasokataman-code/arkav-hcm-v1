<?php $page = 'lock-screen'; ?>
@extends('layout.mainlayout')
@section('content')
@php
  $user = auth()->user();
  $employeeProfile = $user?->employeeProfile;
  $profilePhoto = $employeeProfile?->profile_photo_path 
    ? asset('storage/' . $employeeProfile->profile_photo_path)
    : asset('build/img/profiles/avatar-12.jpg');
  $isAuthenticated = auth()->check();
@endphp
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-5">
            @if($isAuthenticated)
                <!-- Authenticated user unlock form -->
                <form action="{{ route('lock-screen.verify') }}" method="POST">
                    @csrf
                    <div class="d-flex flex-column justify-content-between vh-100">
                        <div class=" mx-auto p-4 text-center">
                            <img src="{{URL::asset('build/img/image111.png')}}" class="img-fluid" alt="Logo">
                        </div>
                        <div class="card">
                            <div class="card-body p-4">
                                <div class=" mb-4 text-center">
                                    <h2 class="mb-2">Welcome back! </h2>
                                    <img src="{{ $profilePhoto }}" alt="Profile" class="img-fluid avatar avatar-xxl rounded-pill my-3" onerror="this.src='{{ asset('build/img/profiles/avatar-12.jpg') }}'">
                                    <h6 class="text-dark">{{ $user->name }}</h6>
                                    <small class="text-gray-6">{{ $user->email }}</small>
                                </div>
                                @if ($errors->any())
                                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                        <strong>Unlock Failed!</strong>
                                        @foreach ($errors->all() as $error)
                                            <div>{{ $error }}</div>
                                        @endforeach
                                    </div>
                                @endif
                                <div class="mb-3 ">
                                    <label class="form-label">Password</label>
                                    <div class="pass-group">
                                        <input type="password" name="password" class="pass-input form-control" placeholder="Enter Your Password" required autofocus>
                                        <span class="ti toggle-password ti-eye-off"></span>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary w-100">Unlock</button>
                            </div>								
                        </div>
                        <div class="p-4 text-center">
                            <div class="d-flex justify-content-center">
                                <a href="javascript:void(0);" onclick="event.preventDefault(); document.getElementById('logout-form').submit();" class="me-3 text-gray-9">Logout</a>
                                <form id="logout-form" action="{{ route('signout') }}" method="GET" class="d-none">
                                </form>
                                <a href="#" class="me-3 text-gray-9">Help</a>
                            </div>
                            <div class="p-2 text-center">
                                <p class="mb-0 text-gray-9">Copyright &copy; 2024 - SmartHR</p>
                            </div>
                        </div>
                    </div>
                </form>
            @else
                <!-- Unauthenticated: redirect to login -->
                <div class="d-flex flex-column justify-content-center align-items-center vh-100 text-center">
                    <img src="{{URL::asset('build/img/image111.png')}}" class="img-fluid mb-4" alt="Logo" style="max-width: 200px;">
                    <h2 class="mb-4">Session Expired</h2>
                    <p class="text-gray-6 mb-4">Your session has expired. Please login again to continue.</p>
                    <a href="{{ route('login') }}" class="btn btn-primary">Go to Login</a>
                </div>
            @endif
        </div>
    </div>
</div>

@endsection