<?php $page = 'notes'; ?>
@extends('layout.mainlayout')
@section('content')

    <!-- Page wrapper -->
    <div class="page-wrapper">
        <div class="content pb-4">

            @include('partials.notes.breadcrumb')

            <div class="row">
                @include('partials.notes.sidebar')
                <div class="col-xl-9 budget-role-notes">
                    <div
                        class="bg-white rounded-3 d-flex align-items-center justify-content-between flex-wrap mb-4 p-3 pb-0">
                        <div class="d-flex align-items-center mb-3">
                            <div class="me-3">
                                <select class="select">
                                    <option>Bulk Actions</option>
                                    <option>Delete Marked</option>
                                    <option>Unmark All</option>
                                    <option>Mark All</option>
                                </select>
                            </div>
                            <a href="#" class="btn btn-light">Apply</a>
                        </div>
                        <div class="form-sort mb-3">
                            <i class="ti ti-filter feather-filter info-img"></i>
                            <select class="select">
                                <option>Recent</option>
                                <option>Last Modified</option>
                                <option>Last Modified by me</option>
                            </select>
                        </div>
                    </div>
                    <div class="tab-content" id="v-pills-tabContent2">
                        @include('partials.notes.tab-all-notes-a')
                        @include('partials.notes.tab-all-notes-b')
                        @include('partials.notes.tab-all-notes-c')
                        @include('partials.notes.tab-all-notes-d')
                        @include('partials.notes.tab-important-a')
                        @include('partials.notes.tab-important-b')
                        @include('partials.notes.tab-trash-a')
                        @include('partials.notes.tab-trash-b')
                    </div>
                </div>
            </div>
        </div>

    </div>
    <!-- /Page wrapper -->

    @component('components.modal-popup')
    @endcomponent

@endsection
