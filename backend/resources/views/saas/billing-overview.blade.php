<?php $page = 'saas-billing-overview'; ?>
@extends('layout.mainlayout')

@section('content')
<div class="page-wrapper">
	<div class="content" data-saas-billing-overview-page>
		<div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
			<div class="my-auto mb-2">
				<h2 class="mb-1">Trial & Billing Dashboard</h2>
				<nav>
					<ol class="breadcrumb mb-0">
						<li class="breadcrumb-item">
							<a href="{{url('index')}}"><i class="ti ti-smart-home"></i></a>
						</li>
						<li class="breadcrumb-item">SaaS</li>
						<li class="breadcrumb-item active" aria-current="page">Billing Overview</li>
					</ol>
				</nav>
			</div>
		</div>

		<div class="card">
			<div class="card-body">
				<div class="row g-2 align-items-center">
					<div class="col-md-5">
						<input type="text" class="form-control" placeholder="Search company name/code..." data-billing-search>
					</div>
					<div class="col-md-3">
						<select class="form-select" data-billing-tab>
							<option value="trial">Trial</option>
							<option value="subscribed">Subscribed (Active/Pending)</option>
						</select>
					</div>
					<div class="col-md-2">
						<select class="form-select" data-billing-per-page>
							<option value="15">15 / page</option>
							<option value="30">30 / page</option>
							<option value="50">50 / page</option>
							<option value="100">100 / page</option>
						</select>
					</div>
					<div class="col-md-2">
						<button class="btn btn-outline-secondary w-100" data-billing-refresh>
							<i class="ti ti-refresh"></i> Refresh
						</button>
					</div>
				</div>
			</div>
		</div>

		<div class="alert alert-danger d-none" role="alert" data-billing-error></div>

		<div class="card">
			<div class="table-responsive">
				<table class="table table-nowrap mb-0">
					<thead>
						<tr>
							<th>Company</th>
							<th>Subscription</th>
							<th>Latest invoice</th>
							<th>Email</th>
							<th class="text-end">Actions</th>
						</tr>
					</thead>
					<tbody data-billing-tbody>
						<tr><td colspan="5" class="text-center text-muted py-4">Loading...</td></tr>
					</tbody>
				</table>
			</div>
			<div class="card-footer d-flex align-items-center justify-content-between">
				<div class="text-muted small" data-billing-pagination-info>—</div>
				<div class="btn-group" role="group" aria-label="Pagination">
					<button class="btn btn-outline-secondary btn-sm" data-billing-prev>Prev</button>
					<button class="btn btn-outline-secondary btn-sm" data-billing-next>Next</button>
				</div>
			</div>
		</div>

	</div>
</div>
@endsection

