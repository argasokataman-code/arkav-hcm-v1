<?php $page = 'knowledgebase-view'; ?>
@extends('layout.mainlayout')
@section('content')

<div class="page-wrapper">
    <div class="content">

        <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
            <div class="my-auto mb-2">
                <h2 class="mb-1">{{ $category['title'] }}</h2>
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="{{ url('index') }}"><i class="ti ti-smart-home"></i></a></li>
                        <li class="breadcrumb-item"><a href="{{ route('knowledgebase') }}">Knowledge Base</a></li>
                        <li class="breadcrumb-item active" aria-current="page">{{ $category['title'] }}</li>
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
                        <div class="border-bottom mb-3 pb-3 d-flex align-items-start justify-content-between flex-wrap gap-2">
                            <div class="d-flex align-items-center gap-2">
                                <span class="avatar avatar-md rounded bg-light text-primary flex-shrink-0">
                                    <i class="{{ $category['icon'] ?? 'ti ti-folder' }} fs-18"></i>
                                </span>
                                <div>
                                    <h4 class="mb-1">{{ $category['title'] }}</h4>
                                    @if (!empty($category['description']))
                                        <div class="text-muted fs-13">{{ $category['description'] }}</div>
                                    @endif
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <span class="badge bg-primary-subtle text-primary">{{ count($category['articles'] ?? []) }} artikel</span>
                                <a href="{{ route('knowledgebase') }}" class="btn btn-light border btn-sm">Semua kategori</a>
                            </div>
                        </div>
                        <div class="alert alert-light mb-0">
                            Pilih artikel untuk membuka panduan lengkap per topik.
                        </div>
                    </div>
                </div>
                @foreach ($category['articles'] ?? [] as $i => $article)
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
                            <div class="d-flex align-items-start gap-3 flex-grow-1">
                                <span class="badge bg-light text-dark border mt-1">{{ $i + 1 }}</span>
                                <div class="overflow-hidden">
                                    <h6 class="mb-2"><a href="{{ route('knowledgebase.article', ['slug' => $article['slug']]) }}" class="text-dark">{{ $article['title'] }}</a></h6>
                                    @if (!empty($article['excerpt']))
                                        <p class="text-muted fs-13 mb-2">{{ $article['excerpt'] }}</p>
                                    @endif
                                </div>
                            </div>
                            <div class="text-end">
                                    @if (!empty($article['reading_minutes']))
                                    <div class="text-muted fs-12 mb-2">{{ (int) $article['reading_minutes'] }} menit baca</div>
                                    @endif
                                <a href="{{ route('knowledgebase.article', ['slug' => $article['slug']]) }}" class="btn btn-primary btn-sm">Baca artikel</a>
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach
                @if(empty($category['articles']))
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="ti ti-file-off fs-24 text-muted mb-2"></i>
                        <h6 class="mb-1">Belum ada artikel di kategori ini</h6>
                    </div>
                </div>
                @endif
            </div>
            @include('hcm.partials.knowledgebase-aside', ['activeCategory' => $category])
        </div>
    </div>
</div>

@endsection
