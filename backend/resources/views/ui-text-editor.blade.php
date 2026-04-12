<?php $page = 'ui-text-editor'; ?>
@extends('layout.mainlayout')
@section('content')
<div class="page-wrapper cardhead">
    <div class="content ">

        <!-- Page Header -->
        <div class="page-header">
            <div class="row">
                <div class="col-sm-12">
                    <h3 class="page-title">Text Editor</h3>
                </div>
            </div>
        </div>
        <!-- /Page Header -->

        <div class="row">

            <!-- Editor -->
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Editor</h5>
                    </div>
                    <div class="card-body">
                        <div class="summernote"></div>
                    </div>
                </div>
            </div>
            <!-- /Editor -->

        </div>
    </div>

    
    
</div>
@endsection
