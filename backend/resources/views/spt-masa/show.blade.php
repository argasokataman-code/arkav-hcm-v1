<?php $page = 'spt-masa-pph21'; ?>
@extends('layout.mainlayout')
@section('content')
<div class="page-wrapper" data-spt-masa-page data-spt-masa-screen="detail" data-spt-uuid="{{ $sptUuid }}" role="main" aria-label="Detail SPT Masa PPh 21">
    <div class="content">
        <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
            <div class="my-auto mb-2">
                <h2 class="mb-1">Detail SPT Masa <span data-spt-detail-periode class="text-muted fs-5">—</span></h2>
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="{{ url('index') }}"><i class="ti ti-smart-home"></i></a></li>
                        <li class="breadcrumb-item"><a href="{{ url('spt-masa-pph21') }}">SPT Masa PPh 21</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Detail</li>
                    </ol>
                </nav>
            </div>
            <div class="d-flex my-xl-auto right-content align-items-center flex-wrap gap-2">
                <button type="button" class="btn btn-outline-secondary btn-sm d-none" data-spt-regenerate-btn>
                    <i class="ti ti-refresh me-1"></i>Regenerate
                </button>
                <button type="button" class="btn btn-info btn-sm d-none" data-spt-markready-btn>
                    <i class="ti ti-check me-1"></i>Tandai Ready
                </button>
                <button type="button" class="btn btn-success btn-sm d-none" data-spt-submit-btn>
                    <i class="ti ti-send me-1"></i>Submit
                </button>
                <a href="javascript:void(0);" class="btn btn-light btn-sm d-none" data-spt-export-btn>
                    <i class="ti ti-file-type-csv me-1"></i>Export CSV
                </a>
                <div class="head-icons ms-2">
                    <a href="javascript:void(0);" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-original-title="Collapse" id="collapse-header">
                        <i class="ti ti-chevrons-up"></i>
                    </a>
                </div>
            </div>
        </div>

        <div class="alert alert-danger d-none" data-spt-error role="alert" aria-live="polite"></div>
        <div class="alert alert-success d-none" data-spt-success role="alert" aria-live="polite"></div>

        {{-- Summary cards --}}
        <div class="row g-3 mb-3" data-spt-summary-row>
            <div class="col-md-3 col-6">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-muted small mb-1">Status</div>
                        <div data-spt-status-badge><span class="badge bg-secondary-subtle text-secondary">—</span></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-muted small mb-1">Total Karyawan</div>
                        <h4 class="mb-0" data-spt-total-karyawan>—</h4>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-muted small mb-1">Total Bruto</div>
                        <h4 class="mb-0" data-spt-total-bruto>—</h4>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-muted small mb-1">Total PPh 21</div>
                        <h4 class="mb-0" data-spt-total-pph21>—</h4>
                    </div>
                </div>
            </div>
        </div>

        {{-- Detail table --}}
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Detail Per Karyawan</h5>
                <span class="badge bg-secondary-subtle text-secondary small" data-spt-detail-count></span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0 table-sm" role="grid" aria-label="Detail SPT Masa Per Karyawan">
                        <thead class="thead-light">
                            <tr>
                                <th scope="col">Nama</th>
                                <th scope="col">NPWP</th>
                                <th scope="col">NIK</th>
                                <th scope="col">Kategori SPT</th>
                                <th scope="col">Bukti Potong</th>
                                <th scope="col" class="text-end">Bruto</th>
                                <th scope="col" class="text-end">PPh 21</th>
                            </tr>
                        </thead>
                        <tbody data-spt-detail-body>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    <span class="spinner-border spinner-border-sm me-2" role="status"></span>Memuat data…
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Modal: Confirm Regenerate --}}
<div class="modal fade" id="sptRegenerateModal" tabindex="-1" aria-labelledby="sptRegenerateModalLabel" aria-modal="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="sptRegenerateModalLabel"><i class="ti ti-refresh me-2"></i>Regenerate SPT Masa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <i class="ti ti-alert-triangle me-1"></i>
                    Data detail saat ini akan dihapus dan dibuat ulang dari payroll yang sudah finalized.
                    Pastikan Anda sudah memverifikasi data payroll terlebih dahulu.
                </div>
                <div class="alert alert-danger d-none" data-spt-regenerate-modal-error></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-warning" data-spt-regenerate-confirm>
                    <i class="ti ti-refresh me-1"></i>Lanjutkan Regenerate
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Modal: Confirm Submit --}}
<div class="modal fade" id="sptSubmitModal" tabindex="-1" aria-labelledby="sptSubmitModalLabel" aria-modal="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="sptSubmitModalLabel"><i class="ti ti-send me-2"></i>Submit SPT Masa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Konfirmasi bahwa Anda telah memeriksa semua data dan sudah melakukan setor pajak ke DJP.</p>
                <div class="mb-3">
                    <label class="form-label">Catatan (opsional)</label>
                    <textarea class="form-control" rows="3" data-spt-submit-notes placeholder="Misal: Sudah setor via DJP Online tanggal …"></textarea>
                </div>
                <div class="alert alert-danger d-none" data-spt-submit-modal-error></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-success" data-spt-submit-confirm>
                    <i class="ti ti-send me-1"></i>Submit
                </button>
            </div>
        </div>
    </div>
</div>
@endsection
