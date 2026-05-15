<?php $page = 'saas-renewal-monitoring'; ?>
@extends('layout.mainlayout')

@section('content')
<div class="page-wrapper">
	<div class="content" data-saas-renewal-monitoring-page>
		<div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
			<div class="my-auto mb-2">
				<h2 class="mb-1">Renewal Monitoring</h2>
				<nav>
					<ol class="breadcrumb mb-0">
						<li class="breadcrumb-item">
							<a href="{{url('index')}}"><i class="ti ti-smart-home"></i></a>
						</li>
						<li class="breadcrumb-item">SaaS</li>
						<li class="breadcrumb-item active" aria-current="page">Renewal Monitoring</li>
					</ol>
				</nav>
			</div>
			<div class="d-flex gap-2">
				<button type="button" class="btn btn-outline-secondary" data-renewal-refresh>
					<i class="ti ti-refresh"></i> Refresh
				</button>
				<button type="button" class="btn btn-outline-secondary" data-renewal-reset>
					<i class="ti ti-redo"></i> Reset
				</button>
			</div>
		</div>

		<div class="row g-3 mb-3">
			<div class="col-sm-6 col-xl-2">
				<div class="card mb-0 h-100"><div class="card-body">
					<div class="text-muted small text-uppercase mb-2">Total</div>
					<div class="h4 mb-0" data-renewal-summary-total>0</div>
				</div></div>
			</div>
			<div class="col-sm-6 col-xl-2">
				<div class="card mb-0 h-100"><div class="card-body">
					<div class="text-muted small text-uppercase mb-2">Paid</div>
					<div class="h4 mb-0 text-success" data-renewal-summary-paid>0</div>
				</div></div>
			</div>
			<div class="col-sm-6 col-xl-2">
				<div class="card mb-0 h-100"><div class="card-body">
					<div class="text-muted small text-uppercase mb-2">Retrying</div>
					<div class="h4 mb-0 text-warning" data-renewal-summary-retrying>0</div>
				</div></div>
			</div>
			<div class="col-sm-6 col-xl-2">
				<div class="card mb-0 h-100"><div class="card-body">
					<div class="text-muted small text-uppercase mb-2">Grace</div>
					<div class="h4 mb-0 text-warning" data-renewal-summary-grace>0</div>
				</div></div>
			</div>
			<div class="col-sm-6 col-xl-2">
				<div class="card mb-0 h-100"><div class="card-body">
					<div class="text-muted small text-uppercase mb-2">Inactive</div>
					<div class="h4 mb-0 text-danger" data-renewal-summary-inactive>0</div>
				</div></div>
			</div>
			<div class="col-sm-6 col-xl-2">
				<div class="card mb-0 h-100"><div class="card-body">
					<div class="text-muted small text-uppercase mb-2">Anomali</div>
					<div class="h4 mb-0 text-danger" data-renewal-summary-anomalies>0</div>
				</div></div>
			</div>
		</div>

		<div class="card">
			<div class="card-body">
				<div class="row g-2 align-items-center">
					<div class="col-lg-3">
						<label class="form-label mb-1">Window</label>
						<select class="form-select" data-renewal-days>
							<option value="7">7 hari</option>
							<option value="30" selected>30 hari</option>
							<option value="60">60 hari</option>
							<option value="90">90 hari</option>
						</select>
					</div>
					<div class="col-lg-3">
						<label class="form-label mb-1">Status</label>
						<select class="form-select" data-renewal-status>
							<option value="">Semua status</option>
							<option value="paid">Paid</option>
							<option value="pending">Pending</option>
							<option value="failed">Failed</option>
						</select>
					</div>
					<div class="col-lg-3">
						<label class="form-label mb-1">Reason code</label>
						<input type="text" class="form-control" placeholder="XENDIT_DOWN, ..." data-renewal-reason>
					</div>
					<div class="col-lg-3">
						<label class="form-label mb-1">Company ID</label>
						<input type="number" min="1" class="form-control" placeholder="Opsional" data-renewal-company-id>
					</div>
				</div>
			</div>
		</div>

		<div class="alert alert-danger d-none" role="alert" data-renewal-error></div>

		<div class="row g-3">
			<div class="col-xl-8">
				<div class="card h-100">
					<div class="card-header d-flex justify-content-between align-items-center">
						<h5 class="mb-0">Renewal Records</h5>
						<div class="text-muted small" data-renewal-records-page-info>—</div>
					</div>
					<div class="table-responsive">
						<table class="table align-middle mb-0 table-hover">
							<thead>
								<tr>
									<th>Renewal Key</th>
									<th>Company</th>
									<th>Status</th>
									<th>Reason</th>
									<th class="text-end">Aksi</th>
								</tr>
							</thead>
							<tbody data-renewal-records-body>
								<tr><td colspan="5" class="text-center text-muted py-4">Memuat data...</td></tr>
							</tbody>
						</table>
					</div>
					<div class="card-footer d-flex align-items-center justify-content-between">
						<div class="text-muted small" data-renewal-records-pagination>—</div>
						<div class="btn-group" role="group">
							<button class="btn btn-outline-secondary btn-sm" data-renewal-prev>Sebelumnya</button>
							<button class="btn btn-outline-secondary btn-sm" data-renewal-next>Berikutnya</button>
						</div>
					</div>
				</div>
			</div>
			<div class="col-xl-4">
				<div class="card mb-3">
					<div class="card-header">
						<h5 class="mb-0">Anomali Aktif</h5>
					</div>
					<div class="card-body p-0">
						<div class="list-group list-group-flush" data-renewal-anomalies-list>
							<div class="list-group-item text-muted text-center py-4">Memuat data...</div>
						</div>
					</div>
				</div>

				<div class="card">
					<div class="card-header d-flex justify-content-between align-items-center">
						<h5 class="mb-0">Detail Record</h5>
						<span class="badge bg-secondary-subtle text-secondary" data-renewal-detail-key>Belum dipilih</span>
					</div>
					<div class="card-body" data-renewal-detail-panel>
						<div class="text-muted text-center py-4">Pilih salah satu record untuk melihat timeline renewal.</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
@endsection