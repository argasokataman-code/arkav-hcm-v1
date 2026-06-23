<?php $page = 'security-incidents'; ?>
@extends('layout.mainlayout')
@section('content')
<div class="page-wrapper">
    <div class="content" data-si-page="1">

        {{-- Breadcrumb --}}
        <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
            <div class="my-auto mb-2">
                <h2 class="mb-1">Insiden Keamanan Data</h2>
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="{{ url('index') }}"><i class="ti ti-smart-home"></i></a></li>
                        <li class="breadcrumb-item">Kepatuhan PDP</li>
                        <li class="breadcrumb-item active" aria-current="page">Insiden Keamanan</li>
                    </ol>
                </nav>
            </div>
            <div class="d-flex my-xl-auto right-content align-items-center flex-wrap gap-2">
                <button type="button" class="btn btn-primary d-flex align-items-center"
                    data-bs-toggle="modal" data-bs-target="#siCreateModal">
                    <i class="ti ti-circle-plus me-2"></i>Laporkan Insiden
                </button>
            </div>
        </div>

        {{-- Stat Cards --}}
        <div class="row g-3 mb-3">
            <div class="col-xl-3 col-md-6">
                <div class="card mb-0 h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted mb-1 small">Total Insiden</p>
                                <h4 class="mb-0" data-si-stat="total">—</h4>
                            </div>
                            <span class="avatar avatar-md rounded-circle bg-secondary-transparent">
                                <i class="ti ti-shield-lock text-secondary fs-20"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card mb-0 h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted mb-1 small">Baru Terdeteksi</p>
                                <h4 class="mb-0" data-si-stat="detected">—</h4>
                            </div>
                            <span class="avatar avatar-md rounded-circle bg-danger-transparent">
                                <i class="ti ti-alert-triangle text-danger fs-20"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card mb-0 h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted mb-1 small">Notifikasi Terkirim</p>
                                <h4 class="mb-0" data-si-stat="notified">—</h4>
                            </div>
                            <span class="avatar avatar-md rounded-circle bg-warning-transparent">
                                <i class="ti ti-bell text-warning fs-20"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card mb-0 h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted mb-1 small">Selesai</p>
                                <h4 class="mb-0" data-si-stat="resolved">—</h4>
                            </div>
                            <span class="avatar avatar-md rounded-circle bg-success-transparent">
                                <i class="ti ti-shield-check text-success fs-20"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Info banner UU PDP --}}
        <div class="alert alert-info d-flex align-items-start mb-3">
            <i class="ti ti-info-circle me-2 fs-18 mt-1"></i>
            <div>
                <strong>Kewajiban UU PDP No. 27/2022 (Pasal 46):</strong>
                Pelanggaran data pribadi <strong>wajib dilaporkan kepada BSSN dan subjek data terdampak paling lambat 14 hari</strong> setelah insiden terdeteksi.
                Gunakan halaman ini untuk mencatat, melacak, dan mengirim notifikasi insiden keamanan.
            </div>
        </div>

        {{-- Filter --}}
        <div class="card mb-3">
            <div class="card-body">
                <div class="row g-2 align-items-end">
                    <div class="col-md-5">
                        <input class="form-control" placeholder="Cari judul / deskripsi..." data-si-filter-q>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" data-si-filter-status>
                            <option value="">Semua status</option>
                            <option value="detected">Terdeteksi</option>
                            <option value="notified">Notifikasi Terkirim</option>
                            <option value="resolved">Selesai</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="button" class="btn btn-outline-primary w-100" data-si-filter-apply>Filter</button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Incident list --}}
        <div data-si-list>
            <div class="card"><div class="card-body text-center text-muted py-4">Memuat insiden keamanan...</div></div>
        </div>

    </div>
</div>

{{-- ===== Modal: Create incident ================================================ --}}
<div class="modal fade" id="siCreateModal" tabindex="-1" aria-labelledby="siCreateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="siCreateModalLabel">
                    <i class="ti ti-shield-exclamation me-2 text-danger"></i>Laporkan Insiden Keamanan Data
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form data-si-create-form>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold">Judul Insiden <span class="text-danger">*</span></label>
                            
                                <div class="invalid-feedback">This field is required.</div><input type="text" class="form-control" name="title" required maxlength="255"
                                placeholder="Contoh: Akses tidak sah ke data karyawan bagian payroll">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Deskripsi Insiden <span class="text-danger">*</span></label>
                            
                                <div class="invalid-feedback">Please enter text.</div><textarea class="form-control" name="description" rows="4" required maxlength="10000"
                                placeholder="Jelaskan kronologi, dampak, dan langkah awal yang diambil..."></textarea>

    <div class="invalid-feedback">Please enter text.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Tanggal Terdeteksi <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="detected_at" required>

    <div class="invalid-feedback">Please select a date.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Jumlah Subjek Terdampak</label>
                            <input type="number" class="form-control" name="affected_subjects_count" min="0" value="0">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Jenis Data Terdampak</label>
                            <input type="text" class="form-control" name="affected_data_types"
                                placeholder="nama, NIK, gaji, biometrik (pisahkan dengan koma)">
                            <small class="text-muted">Pisahkan beberapa jenis data dengan koma</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Tanggal Laporan ke BSSN</label>
                            <input type="date" class="form-control" name="reported_to_bssn_at">
                            <small class="text-muted">Wajib dalam 14 hari sesuai Pasal 46 UU PDP</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="ti ti-circle-plus me-1"></i>Simpan & Laporkan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ===== Modal: Incident detail ================================================ --}}
<div class="modal fade" id="siDetailModal" tabindex="-1" aria-labelledby="siDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="siDetailModalLabel">
                    <i class="ti ti-info-circle me-2"></i>Detail Insiden
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="siDetailModalBody">
                <div class="text-center text-muted py-3">Memuat detail...</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

@endsection
