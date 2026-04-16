<?php $page = 'pages'; ?>
@extends('layout.mainlayout')
@section('content')
@php
    $hub = config('hcm_portal_hub', []);
    $pageTitle = $hub['page_title'] ?? 'Peta halaman HCM';
    $pageSubtitle = $hub['page_subtitle'] ?? '';
    $sections = $hub['sections'] ?? [];
@endphp
<!-- Page Wrapper -->
<div class="page-wrapper">
    <div class="content" data-hcm-portal-hub>

        <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
            <div class="my-auto mb-2">
                <h2 class="mb-1">{{ $pageTitle }}</h2>
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item">
                            <a href="{{ url('index') }}"><i class="ti ti-smart-home"></i></a>
                        </li>
                        <li class="breadcrumb-item">Content</li>
                        <li class="breadcrumb-item active" aria-current="page">Pages</li>
                    </ol>
                </nav>
            </div>
            <div class="d-flex my-xl-auto right-content align-items-center flex-wrap gap-2">
                <div class="mb-2" style="min-width: 220px;">
                    <label class="visually-hidden" for="hcm_portal_hub_search">Cari halaman</label>
                    <input type="search" id="hcm_portal_hub_search" class="form-control" placeholder="Cari modul atau jalur…" autocomplete="off" data-hcm-portal-hub-search>
                </div>
                <div class="head-icons ms-1 mb-2">
                    <a href="javascript:void(0);" class="" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-original-title="Collapse" id="collapse-header">
                        <i class="ti ti-chevrons-up"></i>
                    </a>
                </div>
            </div>
        </div>

        @if($pageSubtitle !== '')
            <div class="alert alert-light border shadow-none mb-4" role="note">
                <p class="mb-0 text-muted">{{ $pageSubtitle }}</p>
            </div>
        @endif

        <div class="card border-0 shadow-none mb-3 d-none" data-hcm-portal-hub-empty>
            <div class="card-body text-center text-muted py-5">
                <i class="ti ti-search-off fs-1 d-block mb-2 opacity-50"></i>
                Tidak ada halaman yang cocok dengan pencarian. Kosongkan kotak pencarian atau ubah kata kunci.
            </div>
        </div>

        <div class="row g-3">
            @foreach ($sections as $section)
                @php
                    $secTitle = $section['title'] ?? '—';
                    $secIcon = $section['icon'] ?? 'ti-layout-grid';
                    $items = $section['items'] ?? [];
                @endphp
                <div class="col-12 col-lg-6 col-xxl-4" data-hub-section>
                    <div class="card h-100 border">
                        <div class="card-header d-flex align-items-center gap-2 py-3">
                            <span class="avatar avatar-sm bg-primary-transparent rounded-circle">
                                <i class="ti {{ $secIcon }} text-primary"></i>
                            </span>
                            <h5 class="card-title mb-0 fw-semibold">{{ $secTitle }}</h5>
                        </div>
                        <div class="list-group list-group-flush rounded-0">
                            @forelse ($items as $item)
                                @php
                                    $label = $item['label'] ?? '—';
                                    $routeName = $item['route'] ?? null;
                                    $desc = $item['description'] ?? '';
                                    $href = $routeName && \Illuminate\Support\Facades\Route::has($routeName) ? route($routeName) : url('index');
                                    $path = $routeName && \Illuminate\Support\Facades\Route::has($routeName) ? parse_url(route($routeName), PHP_URL_PATH) : '/';
                                    $searchBlob = strtolower($label.' '.$desc.' '.$path);
                                @endphp
                                <a href="{{ $href }}" class="list-group-item list-group-item-action py-3" data-hub-item data-hub-search="{{ e($searchBlob) }}">
                                    <div class="d-flex w-100 justify-content-between align-items-start gap-2">
                                        <span class="fw-medium text-body">{{ $label }}</span>
                                        <code class="fs-12 text-muted flex-shrink-0">{{ $path }}</code>
                                    </div>
                                    @if($desc !== '')
                                        <small class="text-muted d-block mt-1">{{ $desc }}</small>
                                    @endif
                                </a>
                            @empty
                                <div class="list-group-item text-muted py-3">Tidak ada entri.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="card mt-4 border-dashed">
            <div class="card-body d-md-flex align-items-center justify-content-between gap-3">
                <div>
                    <h6 class="mb-1">Dokumentasi & izin</h6>
                    <p class="text-muted small mb-0">Detail per modul (API, role, skenario) ada di <code>docs/planning/active-hcm-templates-and-permissions.md</code> dan <code>docs/features/*/README.md</code>. Halaman ini tidak menyimpan konten CMS.</p>
                </div>
                <a href="{{ url('api-docs') }}" class="btn btn-outline-secondary btn-sm flex-shrink-0">API docs</a>
            </div>
        </div>

    </div>
</div>
@endsection
