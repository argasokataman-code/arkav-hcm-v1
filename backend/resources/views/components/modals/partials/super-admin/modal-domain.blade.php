@if (Route::is(['domain']))
    <!-- Domain Details -->
		<div class="modal fade" id="domain_approved">
			<div class="modal-dialog modal-dialog-centered modal-md">
				<div class="modal-content">
					<div class="modal-header">
						<h4 class="modal-title d-flex align-items-center">
							Domain Detail
							<span class="badge bg-outline-success d-inline-flex align-items-center badge-xs ms-2">
								<i class="ti ti-point-filled"></i>Approved
							</span>
						</h4>
						<button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
							<i class="ti ti-x"></i>
						</button>
					</div>
					<form action="{{url('domain')}}">
						<div class="modal-body pb-0">
							<div class="row">
								<div class="col-md-12">
									<div class="mb-3">
										<div class="p-3 mb-3 br-5 bg-transparent-light">
											<div class="row">
												<div class="col-md-12">
													<div class="d-flex align-items-center file-name-icon">
														<a href="#" class="avatar avatar-md border avatar-rounded">
															<img src="{{ URL::asset('build/img/company/company-01.svg') }}" class="img-fluid" alt="img">
														</a>
														<div class="ms-2">
															<h6 class="fw-medium fs-14"><a href="#">BrightWave Innovations</a></h6>
														</div>
													</div>
												</div>
											</div>
										</div>
									</div>	
								</div>
								<div class="col-md-4">
									<div class="mb-3">
										<span class="fs-12">Plan Name</span>
										<h6 class="fw-normal">Advanced</h6>
									</div>
								</div>
								<div class="col-md-4">
									<div class="mb-3">
										<span class="fs-12">Plan Type</span>
										<h6 class="fw-normal">Monthly</h6>
									</div>
								</div>
								<div class="col-md-4">
									<div class="mb-3">
										<span class="fs-12">Account URL</span>
										<h6 class="fw-normal">bwi.example.com</h6>
									</div>
								</div>
								<div class="col-md-4">
									<div class="mb-3">
										<span class="fs-12">Price</span>
										<h6 class="fw-normal">200</h6>
									</div>
								</div>
								<div class="col-md-4">
									<div class="mb-3">
										<span class="fs-12">Register Date</span>
										<h6 class="fw-normal">12/09/2024</h6>
									</div>
								</div>
								<div class="col-md-4">
									<div class="mb-3">
										<span class="fs-12">Expiring On</span>
										<h6 class="fw-normal">11/10/2024</h6>
									</div>
								</div>
							</div>
						</div>
					</form>
				</div>
			</div>
		</div>
		<!-- /Domain Details -->
		 
		<!-- Domain Details -->
		<div class="modal fade" id="domain_pending">
			<div class="modal-dialog modal-dialog-centered modal-md">
				<div class="modal-content">
					<div class="modal-header">
						<h4 class="modal-title d-flex align-items-center">
							Domain Detail
							<span class="badge bg-outline-skyblue d-inline-flex align-items-center badge-xs ms-2">
								<i class="ti ti-point-filled"></i>Pending
							</span>
						</h4>
						<button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
							<i class="ti ti-x"></i>
						</button>
					</div>
					<form action="{{url('domain')}}">
						<div class="modal-body pb-0">
							<div class="row">
								<div class="col-md-12">
									<div class="mb-3">
										<div class="p-3 mb-3 br-5 bg-transparent-light">
											<div class="row">
												<div class="col-md-6">
													<div class="d-flex align-items-center file-name-icon">
														<a href="#" class="avatar avatar-md border avatar-rounded">
															<img src="{{ URL::asset('build/img/company/company-01.svg') }}" class="img-fluid" alt="img">
														</a>
														<div class="ms-2">
															<h6 class="fw-medium fs-14"><a href="#">BrightWave Innovations</a></h6>															
														</div>
													</div>
												</div>
												<div class="col-md-6 text-end">
													<span class="badge badge-success d-inline-flex align-items-center badge-xs ms-2">
														<i class="ti ti-check me-1"></i>Approve
													</span>
													<span class="badge badge-danger d-inline-flex align-items-center badge-xs ms-2">
														<i class="ti ti-x me-1"></i>Reject
													</span>
												</div>
											</div>
										</div>
									</div>	
								</div>
								<div class="col-md-4">
									<div class="mb-3">
										<span class="fs-12">Plan Name</span>
										<h6 class="fw-normal">Advanced</h6>
									</div>
								</div>
								<div class="col-md-4">
									<div class="mb-3">
										<span class="fs-12">Plan Type</span>
										<h6 class="fw-normal">Monthly</h6>
									</div>
								</div>
								<div class="col-md-4">
									<div class="mb-3">
										<span class="fs-12">Account URL</span>
										<h6 class="fw-normal">bwi.example.com</h6>
									</div>
								</div>
								<div class="col-md-4">
									<div class="mb-3">
										<span class="fs-12">Price</span>
										<h6 class="fw-normal">200</h6>
									</div>
								</div>
								<div class="col-md-4">
									<div class="mb-3">
										<span class="fs-12">Register Date</span>
										<h6 class="fw-normal">12/09/2024</h6>
									</div>
								</div>
								<div class="col-md-4">
									<div class="mb-3">
										<span class="fs-12">Expiring On</span>
										<h6 class="fw-normal">11/10/2024</h6>
									</div>
								</div>
							</div>
						</div>
					</form>
				</div>
			</div>
		</div>
		<!-- /Domain Details -->
		 
		<!-- Domain Details -->
		<div class="modal fade" id="domain_rejected">
			<div class="modal-dialog modal-dialog-centered modal-md">
				<div class="modal-content">
					<div class="modal-header">
						<h4 class="modal-title d-flex align-items-center">Domain Detail
							<span class="badge bg-outline-danger d-inline-flex align-items-center badge-xs ms-2">
							<i class="ti ti-point-filled"></i>Rejected
						</span></h4>
						<button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
							<i class="ti ti-x"></i>
						</button>
					</div>
					<form action="{{url('domain')}}">
						<div class="modal-body pb-0">
							<div class="row">
								<div class="col-md-12">
									<div class="mb-3">
										<div class="p-3 mb-3 br-5 bg-transparent-light">
											<div class="row">
												<div class="col-md-12">
													<div class="d-flex align-items-center file-name-icon">
														<a href="#" class="avatar avatar-md border avatar-rounded">
															<img src="{{ URL::asset('build/img/company/company-01.svg') }}" class="img-fluid" alt="img">
														</a>
														<div class="ms-2">
															<h6 class="fw-medium fs-14"><a href="#">BrightWave Innovations</a></h6>
														</div>
													</div>
												</div>
											</div>
										</div>
									</div>	
								</div>
								<div class="col-md-4">
									<div class="mb-3">
										<span class="fs-12">Plan Name</span>
										<h6 class="fw-normal">Advanced</h6>
									</div>
								</div>
								<div class="col-md-4">
									<div class="mb-3">
										<span class="fs-12">Plan Type</span>
										<h6 class="fw-normal">Monthly</h6>
									</div>
								</div>
								<div class="col-md-4">
									<div class="mb-3">
										<span class="fs-12">Account URL</span>
										<h6 class="fw-normal">bwi.example.com</h6>
									</div>
								</div>
								<div class="col-md-4">
									<div class="mb-3">
										<span class="fs-12">Price</span>
										<h6 class="fw-normal">200</h6>
									</div>
								</div>
								<div class="col-md-4">
									<div class="mb-3">
										<span class="fs-12">Register Date</span>
										<h6 class="fw-normal">12/09/2024</h6>
									</div>
								</div>
								<div class="col-md-4">
									<div class="mb-3">
										<span class="fs-12">Expiring On</span>
										<h6 class="fw-normal">11/10/2024</h6>
									</div>
								</div>
							</div>
						</div>
					</form>
				</div>
			</div>
		</div>
		<!-- /Domain Details -->

		<!-- Delete Modal -->
		<div class="modal fade" id="delete_modal">
			<div class="modal-dialog modal-dialog-centered">
				<div class="modal-content">
					<div class="modal-body text-center">
						<span class="avatar avatar-xl bg-transparent-danger text-danger mb-3">
							<i class="ti ti-trash-x fs-36"></i>
						</span>
						<h4 class="mb-1">Confirm Delete</h4>
						<p class="mb-3">You want to delete all the marked items, this cant be undone once you delete.</p>
						<div class="d-flex justify-content-center">
							<a href="javascript:void(0);" class="btn btn-light me-3" data-bs-dismiss="modal">Cancel</a>
							<a href="{{url('domain')}}" class="btn btn-danger">Yes, Delete</a>
						</div>
					</div>
				</div>
			</div>
		</div>
		<!-- /Delete Modal -->
@endif