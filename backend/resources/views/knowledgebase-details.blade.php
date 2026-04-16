<?php $page = 'knowledgebase-details'; ?>
@extends('layout.mainlayout')
@section('content')

<div class="page-wrapper">
    <div class="content">

        <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
            <div class="my-auto mb-2">
                <h2 class="mb-1">{{ $article['title'] }}</h2>
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item">
                            <a href="{{ url('index') }}"><i class="ti ti-smart-home"></i></a>
                        </li>
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
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3 flex-wrap gap-2">
                            <span class="badge bg-primary">{{ $category['title'] }}</span>
                            @if (!empty($article['reading_minutes']))
                                <span class="badge bg-light text-dark border">± {{ (int) $article['reading_minutes'] }} menit baca</span>
                            @endif
                            <a href="{{ route('knowledgebase.category', ['slug' => $category['slug']]) }}" class="fs-13 fw-medium">Kembali ke kategori</a>
                        </div>
                        @if (!empty($article['excerpt']))
                            <p class="fs-14 text-muted mb-4 pb-3 border-bottom">{{ $article['excerpt'] }}</p>
                        @endif
                        <div class="col-xl-12 border-bottom mb-3 pb-4 arcav-kb-article-body">
                            {!! $article['body_html'] ?? '' !!}
                        </div>
                        <p class="fs-12 text-muted mb-0">Dokumentasi ini disimpan di repositori aplikasi; hubungi admin tenant jika butuh penyesuaian khusus.</p>
                    </div>
                </div>
            </div>
            @include('hcm.partials.knowledgebase-aside', ['activeCategory' => $category])
        </div>
    </div>
</div>

@endsection
