<?php $page = 'payroll-run-history'; ?>
@extends('layout.mainlayout')
@section('content')
<div class="page-wrapper">
    <div class="content" data-payroll-run-history-panel>
        <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
            <div class="my-auto mb-2">
                <h2 class="mb-1">Payroll - History Monthly Run</h2>
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="{{ url('index') }}"><i class="ti ti-smart-home"></i></a></li>
                        <li class="breadcrumb-item">HR</li>
                        <li class="breadcrumb-item">Payroll</li>
                        <li class="breadcrumb-item active" aria-current="page">History Monthly Run</li>
                    </ol>
                </nav>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">Tahun</label>
                        <input type="number" class="form-control" min="2000" max="2100" value="{{ date('Y') }}" data-payroll-history-year>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Bulan</label>
                        <select class="form-select" data-payroll-history-month>
                            <option value="">Semua bulan</option>
                            @for ($m = 1; $m <= 12; $m++)
                                <option value="{{ $m }}" @selected((int) date('n') === $m)>{{ $m }}</option>
                            @endfor
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" data-payroll-history-status>
                            <option value="">Semua</option>
                            <option value="draft">Draft</option>
                            <option value="finalized">Finalized</option>
                            <option value="void">Void</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="button" class="btn btn-primary w-100" data-payroll-history-refresh>Refresh</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Riwayat run payroll bulanan</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>Run / periode</th>
                                <th>Status</th>
                                <th>Pembayaran</th>
                                <th>Karyawan</th>
                                <th class="text-end">Total net pay</th>
                                <th>Audit trace</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody data-payroll-history-body>
                            <tr><td colspan="7" class="text-center text-muted py-4">Memuat...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer d-flex justify-content-between align-items-center" data-payroll-history-pagination style="display:none;">
                <span class="text-muted small" data-payroll-history-page-info></span>
                <div class="btn-group">
                    <button type="button" class="btn btn-sm btn-light border" data-payroll-history-prev>Sebelumnya</button>
                    <button type="button" class="btn btn-sm btn-light border" data-payroll-history-next>Berikutnya</button>
                </div>
            </div>
        </div>

        <div class="modal fade" id="payroll_history_detail_modal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Detail History Monthly Payroll</h5>
                        <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                            <i class="ti ti-x"></i>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div data-payroll-history-detail class="small text-muted">Pilih run untuk melihat detail.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
