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
                        <li class="breadcrumb-item">
                            <a href="{{ url('index') }}"><i class="ti ti-smart-home"></i></a>
                        </li>
                        <li class="breadcrumb-item">Administration</li>
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
                @if (!empty($category['description']))
                    <div class="alert alert-light border shadow-sm mb-3" role="note">
                        <p class="mb-0 fs-14 text-gray-700">{{ $category['description'] }}</p>
                    </div>
                @endif
                <div class="card">
                    <div class="card-body pb-1">
                        <div class="d-flex align-items-center mb-3">
                            <i class="{{ $category['icon'] ?? 'ti ti-folder' }} text-primary fs-24 me-1"></i>
                            <span class="text-dark fs-16 fw-medium text-truncate">{{ $category['title'] }}</span>
                        </div>
                        @foreach ($category['articles'] ?? [] as $article)
                            <div class="col-xl-12">
                                <div class="card mb-3">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center mb-2 pb-1">
                                            <i class="ti ti-file me-1"></i>
                                            <span class="text-dark fs-14 fw-medium text-truncate">{{ $article['title'] }}</span>
                                        </div>
                                        <div class="ps-3">
                                            <p class="fs-14 fw-normal mb-1">{{ $article['excerpt'] ?? '' }}</p>
                                            <div class="d-flex flex-wrap align-items-center gap-2">
                                                @if (!empty($article['reading_minutes']))
                                                    <span class="badge bg-light text-dark border fs-11">± {{ (int) $article['reading_minutes'] }} menit baca</span>
                                                @endif
                                                <a href="{{ route('knowledgebase.article', ['slug' => $article['slug']]) }}" class="text-primary fs-12 fw-medium">Baca selengkapnya</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
            @include('hcm.partials.knowledgebase-aside', ['activeCategory' => $category])
        </div>
    </div>
</div>

@endsection
