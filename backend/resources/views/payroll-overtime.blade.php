<?php $page = 'payroll-overtime'; ?>
@extends('layout.mainlayout')
@section('content')

    <!-- Page Wrapper -->
	<div class="page-wrapper">
		<div class="content">

			<!-- Breadcrumb -->
			<div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
				<div class="my-auto mb-2">
					<h2 class="mb-1">Payroll — Lembur</h2>
					<nav>
						<ol class="breadcrumb mb-0">
							<li class="breadcrumb-item">
								<a href="{{url('index')}}"><i class="ti ti-smart-home"></i></a>
							</li>
							<li class="breadcrumb-item">
								HR
							</li>
							<li class="breadcrumb-item active" aria-current="page">Payroll / Lembur</li>
						</ol>
					</nav>
				</div>
				<div class="d-flex my-xl-auto right-content align-items-center flex-wrap ">
					<div class="mb-2">
						<div class="dropdown">
							<a href="javascript:void(0);" class="dropdown-toggle btn btn-white d-inline-flex align-items-center" data-bs-toggle="dropdown">
								<i class="ti ti-file-export me-1"></i>Export
							</a>
							<ul class="dropdown-menu  dropdown-menu-end p-3">
								<li>
									<a href="javascript:void(0);" class="dropdown-item rounded-1"><i class="ti ti-file-type-pdf me-1"></i>Export as PDF</a>
								</li>
								<li>
									<a href="javascript:void(0);" class="dropdown-item rounded-1"><i class="ti ti-file-type-xls me-1"></i>Export as Excel </a>
								</li>
							</ul>
						</div>
					</div>
					<div class="head-icons ms-2">
						<a href="javascript:void(0);" class="" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-original-title="Collapse" id="collapse-header">
							<i class="ti ti-chevrons-up"></i>
						</a>
					</div>
				</div>
			</div>
			<div class="d-flex flex-wrap gy-2 justify-content-between my-4">
				@include('hcm.partials.payroll-section-tabs', ['payrollTab' => 'overtime'])
				<div class="mb-2 d-flex flex-wrap gap-2">
					<a href="{{ url('overtime-master') }}" class="btn btn-white border d-inline-flex align-items-center"><i class="ti ti-settings me-2"></i>Master tipe lembur</a>
					<a href="{{ url('overtime') }}" class="btn btn-primary d-inline-flex align-items-center"><i class="ti ti-circle-plus me-2"></i>Kelola pengajuan lembur</a>
				</div>
			</div>

			<div class="alert alert-light border mb-4" role="status">
				<strong>Integrasi tanggal dengan absensi admin:</strong> pilih tanggal kerja yang sama dengan
				<code>Attendance Admin</code> (query <code>?date=</code>). Tabel di bawah memuat pengajuan lembur untuk tanggal tersebut dari
				<code>GET /v1/hcm/overtime-requests</code>.
			</div>

			<div class="card mb-4">
				<div class="card-body d-flex flex-wrap align-items-end gap-3">
					<div>
						<label class="form-label mb-1">Tanggal kerja (work date)</label>
						<input type="date" class="form-control" data-payroll-overtime-date style="min-width: 11rem;">
					</div>
					<div>
						<label class="form-label mb-1">Status</label>
						<select class="form-select" data-payroll-overtime-status style="min-width: 10rem;">
							<option value="">Semua</option>
							<option value="pending">Pending</option>
							<option value="approved">Approved</option>
							<option value="declined">Declined</option>
						</select>
					</div>
					<div class="ms-md-auto d-flex flex-wrap gap-2">
						<a href="{{ url('attendance-admin') }}" class="btn btn-white border d-inline-flex align-items-center" data-payroll-overtime-attendance-link>
							<i class="ti ti-calendar me-2"></i>Buka absensi admin
						</a>
					</div>
				</div>
			</div>

			<div class="row mb-4" data-payroll-overtime-summary-row style="display: none;">
				<div class="col-6 col-md-3 mb-3 mb-md-0">
					<div class="card p-3 mb-0">
						<p class="fs-12 text-muted mb-1">Karyawan (unik)</p>
						<h4 class="mb-0" data-payroll-overtime-summary-users>0</h4>
					</div>
				</div>
				<div class="col-6 col-md-3 mb-3 mb-md-0">
					<div class="card p-3 mb-0">
						<p class="fs-12 text-muted mb-1">Pending</p>
						<h4 class="mb-0" data-payroll-overtime-summary-pending>0</h4>
					</div>
				</div>
				<div class="col-6 col-md-3 mb-3 mb-md-0">
					<div class="card p-3 mb-0">
						<p class="fs-12 text-muted mb-1">Ditolak</p>
						<h4 class="mb-0" data-payroll-overtime-summary-declined>0</h4>
					</div>
				</div>
				<div class="col-6 col-md-3">
					<div class="card p-3 mb-0">
						<p class="fs-12 text-muted mb-1">Menit disetujui</p>
						<h4 class="mb-0" data-payroll-overtime-summary-minutes>0</h4>
					</div>
				</div>
			</div>

			<div class="card mb-4">
				<div class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3">
					<h5 class="mb-0">Pengajuan lembur (filter tanggal)</h5>
					<span class="text-muted small">Sumber: <code>GET /v1/hcm/overtime-requests?workDate=…</code></span>
				</div>
				<div class="card-body p-0">
					<div class="table-responsive">
						<table class="table mb-0">
							<thead class="thead-light">
								<tr>
									<th>Karyawan</th>
									<th>Tanggal</th>
									<th>Menit</th>
									<th>Tipe</th>
									<th>Status</th>
									<th>Komponen lembur</th>
								</tr>
							</thead>
							<tbody data-payroll-overtime-requests-body>
								<tr>
									<td colspan="6" class="text-center text-muted py-4">Memuat…</td>
								</tr>
							</tbody>
						</table>
					</div>
					<div class="card-footer d-flex flex-wrap align-items-center justify-content-between gap-2" data-payroll-overtime-pagination style="display: none;">
						<span class="text-muted small" data-payroll-overtime-page-info></span>
						<div class="btn-group">
							<button type="button" class="btn btn-white border btn-sm" data-payroll-overtime-prev>Sebelumnya</button>
							<button type="button" class="btn btn-white border btn-sm" data-payroll-overtime-next>Berikutnya</button>
						</div>
					</div>
				</div>
			</div>

			<div class="card">
				<div class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3">
					<h5 class="mb-0">Master tipe lembur (referensi)</h5>
					<span class="text-muted small">Sumber: <code>GET /v1/hcm/overtime-types</code></span>
				</div>
				<div class="card-body p-0">
					<div class="table-responsive">
						<table class="table mb-0">
							<thead class="thead-light">
								<tr>
									<th>Nama / kode</th>
									<th>Pengali</th>
									<th>Status</th>
								</tr>
							</thead>
							<tbody data-payroll-overtime-types-body>
								<tr>
									<td colspan="3" class="text-center text-muted py-4">Memuat…</td>
								</tr>
							</tbody>
						</table>
					</div>
				</div>
			</div>

		</div>

	</div>
	<!-- /Page Wrapper -->

    @component('components.modal-popup')
    @endcomponent
@endsection
