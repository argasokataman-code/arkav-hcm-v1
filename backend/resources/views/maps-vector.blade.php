<?php $page = 'maps-vector'; ?>
@extends('layout.mainlayout')
@section('content')
    <div class="page-wrapper cardhead">
        <div class="content">

            <!-- Page Header -->
            @component('components.breadcrumb')
                @slot('title')
                    Typicon Icon
                @endslot
            @endcomponent
            <!-- /Page Header -->

            <!-- Start::row-1 -->
            <div class="row">
                <div class="col-xl-6">
                    <div class="card custom-card">
                        <div class="card-header">
                            <div class="card-title">Basic Vector Map</div>
                        </div>
                        <div class="card-body">
                            <div id="vector-map"></div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-6">
                    <div class="card custom-card">
                        <div class="card-header">
                            <div class="card-title">Map With Markers</div>
                        </div>
                        <div class="card-body">
                            <div id="marker-map"></div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-6">
                    <div class="card custom-card">
                        <div class="card-header">
                            <div class="card-title">Map With Image Markers</div>
                        </div>
                        <div class="card-body">
                            <div id="marker-image-map"></div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-6">
                    <div class="card custom-card">
                        <div class="card-header">
                            <div class="card-title">Map With Lines</div>
                        </div>
                        <div class="card-body">
                            <div id="lines-map"></div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-6">
                    <div class="card custom-card">
                        <div class="card-header">
                            <div class="card-title">US Vector Map</div>
                        </div>
                        <div class="card-body">
                            <div id="us-map"></div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-6">
                    <div class="card custom-card">
                        <div class="card-header">
                            <div class="card-title">Russia Vector Map</div>
                        </div>
                        <div class="card-body">
                            <div id="russia-map"></div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-6">
                    <div class="card custom-card">
                        <div class="card-header">
                            <div class="card-title">Spain Vector Map</div>
                        </div>
                        <div class="card-body">
                            <div id="spain-map"></div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-6">
                    <div class="card custom-card">
                        <div class="card-header">
                            <div class="card-title">Canada Vector Map</div>
                        </div>
                        <div class="card-body">
                            <div id="canada-map"></div>
                        </div>
                    </div>
                </div>
            </div>
            <!--End::row-1 -->

        </div>
    </div>
@endsection
