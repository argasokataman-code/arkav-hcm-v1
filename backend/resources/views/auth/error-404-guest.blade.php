@extends('layout.guest-fullscreen-minimal')
@section('content')
<div class="container flex-grow-1 d-flex align-items-center py-4">
	<div class="w-100">
		<div class="row justify-content-center align-items-center">
			<div class="col-md-8 d-flex justify-content-center align-items-center mx-auto">
				<div>
					<div class="p-4 text-center">
						<img src="{{ URL::asset('build/img/image111.png') }}" alt="logo" class="img-fluid">
					</div>
					<div class="error-images mb-4">
						<img src="{{ URL::asset('build/img/bg/error-404.svg') }}" alt="" class="img-fluid">
					</div>
					<div class="text-center">
						<h1 class="mb-3">Oops, something went wrong</h1>
						<p class="fs-16 text-center">Error 404 Page not found. Sorry the page you looking <br> for doesn’t exist or has been moved</p>
						<div class="d-flex justify-content-center pb-4">
							<a href="{{ url('/') }}" class="btn btn-primary d-flex align-items-center"><i class="ti ti-arrow-left me-2"></i>Back to Sign In</a>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
@endsection
