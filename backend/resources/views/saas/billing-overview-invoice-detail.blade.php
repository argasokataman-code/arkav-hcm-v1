<?php $page = 'saas-billing-overview'; ?>
@extends('layout.mainlayout')

@section('content')
<div class="page-wrapper">
	<div class="content" data-saas-billing-invoice-detail-page data-invoice-uuid="{{ $invoice->uuid }}">
		<div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
			<div class="my-auto mb-2">
				<h2 class="mb-1" data-billing-detail-title>Detail Invoice</h2>
				<nav>
					<ol class="breadcrumb mb-0">
						<li class="breadcrumb-item">
							<a href="{{url('index')}}"><i class="ti ti-smart-home"></i></a>
						</li>
						<li class="breadcrumb-item">SaaS</li>
						<li class="breadcrumb-item"><a href="{{ route('saas.billing-overview') }}">Billing Overview</a></li>
						<li class="breadcrumb-item active" aria-current="page">Detail Invoice</li>
					</ol>
				</nav>
			</div>
			<div class="d-flex gap-2">
				<a href="{{ route('saas.billing-overview') }}" class="btn btn-outline-secondary">
					<i class="ti ti-arrow-left"></i> Kembali ke overview
				</a>
				<button type="button" class="btn btn-primary" data-billing-detail-resend>
					<i class="ti ti-mail-forward"></i> Resend email
				</button>
			</div>
		</div>

		<div class="alert alert-danger d-none" role="alert" data-billing-detail-error></div>
		<div data-billing-detail-state-badges class="mb-3"></div>

		<div class="row g-3 mb-3">
			<div class="col-md-6 col-xl-3">
				<div class="card mb-0 h-100">
					<div class="card-body">
						<div class="text-muted small text-uppercase mb-2">Company</div>
						<div class="fw-semibold" data-billing-detail-company-name>—</div>
						<div class="text-muted small" data-billing-detail-company-code>—</div>
					</div>
				</div>
			</div>
			<div class="col-md-6 col-xl-3">
				<div class="card mb-0 h-100">
					<div class="card-body">
						<div class="text-muted small text-uppercase mb-2">Subscription</div>
						<div class="fw-semibold" data-billing-detail-subscription-status>—</div>
						<div class="text-muted small" data-billing-detail-subscription-plan>—</div>
						<div class="text-muted small" data-billing-detail-subscription-period>—</div>
					</div>
				</div>
			</div>
			<div class="col-md-6 col-xl-3">
				<div class="card mb-0 h-100">
					<div class="card-body">
						<div class="text-muted small text-uppercase mb-2">Invoice</div>
						<div class="fw-semibold" data-billing-detail-invoice-number>—</div>
						<div class="text-muted small" data-billing-detail-invoice-status>—</div>
						<div class="text-muted small">Jatuh tempo: <span data-billing-detail-invoice-due-date>—</span></div>
						<div class="text-muted small">Jumlah: <span data-billing-detail-invoice-amount>—</span></div>
					</div>
				</div>
			</div>
			<div class="col-md-6 col-xl-3">
				<div class="card mb-0 h-100">
					<div class="card-body">
						<div class="text-muted small text-uppercase mb-2">Email Terakhir</div>
						<div class="fw-semibold" data-billing-detail-latest-email-status>—</div>
						<div class="text-muted small" data-billing-detail-latest-email-target>—</div>
						<div class="text-muted small" data-billing-detail-latest-email-sent-at>—</div>
						<div class="text-muted small text-danger" data-billing-detail-latest-email-error>—</div>
					</div>
				</div>
			</div>
		</div>

		<div class="card">
			<div class="card-header">
				<h5 class="mb-0">Riwayat Email Invoice</h5>
			</div>
			<div class="table-responsive">
				<table class="table table-nowrap mb-0">
					<thead>
						<tr>
							<th>Tujuan Email</th>
							<th>Status</th>
							<th>Waktu</th>
							<th>Error</th>
						</tr>
					</thead>
					<tbody data-billing-email-history-body>
						<tr><td colspan="4" class="text-center text-muted py-4">Loading...</td></tr>
					</tbody>
				</table>
			</div>
		</div>
	</div>
</div>
@endsection