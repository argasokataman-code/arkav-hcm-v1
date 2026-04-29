<?php $page = 'knowledgebase'; ?>
@extends('layout.mainlayout')
@section('content')

<div class="page-wrapper">
    <div class="content">

        <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
            <div class="my-auto mb-2">
                <h2 class="mb-1">Knowledge Base</h2>
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item">
                            <a href="{{ url('index') }}"><i class="ti ti-smart-home"></i></a>
                        </li>
                        <li class="breadcrumb-item">Administration</li>
                        <li class="breadcrumb-item active" aria-current="page">Knowledge Base</li>
                    </ol>
                </nav>
            </div>
            <div class="head-icons mb-2 mb-md-0">
                <a href="javascript:void(0);" data-bs-toggle="tooltip" data-bs-placement="top" title="Collapse" id="collapse-header">
                    <i class="ti ti-chevrons-up"></i>
                </a>
            </div>
        </div>

        <div class="alert alert-primary border-0 shadow-sm mb-3" role="region" aria-label="Tentang Knowledge Base">
            <h6 class="alert-heading fs-15 mb-2"><i class="ti ti-books me-1"></i>Untuk admin dan operator</h6>
            <p class="mb-2 fs-14">Dokumentasi ini menjelaskan <strong>alur nyata Arcav HCM</strong> (route web, peran admin vs karyawan, titik integrasi API). Tiap artikel memuat langkah, checklist, atau tabel ringkas — bukan placeholder. Konten diambil dari repositori; untuk perubahan prosedur internal perusahaan silakan tambahkan SOP di luar aplikasi atau minta fork teks ke tim produk.</p>
            <p class="mb-0 fs-13 text-muted">Butuh indeks modul teknis? Buka juga <a href="{{ url('pages') }}" class="fw-semibold">/pages</a> dan folder <code>docs/features/</code> di repo.</p>
        </div>

        <div class="card mb-3">
            <div class="card-body p-3">
                <form method="get" action="{{ route('knowledgebase') }}" class="row g-2 align-items-end">
                    <div class="col-md-8 col-lg-6">
                        <label class="form-label mb-1">Cari artikel</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="ti ti-search"></i></span>
                            <input type="search" name="q" value="{{ $query }}" class="form-control" placeholder="Judul atau ringkasan…" maxlength="120">
                            <button type="submit" class="btn btn-primary">Cari</button>
                            @if($query !== '')
                                <a href="{{ route('knowledgebase') }}" class="btn btn-outline-secondary">Reset</a>
                            @endif
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="row">
            @forelse ($categories as $cat)
                <div class="col-xl-4 col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <i class="{{ $cat['icon'] ?? 'ti ti-folder' }} text-primary fs-24 me-1"></i>
                                <a href="{{ route('knowledgebase.category', ['slug' => $cat['slug']]) }}" class="text-dark fs-16 fw-medium text-truncate">
                                    {{ $cat['title'] }}
                                    <span class="text-primary">({{ count($cat['articles'] ?? []) }})</span>
                                </a>
                            </div>
                            @foreach (array_slice($cat['articles'] ?? [], 0, 5) as $article)
                                <div class="d-flex align-items-center {{ !$loop->last ? 'mb-2 pb-1' : '' }}">
                                    <i class="ti ti-file me-1"></i>
                                    <a href="{{ route('knowledgebase.article', ['slug' => $article['slug']]) }}" class="text-gray fs-14 fw-normal text-truncate">{{ $article['title'] }}</a>
                                </div>
                            @endforeach
                            @if(count($cat['articles'] ?? []) > 5)
                                <div class="mt-2">
                                    <a href="{{ route('knowledgebase.category', ['slug' => $cat['slug']]) }}" class="fs-13 fw-medium">Lihat semua…</a>
                                </div>
                            @endif
                            @if (!empty($cat['description']))
                                <p class="text-muted small mt-3 mb-0">{{ \Illuminate\Support\Str::limit($cat['description'], 200) }}</p>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12">
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <p class="text-muted mb-2">Tidak ada artikel yang cocok.</p>
                            <a href="{{ route('knowledgebase') }}" class="btn btn-sm btn-outline-primary">Kosongkan pencarian</a>
                        </div>
                    </div>
                </div>
            @endforelse
        </div>
    </div>
</div>

@endsection
