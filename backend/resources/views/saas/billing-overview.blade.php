<?php $page = 'saas-billing-overview'; ?>
@extends('layout.mainlayout')

@section('content')
<div class="page-wrapper">
	<div class="content" data-saas-billing-overview-page>
		<div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
			<div class="my-auto mb-2">
				<h2 class="mb-1">Trial & Billing Overview</h2>
				<nav>
					<ol class="breadcrumb mb-0">
						<li class="breadcrumb-item">
							<a href="{{url('index')}}"><i class="ti ti-smart-home"></i></a>
						</li>
						<li class="breadcrumb-item">SaaS</li>
						<li class="breadcrumb-item active" aria-current="page">Trial & Billing Overview</li>
					</ol>
				</nav>
			</div>
		</div>

		<div class="card">
			<div class="card-body">
				<div class="row g-2 align-items-center">
					<div class="col-lg-4">
						<input type="text" class="form-control" placeholder="Cari company (nama/kode)..." data-billing-search>
					</div>
					<div class="col-lg-4">
						<input type="hidden" value="subscribed" data-billing-tab>
						<div class="nav nav-pills gap-2" role="tablist" aria-label="Billing tab">
							<button type="button" class="btn btn-outline-secondary active" data-billing-tab-button data-tab-value="subscribed">Subscribed</button>
							<button type="button" class="btn btn-outline-secondary" data-billing-tab-button data-tab-value="trial">Trial</button>
						</div>
					</div>
					<div class="col-lg-2">
						<select class="form-select" data-billing-per-page>
							<option value="15">15 / page</option>
							<option value="30">30 / page</option>
							<option value="50">50 / page</option>
							<option value="100">100 / page</option>
						</select>
					</div>
					<div class="col-6 col-lg-1">
						<button class="btn btn-outline-secondary w-100" data-billing-refresh>
							<i class="ti ti-refresh"></i> Refresh
						</button>
					</div>
					<div class="col-6 col-lg-1">
						<button class="btn btn-outline-secondary w-100" data-billing-reset>
							<i class="ti ti-redo"></i> Reset
						</button>
					</div>
				</div>
				<div class="alert alert-light border mb-0 mt-3 small" role="status" data-billing-legend>
					<div class="fw-semibold mb-1">Panduan baca status:</div>
					<div class="text-muted">Subscription menunjukkan fase tenant saat ini, Latest invoice menunjukkan status tagihan terbaru, dan Email menunjukkan status kirim invoice terakhir.</div>
				</div>
			</div>
		</div>

		<div class="alert alert-danger d-none" role="alert" data-billing-error></div>

		<div class="card">
			<div class="d-none d-lg-block">
				<div class="table-responsive">
					<table class="table align-middle mb-0 table-hover">
						<thead>
							<tr>
								<th>Company</th>
								<th>Subscription</th>
								<th>Invoice Terbaru</th>
								<th>Email</th>
								<th class="text-end">Aksi</th>
							</tr>
						</thead>
						<tbody data-billing-tbody>
							<tr><td colspan="5" class="text-center text-muted py-4">Memuat data...</td></tr>
						</tbody>
					</table>
				</div>
			</div>
			<div class="d-lg-none" data-billing-mobile-list>
				<div class="text-center text-muted py-4">Memuat data...</div>
			</div>
			<div class="card-footer d-flex align-items-center justify-content-between">
				<div class="text-muted small" data-billing-pagination-info>—</div>
				<div class="btn-group" role="group" aria-label="Pagination">
					<button class="btn btn-outline-secondary btn-sm" data-billing-prev>Sebelumnya</button>
					<button class="btn btn-outline-secondary btn-sm" data-billing-next>Berikutnya</button>
				</div>
			</div>
		</div>

	</div>
</div>
@endsection

