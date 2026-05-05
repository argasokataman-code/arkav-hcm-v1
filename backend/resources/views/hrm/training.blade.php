<?php $page = 'training'; ?>
@extends('layout.mainlayout')
@section('content')

    <!-- Page Wrapper -->
    <div class="page-wrapper">
        <div class="content">

            <!-- Breadcrumb -->
            <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
                <div class="my-auto mb-2">
                    <h2 class="mb-1">Training</h2>
                    <nav>
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item">
                                <a href="{{url('index')}}"><i class="ti ti-smart-home"></i></a>
                            </li>
                            <li class="breadcrumb-item">
                                Performance
                            </li>
                            <li class="breadcrumb-item active" aria-current="page">Training</li>
                        </ol>
                    </nav>
                </div>
                <div class="d-flex my-xl-auto right-content align-items-center flex-wrap ">
                    <div class="mb-2">
                        <button type="button" class="btn btn-primary d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#arcav_training_modal">
                            <i class="ti ti-circle-plus me-2"></i>Tambah Training
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

            <!-- Training list -->
            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3">
                    <div>
                        <h5 class="mb-1">Daftar Training</h5>
                        <div class="text-muted fs-12">Pantau jadwal dan peserta training tim Anda.</div>
                    </div>
                    <div class="d-flex my-xl-auto right-content align-items-center flex-wrap row-gap-2">
                        <select class="form-select me-2" style="min-width: 180px" data-arcav-training-type-filter>
                            <option value="">Semua Jenis Training</option>
                        </select>
                        <select class="form-select me-2" style="min-width: 160px" data-arcav-training-status-filter>
                            <option value="">Semua Status</option>
                            <option value="active">Aktif</option>
                            <option value="inactive">Nonaktif</option>
                            <option value="completed">Selesai</option>
                        </select>
                        <div class="input-icon-start position-relative me-2" style="min-width: 260px;">
                            <span class="input-icon-addon" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="11" cy="11" r="8"></circle>
                                    <path d="m21 21-4.3-4.3"></path>
                                </svg>
                            </span>
                            <input type="text" class="form-control" placeholder="Cari trainer atau deskripsi" data-arcav-training-q>
                        </div>
                        <button type="button" class="btn btn-white" data-arcav-training-reload>
                            <i class="ti ti-refresh me-1"></i>Muat Ulang
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>Jenis Training</th>
                                    <th>Trainer</th>
                                    <th>Peserta</th>
                                    <th>Periode</th>
                                    <th>Deskripsi</th>
                                    <th>Biaya</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody data-arcav-trainings-tbody>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">Memuat data training...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <!-- /Training list -->

        </div>

    </div>
    <!-- /Page Wrapper -->

    @include('hcm.partials.training-modals')

@endsection