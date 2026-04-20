<?php $page = 'register'; ?>
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
                    </div>
                    <div class="authentication-card w-100">
                        <div class="authen-overlay-item border w-100">
                            <h1 class="text-white display-1">Pendaftaran akun kini diarahkan ke onboarding company.</h1>
                            <div class="mt-4">
                                <p class="text-white fs-20 fw-semibold text-center">Pilih plan dulu, lalu lanjut isi form company agar tenant, paket, dan billing flow tetap konsisten.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-7 col-md-12 col-sm-12">
                <div class="row justify-content-center align-items-center vh-100 overflow-auto flex-wrap">
                    <div class="col-md-8 mx-auto vh-100">
                        <div class="vh-100 d-flex flex-column justify-content-between p-4 pb-0">
                            <div class="mx-auto mb-5 text-center">
                                <img src="{{URL::asset('build/img/image111.png')}}" class="img-fluid" alt="Logo">
                            </div>
                            <div>
                                <div class="text-center mb-4">
                                    <span class="badge badge-soft-warning mb-3">Registration Gate Closed</span>
                                    <h2 class="mb-2">Daftarkan company Anda dari landing page</h2>
                                    <p class="mb-0 text-muted">Form sign up lama sudah ditutup agar calon customer langsung memilih plan yang tersedia, lalu masuk ke onboarding company yang resmi.</p>
                                </div>

                                <div class="alert alert-info d-flex align-items-start gap-2 mb-4" role="alert">
                                    <i class="ti ti-info-circle fs-18 mt-1"></i>
                                    <div>
                                        <strong>Alur baru:</strong> lihat semua plan yang aktif, pilih paket yang cocok, lalu lanjut ke form onboarding company. Employee account tetap dibuat dari workspace company setelah tenant aktif.
                                    </div>
                                </div>

                                <div class="card border shadow-sm mb-4">
                                    <div class="card-body">
                                        <h5 class="mb-3">Mulai dari sini</h5>
                                        <div class="d-grid gap-2">
                                            <a href="{{ url('/landing#pricing') }}" class="btn btn-primary btn-lg">Daftarkan your company here</a>
                                            <a href="{{ route('trial') }}" class="btn btn-outline-primary btn-lg">Langsung ke form onboarding company</a>
                                            <a href="{{ route('login') }}" class="btn btn-light btn-lg border">Saya sudah punya akun, kembali ke login</a>
                                        </div>
                                        <p class="text-muted small mb-0 mt-3">Semua plan yang aktif terbuka di section pricing landing page. Setelah memilih plan, customer akan diarahkan ke flow onboarding yang sama seperti CTA dari landing page.</p>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-5 pb-4 text-center">
                                <p class="mb-0 text-gray-9">Copyright &copy; 2025 - Arkav.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection