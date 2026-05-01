<?php $page = 'knowledgebase-details'; ?>
@extends('layout.mainlayout')
@section('content')

<div class="page-wrapper">
    <div class="content">

        {{-- Breadcrumb --}}
        <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
            <div class="my-auto mb-2">
                <h2 class="mb-1 text-truncate" style="max-width:600px;">{{ $article['title'] }}</h2>
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="{{ url('index') }}"><i class="ti ti-smart-home"></i></a></li>
                        <li class="breadcrumb-item">Administration</li>
                        <li class="breadcrumb-item"><a href="{{ route('knowledgebase') }}">Knowledge Base</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('knowledgebase.category', ['slug' => $category['slug']]) }}">{{ $category['title'] }}</a></li>
                        <li class="breadcrumb-item active" aria-current="page">{{ \Illuminate\Support\Str::limit($article['title'], 40) }}</li>
                    </ol>
                </nav>
            </div>
            <div class="head-icons mb-2 mb-md-0">
                <a href="javascript:void(0);" data-bs-toggle="tooltip" title="Collapse" id="collapse-header">
                    <i class="ti ti-chevrons-up"></i>
                </a>
            </div>
        </div>

        <div class="row">
            <div class="col-xl-8">
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="border-bottom mb-4 pb-3">
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <span class="badge bg-primary">{{ $category['title'] }}</span>
                            @if (!empty($article['reading_minutes']))
                                            <span class="badge bg-light text-dark border">{{ (int) $article['reading_minutes'] }} menit baca</span>
                            @endif
                                </div>
                                <a href="{{ route('knowledgebase.category', ['slug' => $category['slug']]) }}" class="btn btn-light border btn-sm">Kembali ke kategori</a>
                            </div>
                            <h4 class="mb-2">{{ $article['title'] }}</h4>
                            @if (!empty($article['excerpt']))
                                <p class="text-muted mb-0">{{ $article['excerpt'] }}</p>
                            @endif
                        </div>
                        <div class="arcav-kb-article-body">
                            {!! $article['body_html'] ?? '' !!}
                        </div>
                        <div class="alert alert-light mt-4 mb-0">
                            Konten ini mengikuti dokumentasi aplikasi aktif. Jika prosedur internal perusahaan Anda berbeda, gunakan SOP internal sebagai pelengkap.
                        </div>
                    </div>
                </div>
                @php
                    $related = collect($category['articles'] ?? [])
                        ->filter(fn($a) => ($a['slug'] ?? '') !== ($article['slug'] ?? ''))
                        ->take(3)
                        ->values();
                @endphp
                @if($related->count() > 0)
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Artikel lain di kategori ini</h6>
                    </div>
                    <div class="card-body p-0">
                        @foreach($related as $rel)
                        <a href="{{ route('knowledgebase.article', ['slug' => $rel['slug']]) }}" class="d-flex align-items-center justify-content-between gap-3 p-3 text-decoration-none text-dark border-bottom">
                            <div class="overflow-hidden">
                                <div class="fw-medium text-truncate">{{ $rel['title'] }}</div>
                                @if(!empty($rel['excerpt']))
                                    <div class="text-muted fs-12 text-truncate">{{ $rel['excerpt'] }}</div>
                                @endif
                            </div>
                            @if (!empty($rel['reading_minutes']))
                                <span class="text-muted fs-12 flex-shrink-0">{{ $rel['reading_minutes'] }} mnt</span>
                            @endif
                        </a>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>
            @include('hcm.partials.knowledgebase-aside', ['activeCategory' => $category])
        </div>
    </div>
</div>
@endsection
