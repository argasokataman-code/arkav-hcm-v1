<?php $page = 'knowledgebase'; ?>
@extends('layout.mainlayout')
@section('content')

@php
    $totalArticles = collect($categories)->sum(fn($c) => count($c['articles'] ?? []));
    $totalCategories = count($categories);
    $guidedTutorials = $guidedTutorials ?? [];
@endphp

<div class="page-wrapper">
    <div class="content">

        <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
            <div class="my-auto mb-2">
                <h2 class="mb-1">Knowledge Base</h2>
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="{{ url('index') }}"><i class="ti ti-smart-home"></i></a></li>
                        <li class="breadcrumb-item">Administration</li>
                        <li class="breadcrumb-item active" aria-current="page">Knowledge Base</li>
                    </ol>
                </nav>
            </div>
            <div class="head-icons mb-2 mb-md-0">
                <a href="javascript:void(0);" data-bs-toggle="tooltip" title="Collapse" id="collapse-header">
                    <i class="ti ti-chevrons-up"></i>
                </a>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <div class="border-bottom mb-3 pb-3">
                    <div class="d-flex align-items-start justify-content-between flex-wrap gap-2">
                        <div>
                            <h4 class="mb-1">Panduan Arcav HCM</h4>
                            <p class="text-muted fs-13 mb-0">Dokumentasi alur operasional aplikasi untuk admin, operator, dan karyawan.</p>
                        </div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <span class="badge bg-primary-subtle text-primary">{{ $totalCategories }} kategori</span>
                            <span class="badge bg-success-subtle text-success">{{ $totalArticles }} artikel</span>
                        </div>
                    </div>
                </div>
                <div class="alert alert-primary mb-3">
                    <div class="d-flex align-items-start gap-2">
                        <i class="ti ti-info-circle fs-16 mt-1"></i>
                        <div>
                            Gunakan pencarian untuk menemukan panduan per modul. Konten di sini mengikuti alur aplikasi aktif, bukan halaman demo template.
                        </div>
                    </div>
                </div>
                <form method="get" action="{{ route('knowledgebase') }}" class="row g-2 align-items-end">
                    <div class="col-lg-8">
                        <label class="form-label">Cari artikel</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="ti ti-search"></i></span>
                            <input type="search" name="q" value="{{ $query }}" class="form-control" placeholder="Cari judul, ringkasan, atau topik" maxlength="120">
                            <button type="submit" class="btn btn-primary">Cari</button>
                            @if($query !== '')
                                <a href="{{ route('knowledgebase') }}" class="btn btn-light border">Reset</a>
                            @endif
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <label class="form-label">Akses cepat</label>
                        <div class="d-flex gap-2 flex-wrap">
                            <a href="{{ route('knowledgebase.article', ['slug' => 'checklist-onboarding-admin-hcm']) }}" class="btn btn-light border btn-sm">Onboarding</a>
                            <a href="{{ route('knowledgebase.article', ['slug' => 'payroll-run-bulanan']) }}" class="btn btn-light border btn-sm">Payroll</a>
                            <a href="{{ route('knowledgebase.article', ['slug' => 'absensi-dan-gps']) }}" class="btn btn-light border btn-sm">Absensi</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if($query !== '')
            <div class="alert alert-info d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                <div><i class="ti ti-search me-1"></i>Hasil pencarian untuk <strong>{{ e($query) }}</strong></div>
                <a href="{{ route('knowledgebase') }}" class="btn btn-sm btn-light border">Tampilkan semua</a>
            </div>
        @endif

        @if($query === '' && !empty($guidedTutorials))
            <div class="card mb-3">
                <div class="card-body">
                    <div class="border-bottom mb-3 pb-3">
                        <h5 class="mb-1">Tutorial Penggunaan Aplikasi</h5>
                        <p class="text-muted fs-13 mb-0">Mulai dari sini kalau yang Anda cari adalah panduan memakai aplikasi langkah demi langkah, bukan penjelasan modul terpisah.</p>
                    </div>
                    <div class="row">
                        @foreach($guidedTutorials as $tutorial)
                            <div class="col-xl-6">
                                <div class="border rounded p-3 mb-3 h-100">
                                    <div class="d-flex align-items-center justify-content-between gap-2 mb-2 flex-wrap">
                                        <span class="badge bg-info-subtle text-info">{{ $tutorial['category_title'] }}</span>
                                        @if(($tutorial['reading_minutes'] ?? 0) > 0)
                                            <span class="text-muted fs-12">{{ $tutorial['reading_minutes'] }} menit baca</span>
                                        @endif
                                    </div>
                                    <h6 class="mb-2"><a href="{{ route('knowledgebase.article', ['slug' => $tutorial['slug']]) }}" class="text-dark">{{ $tutorial['title'] }}</a></h6>
                                    <p class="text-muted fs-13 mb-3">{{ $tutorial['excerpt'] }}</p>
                                    <a href="{{ route('knowledgebase.article', ['slug' => $tutorial['slug']]) }}" class="btn btn-primary btn-sm">Buka tutorial</a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        <div class="row">
            @forelse ($categories as $cat)
            <div class="col-xl-4 col-md-6">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-start justify-content-between gap-2 mb-3">
                            <div class="d-flex align-items-center gap-2">
                                <span class="avatar avatar-md rounded bg-light text-primary flex-shrink-0">
                                    <i class="{{ $cat['icon'] ?? 'ti ti-folder' }} fs-18"></i>
                                </span>
                                <div>
                                    <h6 class="mb-1 text-truncate"><a href="{{ route('knowledgebase.category', ['slug' => $cat['slug']]) }}" class="text-dark">{{ $cat['title'] }}</a></h6>
                                    <span class="badge bg-primary-subtle text-primary">{{ count($cat['articles'] ?? []) }} artikel</span>
                                </div>
                            </div>
                        </div>
                        @if (!empty($cat['description']))
                            <p class="text-muted fs-13 mb-3">{{ \Illuminate\Support\Str::limit($cat['description'], 120) }}</p>
                        @endif
                        <div class="border-top pt-3">
                            @foreach (array_slice($cat['articles'] ?? [], 0, 4) as $article)
                                <div class="d-flex align-items-start justify-content-between gap-2 {{ $loop->last ? '' : 'mb-3' }}">
                                    <div class="overflow-hidden">
                                        <div class="fs-13 fw-medium text-truncate">
                                            <a href="{{ route('knowledgebase.article', ['slug' => $article['slug']]) }}" class="text-dark">
                                        {{ $article['title'] }}
                                            </a>
                                        </div>
                                        @if (!empty($article['excerpt']))
                                            <div class="text-muted fs-12">{{ \Illuminate\Support\Str::limit($article['excerpt'], 70) }}</div>
                                        @endif
                                    </div>
                                    @if (!empty($article['reading_minutes']))
                                        <span class="text-muted fs-12 flex-shrink-0">{{ $article['reading_minutes'] }} mnt</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                        <div class="border-top mt-3 pt-3 d-flex align-items-center justify-content-between">
                            <span class="text-muted fs-12">Lihat semua artikel di kategori ini</span>
                            <a href="{{ route('knowledgebase.category', ['slug' => $cat['slug']]) }}" class="btn btn-sm btn-light border">Buka</a>
                        </div>
                    </div>
                </div>
            </div>
            @empty
            <div class="col-12">
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="ti ti-search-off fs-24 text-muted mb-2"></i>
                        <h6 class="mb-1">Artikel tidak ditemukan</h6>
                        <p class="text-muted fs-13 mb-3">Coba kata kunci lain atau kembali ke seluruh kategori.</p>
                        <a href="{{ route('knowledgebase') }}" class="btn btn-primary">Tampilkan semua</a>
                    </div>
                </div>
            </div>
            @endforelse
        </div>

    </div>
</div>

@endsection
