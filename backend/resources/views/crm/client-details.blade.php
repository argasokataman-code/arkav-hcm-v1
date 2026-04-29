<?php $page = 'client-details'; ?>
@extends('layout.mainlayout')
@section('content')

    <!-- Page Wrapper -->
    <div class="page-wrapper">
        <div class="content">

            @include('partials.client-details.breadcrumb')

            <div class="row">
                @include('partials.client-details.sidebar')
                <div class="col-xl-8">
                    <div>
                        @include('partials.client-details.tab-nav')
                        <div class="tab-content custom-accordion-items client-accordion">
                            <div class="tab-pane active show" id="bottom-justified-tab1" role="tabpanel">
                                <div class="accordion accordions-items-seperate" id="accordionExample">
                                    @include('partials.client-details.tab-overview-heading1')
                                    @include('partials.client-details.tab-overview-heading2a')
                                    @include('partials.client-details.tab-overview-heading2b')
                                    @include('partials.client-details.tab-overview-heading3')
                                    @include('partials.client-details.tab-overview-heading4')
                                    @include('partials.client-details.tab-overview-heading5a')
                                    @include('partials.client-details.tab-overview-heading5b')
                                </div>
                            </div>
                            @include('partials.client-details.tab-projects')
                            @include('partials.client-details.tab-tasks-a')
                            @include('partials.client-details.tab-tasks-b')
                            @include('partials.client-details.tab-invoices')
                            @include('partials.client-details.tab-notes-tab')
                            @include('partials.client-details.tab-documents-a')
                            @include('partials.client-details.tab-documents-b')
                            @include('partials.client-details.tab-documents-c')
                        </div>
                        @include('partials.client-details.tab-fab-button')
                    </div>
                </div>
            </div>
        </div>

    </div>
    <!-- /Page Wrapper -->

    @component('components.modal-popup')
    @endcomponent

@endsection
