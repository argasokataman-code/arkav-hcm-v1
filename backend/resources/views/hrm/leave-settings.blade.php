<?php $page = 'leave-settings'; ?>
@extends('layout.mainlayout')
@section('content')

    <!-- Page Wrapper -->
    <div class="page-wrapper">
        <div class="content">

            <!-- Breadcrumb -->
            <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
                <div class="my-auto mb-2">
                    <h2 class="mb-1">Leave Settings</h2>
                    <nav>
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item">
                                <a href="{{url('index')}}"><i class="ti ti-smart-home"></i></a>
                            </li>
                            <li class="breadcrumb-item">
                                Employee
                            </li>
                            <li class="breadcrumb-item active" aria-current="page">Leave Settings</li>
                        </ol>
                    </nav>
                </div>
                <div class="d-flex my-xl-auto right-content align-items-center flex-wrap ">
                    <div class="mb-2">
                        <button type="button" class="btn btn-primary d-flex align-items-center" onclick="window.arcavOpenCustomForm ? window.arcavOpenCustomForm() : alert('Module not loaded. Refresh page.')">
                            <i class="ti ti-circle-plus me-2"></i>Add Custom Policy
                        </button>
                    </div>
                    <div class="head-icons ms-2">
                        <a href="javascript:void(0);" class="" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-original-title="Collapse" id="collapse-header">
                            <i class="ti ti-chevrons-up"></i>
                        </a>
                    </div>
                </div>
            </div>
            <!-- /Breadcrumb -->

            <div class="row" data-hcm-leave-types-grid>
                <div class="col-12 text-muted small py-3">Memuat pengaturan cuti…</div>
            </div>

        </div>
	
    </div>
    <!-- /Page Wrapper -->

    @include('hcm.partials.leave-settings-modals')

    @component('components.modal-popup')
    @endcomponent

@endsection
