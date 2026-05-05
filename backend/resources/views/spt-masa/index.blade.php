<?php $page = 'spt-masa-pph21'; ?>
@extends('layout.mainlayout')
@section('content')
<div class="page-wrapper" data-spt-masa-page data-spt-masa-screen="list" role="main" aria-label="SPT Masa PPh 21">
    <div class="content">
        <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
            <div class="my-auto mb-2">
                <h2 class="mb-1">SPT Masa PPh 21</h2>
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="{{ url('index') }}"><i class="ti ti-smart-home"></i></a></li>
                        <li class="breadcrumb-item">Payroll</li>
                        <li class="breadcrumb-item active" aria-current="page">SPT Masa PPh 21</li>
                    </ol>
                </nav>
            </div>
            <div class="d-flex my-xl-auto right-content align-items-center flex-wrap">
                <div class="mb-2">
                    <button type="button" class="btn btn-primary d-inline-flex align-items-center" data-spt-generate-btn>
                        <i class="ti ti-circle-plus me-1"></i>Generate SPT Masa
                    </button>
                </div>
                <div class="head-icons ms-2">
                    <a href="javascript:void(0);" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-original-title="Collapse" id="collapse-header">
                        <i class="ti ti-chevrons-up"></i>
                    </a>
                </div>
            </div>
        </div>

        <div class="alert alert-danger d-none" data-spt-error role="alert" aria-live="polite"></div>
        <div class="alert alert-success d-none" data-spt-success role="alert" aria-live="polite"></div>

        {{-- Filter bar --}}
        <div class="card mb-3">
            <div class="card-body py-2">
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <div>
                        <label class="form-label small mb-0 me-1">Periode</label>
                        <input type="month" class="form-control form-control-sm" style="width:160px;" data-spt-filter-periode>
                    </div>
                    <div>
                        <label class="form-label small mb-0 me-1">Status</label>
                        <select class="form-select form-select-sm" style="width:130px;" data-spt-filter-status>
                            <option value="">Semua</option>
                            <option value="draft">Draft</option>
                            <option value="ready">Ready</option>
                            <option value="submitted">Submitted</option>
                        </select>
                    </div>
                    <button type="button" class="btn btn-sm btn-secondary" data-spt-filter-apply>
                        <i class="ti ti-search me-1"></i>Filter
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-spt-filter-reset>Reset</button>
                </div>
            </div>
        </div>

        {{-- List table --}}
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0" role="grid" aria-label="Daftar SPT Masa PPh 21">
                        <thead class="thead-light">
                            <tr>
                                <th scope="col">Periode</th>
                                <th scope="col">Status</th>
                                <th scope="col">Total Karyawan</th>
                                <th scope="col">Total Bruto</th>
                                <th scope="col">Total PPh 21</th>
                                <th scope="col">Generated At</th>
                                <th scope="col">Aksi</th>
                            </tr>
                        </thead>
                        <tbody data-spt-list-body>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    <span class="spinner-border spinner-border-sm me-2" role="status"></span>Memuat data…
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer d-flex justify-content-between align-items-center" data-spt-pagination-bar>
                <span class="small text-muted" data-spt-pagination-info></span>
                <div class="d-flex gap-2" data-spt-pagination-controls></div>
            </div>
        </div>
    </div>
</div>

{{-- Modal: Generate SPT Masa --}}
<div class="modal fade" id="sptGenerateModal" tabindex="-1" aria-labelledby="sptGenerateModalLabel" aria-modal="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="sptGenerateModalLabel"><i class="ti ti-file-invoice me-2"></i>Generate SPT Masa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger d-none" data-spt-modal-error></div>
                <div class="mb-3">
                    <label class="form-label">Periode <span class="text-danger">*</span></label>
                    <input type="month" class="form-control" data-spt-modal-periode required>
                    <div class="form-text">Format: YYYY-MM. Pastikan payroll periode ini sudah finalized.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary" data-spt-modal-generate-confirm>
                    <i class="ti ti-circle-plus me-1"></i>Generate
                </button>
            </div>
        </div>
    </div>
</div>
@endsection
