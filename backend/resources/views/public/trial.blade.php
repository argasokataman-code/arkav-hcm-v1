@extends('layout.guest-fullscreen-minimal')

@section('content')
@php
	$companyName = \App\Support\WebsiteSettings::businessCompanyName();
@endphp

<div class="landing-shell">
	<nav class="landing-nav navbar navbar-expand-lg">
		<div class="container">
			<a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="{{ url('/landing') }}">
				<i class="ti ti-sparkles"></i> <span>{{ $companyName }}</span>
			</a>
			<div class="ms-auto d-flex gap-2">
				<a href="{{ url('/landing#pricing') }}" class="btn btn-outline-secondary btn-sm">Lihat paket</a>
				<a href="{{ url('/login') }}" class="btn btn-primary btn-sm">Login</a>
			</div>
		</div>
	</nav>

	<div class="container py-5">
		<div class="row justify-content-center">
			<div class="col-lg-8">
				<div class="mb-4" data-reveal>
					<span class="badge bg-primary-subtle text-primary fw-semibold px-3 py-2">Coba Trial Gratis</span>
					<h1 class="h2 fw-bold mt-3 mb-2">Buat company & owner dalam 1 menit</h1>
					<p class="text-muted mb-0">Isi form di bawah. Setelah berhasil, kamu bisa login sebagai owner dan mulai setup modul HCM.</p>
				</div>

				<div class="card border-0 shadow-lg" data-reveal>
					<div class="card-body p-4 p-lg-5">
						<div class="alert alert-danger d-none" role="alert" data-onboarding-error></div>
						<div class="text-muted small mb-3">Field bertanda <span class="text-danger">*</span> wajib diisi.</div>

						<form id="onboardingForm" data-onboarding-form>
							<input type="text" name="website" class="d-none" tabindex="-1" autocomplete="off" aria-hidden="true">
							<input type="hidden" name="start_mode" value="trial">

							<div class="row g-3">
								<div class="col-md-6">
									<label class="form-label">Paket <span class="text-danger">*</span></label>
									<select class="form-select" name="package_id" required data-onboarding-package>
										@foreach ($packages as $package)
											<option value="{{ $package->id }}" @selected($selectedPackageId && (int) $selectedPackageId === (int) $package->id)>
												{{ $package->name }} ({{ $package->code }})
											</option>
										@endforeach
									</select>
								</div>
								<div class="col-md-6" data-billing-cycle-wrapper>
									<label class="form-label">Billing cycle <span class="text-danger">*</span></label>
									<select class="form-select" name="billing_cycle" required>
										<option value="monthly" selected>Monthly</option>
										<option value="yearly">Yearly</option>
									</select>
									<div class="form-text" data-billing-cycle-help>Berlaku saat subscription menjadi aktif (setelah masa trial berakhir).</div>
									<div class="form-text text-muted d-none" data-billing-cycle-trial-help>
										<i class="ti ti-lock text-warning"></i> Locked ke Monthly untuk trial. Bisa diubah setelah trial berakhir.
									</div>
								</div>
							</div>

							<hr class="my-4">

							<div class="row g-3">
								<div class="col-md-12">
									<label class="form-label">Company name <span class="text-danger">*</span></label>
									<input class="form-control" name="company_name" placeholder="Nama perusahaan" required maxlength="255">
									<div class="form-text">Company code akan dibuat otomatis (unik) setelah submit.</div>
								</div>
								<div class="col-md-6">
									<label class="form-label">PIC / Contact person</label>
									<input class="form-control" name="company_contact_person_name" placeholder="Nama PIC (opsional)" maxlength="120">
								</div>
								<div class="col-md-6">
									<label class="form-label">PIC role (optional)</label>
									<input class="form-control" name="company_contact_person_role" placeholder="Jabatan PIC (opsional)" maxlength="120">
								</div>
								<div class="col-md-6">
									<label class="form-label">Contact phone (company)</label>
									<input class="form-control" name="company_contact_phone" inputmode="tel" placeholder="contoh: +62 812-3456-7890" maxlength="30" pattern="^[0-9+\-\s().]{6,30}$">
									<div class="form-text">Opsional. Dipakai untuk kontak billing/support.</div>
								</div>
								<div class="col-md-6">
									<label class="form-label">Timezone <span class="text-danger">*</span></label>
									<select class="form-select" name="company_timezone" required>
										<option value="Asia/Jakarta" selected>Asia/Jakarta (WIB)</option>
										<option value="Asia/Makassar">Asia/Makassar (WITA)</option>
										<option value="Asia/Jayapura">Asia/Jayapura (WIT)</option>
									</select>
								</div>
								<div class="col-md-3">
									<label class="form-label">Currency <span class="text-danger">*</span></label>
									<select class="form-select" name="company_currency" required>
										<option value="IDR" selected>IDR</option>
									</select>
								</div>
								<div class="col-md-3">
									<label class="form-label">Country <span class="text-danger">*</span></label>
									<select class="form-select" name="company_country_code" required>
										<option value="ID" selected>ID</option>
									</select>
								</div>
								<div class="col-12">
									<label class="form-label">Legal name (optional)</label>
									<input class="form-control" name="company_legal_name" maxlength="255">
								</div>
								<div class="col-12">
									<label class="form-label">Alamat perusahaan <span class="text-danger">*</span></label>
									<textarea class="form-control" name="company_address" rows="3" required maxlength="500" placeholder="Contoh: Jl. Sudirman Kav 52-53, Jakarta Selatan"></textarea>
								</div>
								<div class="col-md-6">
									<label class="form-label">Kota <span class="text-danger">*</span></label>
									<input class="form-control" name="company_city" required maxlength="120" placeholder="Contoh: Jakarta Selatan">
								</div>
								<div class="col-md-6">
									<label class="form-label">Kode pos (optional)</label>
									<input class="form-control" name="company_postal_code" inputmode="numeric" maxlength="12" pattern="^[0-9]{3,12}$" placeholder="Contoh: 12190">
								</div>
							</div>

							<hr class="my-4">

							<div class="row g-3">
								<div class="col-md-6">
									<label class="form-label">Owner name <span class="text-danger">*</span></label>
									<input class="form-control" name="owner_name" required minlength="2" maxlength="150" pattern="^[A-Za-z][A-Za-z\s'.-]{1,149}$">
								</div>
								<div class="col-md-6">
									<label class="form-label">Owner email <span class="text-danger">*</span></label>
									<input type="email" class="form-control" name="owner_email" required maxlength="255">
								</div>
								<div class="col-md-6">
									<label class="form-label">Owner phone</label>
									<input class="form-control" name="owner_phone" inputmode="tel" placeholder="contoh: +62 812-3456-7890" maxlength="30" pattern="^[0-9+\-\s().]{6,30}$">
								</div>
								<div class="col-md-6">
									<label class="form-label">Password <span class="text-danger">*</span></label>
									<input type="password" class="form-control" name="owner_password" required minlength="8" maxlength="64" pattern="^(?=.*[A-Z])(?=.*[a-z])(?=.*\\d)[A-Za-z\\d@$!%*?&._-]{8,64}$">
									<div class="form-text">Min 8, harus ada huruf besar + kecil + angka.</div>
								</div>
								<div class="col-md-6 order-md-last">
									<label class="form-label">Confirm password <span class="text-danger">*</span></label>
									<input type="password" class="form-control" name="owner_confirm_password" required minlength="8" maxlength="64">
								</div>
							</div>

							<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mt-4">
								<div class="text-muted small">
									Dengan klik “Buat Trial”, kamu setuju proses onboarding dan validasi data berjalan sesuai sistem.
								</div>
								<button type="submit" class="btn btn-primary" data-onboarding-submit>
									<i class="ti ti-rocket me-1"></i> Buat Trial
								</button>
							</div>
						</form>
						{{--
						@if (config('turnstile.enabled') && config('turnstile.site_key'))
							<div class="mt-3">
								<div class="cf-turnstile" data-sitekey="{{ config('turnstile.site_key') }}"></div>
								<div class="form-text">Verifikasi keamanan untuk mencegah spam/bot.</div>
							</div>
							<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
						@endif
						--}}
					</div>
				</div>

				<div class="text-center text-muted small mt-4" data-reveal>
					Sudah punya akun? <a href="{{ url('/login') }}">Login</a>
				</div>
			</div>
		</div>
	</div>
</div>

<style>

<!-- Error Modal for Onboarding (Bootstrap 5) -->
<div class="modal fade" id="onboardingErrorModal" tabindex="-1" aria-labelledby="onboardingErrorModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content">
			<div class="modal-header bg-danger text-white">
				<span class="me-2"><i class="ti ti-alert-triangle" style="font-size:1.5rem;"></i></span>
				<h5 class="modal-title" id="onboardingErrorModalLabel">Terjadi Kesalahan</h5>
				<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<div id="onboardingErrorModalMsg" class="mb-2"></div>
				<ul id="onboardingErrorModalList" class="mb-0 ps-3"></ul>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-danger w-100" data-bs-dismiss="modal">Coba Lagi</button>
			</div>
		</div>
	</div>
</div>
	.landing-shell { background: radial-gradient(1200px 600px at 20% 10%, rgba(45,127,249,.18), transparent 60%), radial-gradient(900px 500px at 80% 0%, rgba(0,167,111,.16), transparent 55%), #ffffff; min-height: 100vh; }
	.landing-nav { position: sticky; top: 0; z-index: 10; backdrop-filter: blur(12px); background: rgba(255,255,255,.75); border-bottom: 1px solid rgba(0,0,0,.06); }
	[data-reveal] { opacity: 0; transform: translateY(14px); transition: opacity .6s ease, transform .6s ease; }
	[data-reveal].is-visible { opacity: 1; transform: translateY(0); }
	select:disabled { background-color: #f8f9fa; cursor: not-allowed; }
	.opacity-50 select:disabled { opacity: 0.6; }
	@media (prefers-reduced-motion: reduce) {
		[data-reveal] { opacity: 1; transform: none; transition: none; }
	}
</style>

<script src="{{ url('build/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ url('build/js/api-client.js') }}"></script>
<script src="{{ url('build/js/arcav-validation.js') }}"></script>
<link rel="stylesheet" href="{{ url('build/vendor/swiper-bundle.min.css') }}?v={{ file_exists(public_path('build/vendor/swiper-bundle.min.css')) ? filemtime(public_path('build/vendor/swiper-bundle.min.css')) : time() }}">
<script src="{{ url('build/vendor/swiper-bundle.min.js') }}?v={{ file_exists(public_path('build/vendor/swiper-bundle.min.js')) ? filemtime(public_path('build/vendor/swiper-bundle.min.js')) : time() }}"></script>
<script src="{{ url('build/vendor/countUp.umd.js') }}?v={{ file_exists(public_path('build/vendor/countUp.umd.js')) ? filemtime(public_path('build/vendor/countUp.umd.js')) : time() }}"></script>
<script src="{{ url('build/js/landing-vendor-init.js') }}?v={{ file_exists(public_path('build/js/landing-vendor-init.js')) ? filemtime(public_path('build/js/landing-vendor-init.js')) : time() }}"></script>
<script src="{{ url('build/js/public-landing-onboarding.js') }}?v={{ file_exists(public_path('build/js/public-landing-onboarding.js')) ? filemtime(public_path('build/js/public-landing-onboarding.js')) : time() }}"></script>
@endsection

