@extends('layout.guest-fullscreen-minimal')

@section('content')
@php
	$companyName = \App\Support\WebsiteSettings::businessCompanyName();
	$companyLogoUrl = \App\Support\WebsiteSettings::brandingUrl('white_logo', URL::asset('build/img/image111.png'));
	$companyMiniLogoUrl = \App\Support\WebsiteSettings::brandingUrl('white_mini_logo', URL::asset('build/img/image111.png'));
	$landingBootstrap = [
		'companyName' => $companyName,
		'companyLogoUrl' => $companyLogoUrl,
		'companyMiniLogoUrl' => $companyMiniLogoUrl,
		'landingUrl' => url('/landing'),
		'loginUrl' => url('/login'),
		'trialUrl' => url('/trial'),
		'turnstileEnabled' => (bool) config('turnstile.enabled'),
		'turnstileHideTestNotice' => (bool) config('turnstile.hide_test_notice'),
		'turnstileSiteKey' => (string) config('turnstile.site_key'),
		'packages' => $packages->map(function ($package) {
			$featureHighlights = $package->features
				->filter(fn ($feature) => method_exists($feature, 'isIncluded') ? $feature->isIncluded() : true)
				->take(4)
				->map(fn ($feature) => [
					'code' => (string) ($feature->feature_code ?? ''),
					'name' => (string) ($feature->feature_name ?: $feature->feature_code),
				])
				->values();

			return [
				'uuid' => (string) $package->uuid,
				'code' => (string) $package->code,
				'name' => (string) $package->name,
				'description' => (string) ($package->description ?? ''),
				'monthlyPrice' => (float) $package->monthly_price,
				'yearlyPrice' => (float) $package->yearly_price,
				'billingUnit' => (string) ($package->billing_unit ?? 'company'),
				'color' => (string) ($package->color ?: '#2D7FF9'),
				'featureHighlights' => $featureHighlights,
			];
		})->values(),
	];
@endphp

<script id="landing-app-data" type="application/json">@json($landingBootstrap)</script>
<script>document.documentElement.classList.add('landing-react-ready');</script>
@if (config('turnstile.enabled') && config('turnstile.site_key'))
	<script src="https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit" async defer></script>
@endif
<div id="landing-react-root" class="landing-react-root" aria-live="polite">
	<div class="landing-react-loading" aria-hidden="true">
		<div class="landing-react-loading__mark"><i class="ti ti-bolt"></i></div>
		<div class="landing-react-loading__eyebrow">Loading landing experience</div>
		<div class="landing-react-loading__title">Menyiapkan preview Arkav HCM...</div>
	</div>
</div>

<div class="landing-shell" data-landing-fallback>
	<div class="landing-orb landing-orb-one" aria-hidden="true"></div>
	<div class="landing-orb landing-orb-two" aria-hidden="true"></div>
	<div class="landing-grid" aria-hidden="true"></div>
	<nav class="landing-nav navbar navbar-expand-lg">
		<div class="container">
			<a class="navbar-brand landing-brand fw-bold d-flex align-items-center gap-3" href="{{ url('/landing') }}">
				<span class="landing-brand-mark"><i class="ti ti-sparkles"></i></span>
				<span>
					<small class="d-block text-uppercase">Modern HCM</small>
					<span>{{ $companyName }}</span>
				</span>
			</a>
			<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#landingNav">
				<span class="navbar-toggler-icon"></span>
			</button>
			<div class="collapse navbar-collapse" id="landingNav">
				<ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
					<li class="nav-item"><a class="nav-link" href="#features">Fitur</a></li>
					<li class="nav-item"><a class="nav-link" href="#solutions">Solusi</a></li>
					<li class="nav-item"><a class="nav-link" href="#how">Cara kerja</a></li>
					<li class="nav-item"><a class="nav-link" href="#pricing">Paket</a></li>
					<li class="nav-item"><a class="nav-link" href="#faq">FAQ</a></li>
					<li class="nav-item ms-lg-2">
						<a href="{{ url('/login') }}" class="btn btn-outline-secondary btn-sm landing-nav-ghost">Login</a>
					</li>
					<li class="nav-item">
						<a href="#pricing" class="btn btn-primary btn-sm landing-nav-cta">Mulai Trial</a>
					</li>
				</ul>
			</div>
		</div>
	</nav>

	<section class="landing-hero py-5">
		<div class="container">
			@php
				$priorityFeatureCodes = [
					'employee_management',
					'attendance',
					'leave_management',
					'payroll',
					'payroll_components',
					'payroll_thr',
					'holiday_calendar',
					'overtime',
					'performance',
					'training',
					'tickets',
					'asset_management',
				];

				$featureCatalogMap = $packages
					->flatMap(function ($package) {
						return $package->features ?? collect();
					})
					->filter(fn ($feature) => ! empty($feature->feature_code))
					->groupBy('feature_code')
					->map(function ($items, $code) {
						$first = $items->first();
						$label = $items
							->pluck('feature_name')
							->filter(fn ($name) => is_string($name) && trim($name) !== '')
							->first();

						return [
							'code' => (string) $code,
							'label' => $label ?: ucfirst(str_replace('_', ' ', (string) $code)),
							'icon' => match ((string) $code) {
								'employee_management', 'employee_document_center' => 'users',
								'attendance', 'attendance_shift_scheduling' => 'fingerprint',
								'leave_management', 'holiday_calendar' => 'calendar-time',
								'payroll', 'payroll_components', 'payroll_thr' => 'receipt-2',
								'performance', 'goal_tracking', 'training' => 'target',
								'asset_management', 'asset_logs' => 'package',
								'tickets', 'notifications' => 'message-circle-2',
								'overtime' => 'hourglass',
								'promotion', 'resignation', 'termination' => 'trending-up',
								default => 'zap',
							},
							'group' => match (true) {
								str_starts_with((string) $code, 'payroll') || (string) $code === 'overtime' => 'Payroll',
								in_array((string) $code, ['attendance', 'attendance_shift_scheduling', 'leave_management', 'holiday_calendar'], true) => 'Workforce Operations',
								in_array((string) $code, ['employee_management', 'employee_document_center', 'promotion', 'resignation', 'termination'], true) => 'People Management',
								in_array((string) $code, ['performance', 'goal_tracking', 'training'], true) => 'Performance & Growth',
								in_array((string) $code, ['tickets', 'notifications', 'asset_management', 'asset_logs'], true) => 'Support & Asset',
								default => 'Platform',
							},
							'_first' => $first,
						];
					});

				$sortedFeatureCatalog = $featureCatalogMap
					->sortBy(function ($feature) use ($priorityFeatureCodes) {
						$priorityIndex = array_search($feature['code'], $priorityFeatureCodes, true);
						return [
							$priorityIndex === false ? 999 : $priorityIndex,
							strtolower((string) $feature['label']),
						];
					})
					->values();

				$featuredLandingFeatures = $sortedFeatureCatalog->take(10);
				$featureGroups = $sortedFeatureCatalog->groupBy('group');

				$formatLimit = function ($limit) {
					if ($limit === null) return 'Unlimited';
					if ((int) $limit === 0) return '—';
					return (string) $limit;
				};
			@endphp
			<div class="row align-items-center g-4">
				<div class="col-lg-6" data-reveal data-parallax="0.08">
					<div class="mb-3">
						<span class="badge landing-pill fw-semibold px-3 py-2">Platform HR digital untuk perusahaan Indonesia</span>
					</div>
					<h1 class="display-5 fw-bold mb-3 landing-title">
						Kelola absensi, cuti, payroll, dan laporan dalam satu platform.
					</h1>
					<p class="text-muted fs-5 mb-4">
						Dari rekrutmen sampai payroll bulanan, semua proses HR ada dalam satu dashboard. Tim HR lebih cepat bekerja, owner lebih mudah memantau, dan finance lebih tenang saat tutup buku.
					</p>

					<div class="landing-proof-list d-flex flex-wrap gap-3 mb-4">
						<span><i class="ti ti-bolt me-2"></i>Setup cepat tanpa perlu tim IT khusus</span>
						<span><i class="ti ti-shield-check me-2"></i>Aman dengan role-based access control</span>
						<span><i class="ti ti-receipt-2 me-2"></i>Flexible pricing dan free trial tersedia</span>
					</div>

					<div class="d-flex flex-wrap gap-2">
						<a href="#pricing" class="btn btn-primary btn-lg">
							Lihat Paket
						</a>
						@if ($hasActiveTrialPackages)
							<a href="{{ url('/trial') }}" class="btn btn-outline-secondary btn-lg">
								Mulai Trial Gratis
							</a>
						@endif
					</div>

					<div class="landing-badges d-flex flex-wrap gap-2 mt-4">
						<span class="badge bg-light text-dark border"><i class="ti ti-shield-check me-1"></i>Satu platform untuk banyak cabang/perusahaan</span>
						<span class="badge bg-light text-dark border"><i class="ti ti-layout-dashboard me-1"></i>HR, Payroll, & Reporting terintegrasi</span>
						<span class="badge bg-light text-dark border"><i class="ti ti-zap me-1"></i>Siap digunakan untuk ketenagakerjaan Indonesia</span>
					</div>

					<div class="row g-2 mt-4" data-reveal>
						<div class="col-4">
							<div class="p-3 rounded-3 bg-white border landing-stat">
								<div class="text-muted small">Modul inti</div>
								<div class="h4 fw-bold mb-0" data-countup data-countup-end="{{ $sortedFeatureCatalog->count() }}" data-countup-suffix="+">0+</div>
							</div>
						</div>
						@if ($hasActiveTrialPackages)
							<div class="col-4">
								<div class="p-3 rounded-3 bg-white border landing-stat">
									<div class="text-muted small">Masa trial</div>
									<div class="h4 fw-bold mb-0" data-countup data-countup-end="{{ config('saas.trial_days', 30) }}" data-countup-suffix=" hari">0</div>
								</div>
							</div>
						@endif
						<div class="col-4">
							<div class="p-3 rounded-3 bg-white border landing-stat">
								<div class="text-muted small">State utama</div>
								<div class="h4 fw-bold mb-0" data-countup data-countup-end="4" data-countup-suffix=" step">0</div>
							</div>
						</div>
					</div>
				</div>

				<div class="col-lg-6" data-reveal data-parallax="0.14">
					<div class="landing-mock card border-0 shadow-lg overflow-hidden">
						<div class="card-body p-4 p-lg-5">
							<div class="landing-mock-head p-3 rounded-3 mb-3">
								<div class="d-flex align-items-center justify-content-between">
									<div class="d-flex align-items-center gap-2">
										<div class="landing-dot"></div>
										<div class="landing-dot"></div>
										<div class="landing-dot"></div>
										<div class="ms-2 fw-semibold">Preview Dashboard</div>
									</div>
									<span class="badge bg-success-subtle text-success"><i class="ti ti-bolt me-1"></i>Siap digunakan</span>
								</div>
								<div class="d-flex flex-wrap gap-2 mt-3">
									<span class="badge bg-light text-dark border"><i class="ti ti-layout-dashboard me-1"></i>Ringkasan Kinerja</span>
									<span class="badge bg-light text-dark border"><i class="ti ti-users me-1"></i>Data Karyawan</span>
									<span class="badge bg-light text-dark border"><i class="ti ti-calendar-time me-1"></i>Persetujuan Cuti</span>
									<span class="badge bg-light text-dark border"><i class="ti ti-receipt-2 me-1"></i>Billing & Payroll</span>
								</div>
							</div>

							<div class="row g-3">
								<div class="col-7">
									<div class="p-3 rounded-3 bg-light landing-kpi h-100">
										<div class="d-flex align-items-center justify-content-between">
											<div class="fw-semibold">Ringkasan minggu ini</div>
											<i class="ti ti-chart-line text-primary"></i>
										</div>
										<div class="text-muted small mt-1">Pantau absensi, approval, dan payroll dari satu layar.</div>

										<div class="landing-mini-chart mt-3" aria-hidden="true">
											<span style="height: 45%"></span>
											<span style="height: 62%"></span>
											<span style="height: 40%"></span>
											<span style="height: 78%"></span>
											<span style="height: 55%"></span>
											<span style="height: 86%"></span>
											<span style="height: 60%"></span>
										</div>

										<div class="d-flex gap-2 mt-3">
											<div class="flex-fill p-2 rounded-3 bg-white border">
												<div class="text-muted small">Produktivitas tim</div>
												<div class="fw-bold">Naik</div>
											</div>
											<div class="flex-fill p-2 rounded-3 bg-white border">
												<div class="text-muted small">Akurasi payroll</div>
												<div class="fw-bold">Terjaga</div>
											</div>
										</div>
									</div>
								</div>
								<div class="col-5">
									<div class="p-3 rounded-3 bg-light landing-kpi h-100">
										<div class="d-flex align-items-center justify-content-between">
											<div class="fw-semibold">Aktivitas penting hari ini</div>
											<i class="ti ti-bell-ringing text-warning"></i>
										</div>
										<div class="landing-activity mt-2">
											<div class="d-flex align-items-center gap-2 py-2">
												<span class="landing-ico-sm bg-primary-subtle text-primary"><i class="ti ti-user-plus"></i></span>
												<div class="small">
													<div class="fw-semibold">Data karyawan terbaru masuk</div>
													<div class="text-muted">Profil tim selalu up to date</div>
												</div>
											</div>
											<div class="d-flex align-items-center gap-2 py-2">
												<span class="landing-ico-sm bg-success-subtle text-success"><i class="ti ti-calendar-check"></i></span>
												<div class="small">
													<div class="fw-semibold">Pengajuan cuti siap diproses</div>
													<div class="text-muted">Proses approval lebih cepat</div>
												</div>
											</div>
											<div class="d-flex align-items-center gap-2 py-2">
												<span class="landing-ico-sm bg-warning-subtle text-warning"><i class="ti ti-receipt-2"></i></span>
												<div class="small">
													<div class="fw-semibold">Tagihan siap ditindaklanjuti</div>
													<div class="text-muted">Alur billing lebih transparan</div>
												</div>
											</div>
										</div>
										<div class="mt-2">
											<span class="badge bg-light text-dark border"><i class="ti ti-shield-check me-1"></i>Audit-friendly</span>
										</div>
									</div>
								</div>
							</div>

							<div class="mt-3 p-3 rounded-3 border bg-white landing-next">
								<div class="d-flex align-items-center justify-content-between">
									<div>
										<div class="small text-muted">Langkah selanjutnya</div>
										<div class="fw-semibold">Daftar sekarang, langsung bisa dipakai</div>
										<div class="text-muted small">Pilih paket, buat akun owner, dan mulai operasional HR hari ini.</div>
									</div>
									@if ($hasActiveTrialPackages)
										<a class="btn btn-sm btn-primary" href="{{ url('/trial') }}">
											<i class="ti ti-rocket me-1"></i> Mulai
										</a>
									@else
										<a class="btn btn-sm btn-primary" href="{{ url('/trial') }}">
											<i class="ti ti-rocket me-1"></i> Daftar
										</a>
									@endif
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</section>

	<section class="py-5 landing-section landing-section-soft">
		<div class="container">
			<div class="row align-items-end g-3 mb-4" data-reveal>
				<div class="col-lg-8">
					<span class="landing-section-kicker">Experience</span>
					<h2 class="h3 fw-bold mb-2 landing-section-title">Apa yang tim kamu rasakan setelah implementasi</h2>
					<p class="text-muted mb-0 landing-section-copy">Hasil yang langsung terasa: proses HR lebih cepat, payroll lebih akurat, dan keputusan bisnis lebih percaya diri.</p>
				</div>
			</div>

			<div class="swiper landing-swiper" data-landing-swiper data-reveal>
				<div class="swiper-wrapper">
					<div class="swiper-slide">
						<div class="card border-0 shadow-sm h-100">
							<div class="card-body p-4">
								<div class="d-flex align-items-center gap-2 mb-2">
									<span class="landing-ico bg-primary-subtle text-primary"><i class="ti ti-checklist"></i></span>
									<div class="fw-semibold">Operasional HR lebih rapi</div>
								</div>
								<div class="text-muted">Data employee, leave, dan attendance lebih tertata—alur approval jelas, mudah ditelusuri.</div>
							</div>
						</div>
					</div>
					<div class="swiper-slide">
						<div class="card border-0 shadow-sm h-100">
							<div class="card-body p-4">
								<div class="d-flex align-items-center gap-2 mb-2">
									<span class="landing-ico bg-success-subtle text-success"><i class="ti ti-receipt-2"></i></span>
									<div class="fw-semibold">Payroll run lebih aman</div>
								</div>
								<div class="text-muted">Draft → finalize → disburse dengan proses yang jelas, mengurangi salah hitung & salah langkah.</div>
							</div>
						</div>
					</div>
					<div class="swiper-slide">
						<div class="card border-0 shadow-sm h-100">
							<div class="card-body p-4">
								<div class="d-flex align-items-center gap-2 mb-2">
									<span class="landing-ico bg-warning-subtle text-warning"><i class="ti ti-shield-check"></i></span>
									<div class="fw-semibold">Akses & audit lebih jelas</div>
								</div>
								<div class="text-muted">Role-based access admin vs karyawan, plus reporting snapshot untuk kebutuhan arsip.</div>
							</div>
						</div>
					</div>
					<div class="swiper-slide">
						<div class="card border-0 shadow-sm h-100">
							<div class="card-body p-4">
								<div class="d-flex align-items-center gap-2 mb-2">
									<span class="landing-ico bg-info-subtle text-info"><i class="ti ti-rocket"></i></span>
									<div class="fw-semibold">Mulai cepat</div>
								</div>
								<div class="text-muted">Self-serve onboarding: pilih paket → buat company + owner → trial / subscribe, tanpa setup rumit.</div>
							</div>
						</div>
					</div>
				</div>
				<div class="swiper-pagination"></div>
			</div>
		</div>
	</section>

	<section id="features" class="py-5 landing-section">
		<div class="container">
			<div class="row align-items-end g-3 mb-4" data-reveal>
				<div class="col-lg-8">
					<span class="landing-section-kicker">Features</span>
					<h2 class="h3 fw-bold mb-2 landing-section-title">Modul lengkap untuk mengelola seluruh HR lifecycle</h2>
					<p class="text-muted mb-0 landing-section-copy">Mulai dari employee management, attendance, leave, hingga payroll dan pelaporan, semua tersusun untuk membantu tim bekerja lebih cepat dan akurat.</p>
				</div>
			</div>

			<div class="row g-3">
				@forelse ($featuredLandingFeatures as $feature)
					<div class="col-md-6 col-lg-4" data-reveal>
						<div class="card h-100 border-0 shadow-sm landing-feature">
							<div class="card-body p-4">
								<div class="d-flex align-items-center gap-2 mb-2">
									<span class="landing-ico bg-primary-subtle text-primary">
										<i class="ti ti-{{ $feature['icon'] }}"></i>
									</span>
									<div class="fw-semibold">{{ $feature['label'] }}</div>
								</div>
								<div class="text-muted small">{{ $feature['group'] }}</div>
							</div>
						</div>
					</div>
				@empty
					<div class="col-12 text-center text-muted py-5">
						<p>Features loading...</p>
					</div>
				@endforelse
			</div>
		</div>
	</section>

	<section id="solutions" class="py-5 landing-section">
		<div class="container">
			<div class="row align-items-end g-3 mb-4" data-reveal>
				<div class="col-lg-8">
					<span class="landing-section-kicker">Solutions</span>
					<h2 class="h3 fw-bold mb-2 landing-section-title">Solusi berbeda untuk role yang berbeda</h2>
					<p class="text-muted mb-0 landing-section-copy">Setiap peran mendapatkan tampilan dan alur kerja yang tepat sesuai tanggung jawabnya.</p>
				</div>
			</div>

			<div class="card border-0 shadow-sm landing-surface" data-reveal>
				<div class="card-body p-4 p-lg-5">
					<ul class="nav nav-pills gap-2" id="roleTabs" role="tablist">
						<li class="nav-item" role="presentation">
							<button class="nav-link active" id="role-hr-tab" data-bs-toggle="pill" data-bs-target="#role-hr" type="button" role="tab">
								<i class="ti ti-users me-1"></i> Admin HR
							</button>
						</li>
						<li class="nav-item" role="presentation">
							<button class="nav-link" id="role-emp-tab" data-bs-toggle="pill" data-bs-target="#role-emp" type="button" role="tab">
								<i class="ti ti-id-badge-2 me-1"></i> Karyawan
							</button>
						</li>
						<li class="nav-item" role="presentation">
							<button class="nav-link" id="role-fin-tab" data-bs-toggle="pill" data-bs-target="#role-fin" type="button" role="tab">
								<i class="ti ti-receipt-tax me-1"></i> Finance
							</button>
						</li>
					</ul>

					<div class="tab-content mt-4">
						<div class="tab-pane fade show active" id="role-hr" role="tabpanel">
							<div class="row g-3">
								<div class="col-md-6">
									<div class="p-3 rounded-3 border bg-white h-100">
										<div class="fw-semibold mb-1">Manajemen karyawan & organisasi</div>
										<div class="text-muted small">Directory employee, department/designation/policy, dan update data yang rapi.</div>
									</div>
								</div>
								<div class="col-md-6">
									<div class="p-3 rounded-3 border bg-white h-100">
										<div class="fw-semibold mb-1">Operasional harian</div>
										<div class="text-muted small">Attendance admin, schedule timing + shift, leave approval multirole.</div>
									</div>
								</div>
								<div class="col-md-6">
									<div class="p-3 rounded-3 border bg-white h-100">
										<div class="fw-semibold mb-1">Kontrol & audit</div>
										<div class="text-muted small">Reporting hub (live/archive) untuk rekap dan kebutuhan audit.</div>
									</div>
								</div>
								<div class="col-md-6">
									<div class="p-3 rounded-3 border bg-white h-100">
										<div class="fw-semibold mb-1">Performance (opsional)</div>
										<div class="text-muted small">Cycle, review workflow, goal tracking, dan training (sesuai paket).</div>
									</div>
								</div>
							</div>
						</div>

						<div class="tab-pane fade" id="role-emp" role="tabpanel">
							<div class="row g-3">
								<div class="col-md-6">
									<div class="p-3 rounded-3 border bg-white h-100">
										<div class="fw-semibold mb-1">Self-service</div>
										<div class="text-muted small">Attendance self + leave request, cepat dan transparan.</div>
									</div>
								</div>
								<div class="col-md-6">
									<div class="p-3 rounded-3 border bg-white h-100">
										<div class="fw-semibold mb-1">Slip gaji</div>
										<div class="text-muted small">Akses payslip self untuk periode yang sudah finalized.</div>
									</div>
								</div>
								<div class="col-md-6">
									<div class="p-3 rounded-3 border bg-white h-100">
										<div class="fw-semibold mb-1">Akses aman</div>
										<div class="text-muted small">Hak akses jelas: karyawan hanya melihat data miliknya.</div>
									</div>
								</div>
								<div class="col-md-6">
									<div class="p-3 rounded-3 border bg-white h-100">
										<div class="fw-semibold mb-1">Status real-time</div>
										<div class="text-muted small">Progress approval dan status attendance terlihat jelas.</div>
									</div>
								</div>
							</div>
						</div>

						<div class="tab-pane fade" id="role-fin" role="tabpanel">
							<div class="row g-3">
								<div class="col-md-6">
									<div class="p-3 rounded-3 border bg-white h-100">
										<div class="fw-semibold mb-1">Payroll run</div>
										<div class="text-muted small">Draft → finalize → disburse. Flow terstruktur untuk mengurangi error.</div>
									</div>
								</div>
								<div class="col-md-6">
									<div class="p-3 rounded-3 border bg-white h-100">
										<div class="fw-semibold mb-1">Billing SaaS (admin)</div>
										<div class="text-muted small">Trial vs subscribed, invoice terbaru, resend email invoice (opsional).</div>
									</div>
								</div>
								<div class="col-md-6">
									<div class="p-3 rounded-3 border bg-white h-100">
										<div class="fw-semibold mb-1">Akurasi</div>
										<div class="text-muted small">Perhitungan & output konsisten, siap untuk rekap dan audit.</div>
									</div>
								</div>
								<div class="col-md-6">
									<div class="p-3 rounded-3 border bg-white h-100">
										<div class="fw-semibold mb-1">Export & reporting</div>
										<div class="text-muted small">Snapshot report untuk kebutuhan arsip dan kontrol proses.</div>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</section>

	<section id="how" class="py-5 bg-light landing-section landing-section-soft">
		<div class="container">
			<div class="row g-3 mb-4" data-reveal>
				<div class="col-lg-8">
					<span class="landing-section-kicker">How it works</span>
					<h2 class="h3 fw-bold mb-2 landing-section-title">Mulai dalam 4 langkah mudah</h2>
					<p class="text-muted mb-0 landing-section-copy">Daftar, pilih paket, undang tim, dan langsung kelola HR operasional — tanpa perlu install atau setup rumit.</p>
				</div>
			</div>

			<div class="row g-3">
				<div class="col-md-6 col-lg-3" data-reveal>
					<div class="card h-100 border-0 shadow-sm">
						<div class="card-body p-4">
							<div class="landing-step">01</div>
							<div class="fw-semibold mb-1">Pilih paket</div>
							<div class="text-muted small">Sesuaikan kebutuhan & budget perusahaan.</div>
						</div>
					</div>
				</div>
				<div class="col-md-6 col-lg-3" data-reveal>
					<div class="card h-100 border-0 shadow-sm">
						<div class="card-body p-4">
							<div class="landing-step">02</div>
							<div class="fw-semibold mb-1">Buat company + owner</div>
							<div class="text-muted small">Onboarding terstruktur dengan validasi yang ketat.</div>
						</div>
					</div>
				</div>
				@if ($hasActiveTrialPackages)
				<div class="col-md-6 col-lg-3" data-reveal>
					<div class="card h-100 border-0 shadow-sm">
						<div class="card-body p-4">
							<div class="landing-step">03</div>
							<div class="fw-semibold mb-1">Trial / Pending payment</div>
							<div class="text-muted small">Mulai trial gratis atau langsung invoice bila pilih subscribe.</div>
						</div>
					</div>
				</div>
				@else
				<div class="col-md-6 col-lg-3" data-reveal>
					<div class="card h-100 border-0 shadow-sm">
						<div class="card-body p-4">
							<div class="landing-step">03</div>
							<div class="fw-semibold mb-1">Pending payment</div>
							<div class="text-muted small">Pilih paket, buat invoice, dan lakukan pembayaran.</div>
						</div>
					</div>
				</div>
				@endif
				<div class="col-md-6 col-lg-3" data-reveal>
					<div class="card h-100 border-0 shadow-sm">
						<div class="card-body p-4">
							<div class="landing-step">04</div>
							<div class="fw-semibold mb-1">Login & mulai pakai</div>
							<div class="text-muted small">Masuk ke aplikasi dan mulai setup modul HCM.</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</section>

	<section id="pricing" class="py-5 landing-section">
		<div class="container">
			<div class="row align-items-end g-3 mb-4" data-reveal>
				<div class="col-lg-8">
					<span class="landing-section-kicker">Pricing</span>
					<h2 class="h3 fw-bold mb-2 landing-section-title">Pilih paket yang paling pas untuk pertumbuhan tim Anda</h2>
					<p class="text-muted mb-0 landing-section-copy">Bandingkan manfaat setiap paket lalu aktifkan akun sesuai kebutuhan perusahaan Anda.</p>
				</div>
				<div class="col-lg-4 text-lg-end" data-reveal>
					<div class="d-inline-flex align-items-center gap-2 p-2 rounded-3 bg-light border landing-cycle-toggle">
						<span class="small text-muted">Monthly</span>
						<div class="form-check form-switch m-0">
							<input class="form-check-input" type="checkbox" role="switch" id="billingToggle" data-billing-toggle>
						</div>
						<span class="small fw-semibold">Yearly</span>
					</div>
				</div>
			</div>

	<div class="row g-3" data-packages-grid data-packages='@json($packages)'>
		@forelse ($packages as $package)
			<div class="col-md-6 col-lg-4">
				@php
					$packageHighlights = $package->features
						->filter(fn ($feature) => method_exists($feature, 'isIncluded') ? $feature->isIncluded() : true)
						->take(4);
					$packageAccent = $package->color ?: '#2D7FF9';
				@endphp
				<div class="card h-100 border-0 shadow-sm landing-card landing-package-card" data-reveal style="--package-accent: {{ $packageAccent }};">
					<div class="card-body p-4">
						<div class="d-flex align-items-start justify-content-between">
							<div>
								<div class="fw-bold fs-5">{{ $package->name }}</div>
								<div class="text-muted small text-uppercase">{{ $package->code }}</div>
							</div>
							<span class="badge bg-success-subtle text-success">Active</span>
						</div>

						@if ($package->description)
							<p class="text-muted mt-3 mb-3">{{ $package->description }}</p>
						@else
							<p class="text-muted mt-3 mb-3">Paket siap pakai untuk memulai operasional HR dengan rapi.</p>
						@endif

						<div class="d-flex flex-wrap gap-2 mb-3">
							@forelse ($packageHighlights as $feature)
								<span class="badge bg-light text-dark border"><i class="ti ti-check me-1"></i>{{ $feature->feature_name ?: $feature->feature_code }}</span>
							@empty
								<span class="badge bg-light text-dark border"><i class="ti ti-check me-1"></i>Dashboard</span>
								<span class="badge bg-light text-dark border"><i class="ti ti-check me-1"></i>Employees</span>
								<span class="badge bg-light text-dark border"><i class="ti ti-check me-1"></i>Attendance</span>
								<span class="badge bg-light text-dark border"><i class="ti ti-check me-1"></i>Leave</span>
							@endforelse
						</div>

						<div class="d-flex align-items-end justify-content-between mt-3">
							<div>
								<div class="small text-muted">Mulai dari</div>
								<div
									class="fs-4 fw-bold landing-price"
									data-price
									data-price-monthly="{{ (float) $package->monthly_price }}"
									data-price-yearly="{{ (float) $package->yearly_price }}"
									data-price-cycle="monthly"
								>
									Rp {{ number_format((float) $package->monthly_price, 0, ',', '.') }}
									<span class="fs-6 text-muted fw-normal" data-price-suffix>/bulan</span>
								</div>
							</div>
							<a
								class="btn btn-primary"
								href="{{ url('/trial') }}?packageId={{ $package->uuid }}"
							>
								Pilih plan
							</a>
						</div>

						<div class="mt-3 small text-muted">
							<span class="me-2">Tahunan: Rp {{ number_format((float) $package->yearly_price, 0, ',', '.') }}</span>
							<span class="badge bg-primary-subtle text-primary">Lebih efisien</span>
						</div>
					</div>
				</div>
			</div>
		@empty
			<div class="col-12">
				<div class="alert alert-warning mb-0">Belum ada paket aktif.</div>
			</div>
		@endforelse
	</div>

			<div class="mt-4" data-reveal>
				<h3 class="h5 fw-bold mb-2">Perbandingan fitur per paket</h3>
				<p class="text-muted mb-0">Detail fitur setiap paket berdasarkan modul yang tersedia.</p>
			</div>

			<div class="accordion mt-3" id="packageComparison" data-reveal>
				@foreach ($featureGroups as $groupName => $features)
					<div class="accordion-item">
						<h2 class="accordion-header" id="cmp_{{ \Illuminate\Support\Str::slug($groupName) }}">
							<button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#cmp_body_{{ \Illuminate\Support\Str::slug($groupName) }}">
								{{ $groupName }}
							</button>
						</h2>
						<div id="cmp_body_{{ \Illuminate\Support\Str::slug($groupName) }}" class="accordion-collapse collapse" data-bs-parent="#packageComparison">
							<div class="accordion-body">
								<div class="table-responsive">
									<table class="table table-sm align-middle mb-0">
										<thead>
											<tr>
												<th style="min-width: 220px">Feature</th>
												@foreach ($packages as $p)
													<th class="text-nowrap">{{ $p->name }}</th>
												@endforeach
											</tr>
										</thead>
										<tbody>
											@foreach ($features as $f)
												<tr>
													<td class="fw-semibold">{{ $f['label'] }}</td>
													@foreach ($packages as $p)
														@php
															$pf = $p->features->firstWhere('feature_code', $f['code']);
															$lim = $pf?->limit;
															$included = $pf ? $pf->isIncluded() : false;
														@endphp
														<td>
															@if ($included)
																<span class="badge bg-success-subtle text-success">
																	<i class="ti ti-check me-1"></i>{{ $formatLimit($lim) }}
																</span>
															@else
																<span class="text-muted">—</span>
															@endif
														</td>
													@endforeach
												</tr>
											@endforeach
										</tbody>
									</table>
								</div>
							</div>
						</div>
					</div>
				@endforeach
			</div>

			<div class="card border-0 shadow-sm mt-4" data-reveal>
				<div class="card-body p-4 d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
					<div>
						<div class="fw-bold">Siap mulai?</div>
						<div class="text-muted">Klik “Pilih plan” pada paket pilihanmu, isi onboarding company, lalu login sebagai owner.</div>
					</div>
					<div class="d-flex gap-2">
						<a class="btn btn-primary" href="{{ url('/trial') }}">Daftarkan company</a>
						<a class="btn btn-outline-secondary" href="{{ url('/login') }}">Login</a>
					</div>
				</div>
			</div>
		</div>
	</section>

	<section id="faq" class="py-5 bg-light landing-section landing-section-soft">
		<div class="container">
			<div class="row g-3 mb-4" data-reveal>
				<div class="col-lg-8">
					<span class="landing-section-kicker">FAQ</span>
					<h2 class="h3 fw-bold mb-2 landing-section-title">Jawaban cepat sebelum user masuk ke flow trial</h2>
					<p class="text-muted mb-0 landing-section-copy">Pertanyaan yang paling sering ditanyakan sebelum mulai menggunakan platform.</p>
				</div>
			</div>

			<div class="accordion" id="landingFaq" data-reveal>
				<div class="accordion-item">
					<h2 class="accordion-header" id="faqOne">
						<button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faqOneBody">
							Setelah onboarding, gimana cara masuk?
						</button>
					</h2>
					<div id="faqOneBody" class="accordion-collapse collapse show" data-bs-parent="#landingFaq">
						<div class="accordion-body">
							Pakai email owner yang kamu buat saat onboarding, lalu login lewat <a href="{{ url('/login') }}">/login</a>.
						</div>
					</div>
				</div>
				@if ($hasActiveTrialPackages)
					<div class="accordion-item">
						<h2 class="accordion-header" id="faqTwo">
							<button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqTwoBody">
								Bisa trial dulu atau harus bayar?
							</button>
						</h2>
						<div id="faqTwoBody" class="accordion-collapse collapse" data-bs-parent="#landingFaq">
							<div class="accordion-body">
								Bisa pilih di form onboarding: <strong>Trial</strong> atau <strong>Pending payment</strong>.
							</div>
						</div>
					</div>
				@endif
				<div class="accordion-item">
					<h2 class="accordion-header" id="faqThree">
						<button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqThreeBody">
							Kalau company code / email sudah dipakai?
						</button>
					</h2>
					<div id="faqThreeBody" class="accordion-collapse collapse" data-bs-parent="#landingFaq">
						<div class="accordion-body">
							Sistem akan menolak (422). Untuk email yang sudah terdaftar, gunakan halaman login.
						</div>
					</div>
				</div>
			</div>

			<div class="text-center text-muted small mt-5">
				Butuh bantuan lebih lanjut? <a href="{{ url('/login') }}">Login</a> untuk akses Knowledge Base.
			</div>
		</div>
	</section>
</div>

<!-- Onboarding Modal -->
<div class="modal fade" id="onboardingModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-centered">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">Mulai onboarding</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<form id="onboardingForm">
				<input type="text" name="website" class="d-none" tabindex="-1" autocomplete="off" aria-hidden="true">
				<div class="modal-body">
					<div class="alert alert-danger d-none" role="alert" data-onboarding-error></div>
					<div class="text-muted small mb-2">Field bertanda <span class="text-danger">*</span> wajib diisi.</div>

					<div class="row g-3">
						<div class="col-md-6">
							<label class="form-label">Paket <span class="text-danger">*</span></label>
							<select class="form-select" name="package_uuid" required data-onboarding-package></select>
						</div>
						<div class="col-md-3">
							<label class="form-label">Billing cycle <span class="text-danger">*</span></label>
							<select class="form-select" name="billing_cycle" required>
								<option value="monthly">Monthly</option>
								<option value="yearly">Yearly</option>
							</select>
							@if ($hasActiveTrialPackages)
								<div class="form-text">Trial otomatis <span class="fw-semibold">{{ config('saas.trial_days', 30) }}</span> hari untuk paket yang kamu pilih.</div>
							@endif
						</div>
						<div class="col-md-3">
							<label class="form-label">Start mode</label>
							<select class="form-select" name="start_mode">
								@if ($hasActiveTrialPackages)
									<option value="trial">Trial</option>
								@endif
								<option value="pending_payment">Pending payment</option>
							</select>
						</div>
					</div>

					<hr class="my-4">

					<div class="row g-3">
						<div class="col-md-12">
							<label class="form-label">Company name <span class="text-danger">*</span></label>
							<input class="form-control" name="company_name" placeholder="Nama perusahaan" required minlength="2" maxlength="255">
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
							<input type="password" class="form-control" name="owner_password" required minlength="8" maxlength="64" pattern="^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)[A-Za-z\d@$!%*?&._-]{8,64}$">
							<div class="form-text">Min 8, harus ada huruf besar + kecil + angka.</div>
						</div>
						<div class="col-md-6">
							<label class="form-label">Confirm password <span class="text-danger">*</span></label>
							<input type="password" class="form-control" name="owner_confirm_password" required minlength="8" maxlength="64">
						</div>
						<div class="col-12">
							<label class="form-label">Billing email (optional)</label>
							<input type="email" class="form-control" name="billing_email" maxlength="255" placeholder="billing@company.com">
						</div>
					</div>

					<div class="mt-4 p-3 rounded-3 border">
						<div class="form-check">
							<input class="form-check-input" type="checkbox" id="consentAccepted" name="consent_accepted" value="1" required>
							<label class="form-check-label" for="consentAccepted">
								Saya menyetujui pemrosesan data berikut:
								<ul class="mb-0 mt-1 ps-3 small">
									<li>Data identitas (nama, email, no. telepon) untuk pembuatan dan pengelolaan akun</li>
									<li>Data perusahaan (nama, alamat, NPWP) untuk keperluan faktur dan billing</li>
									<li>Data karyawan untuk operasional HR (absensi, penggajian, cuti, dan penilaian kinerja)</li>
								</ul>
								<div class="mt-2">
									Data disimpan secara aman sesuai <a href="/privacy-policy" target="_blank" rel="noopener noreferrer">Kebijakan Privasi</a>
									dan <a href="/terms-condition" target="_blank" rel="noopener noreferrer">Syarat &amp; Ketentuan</a>.
									Saya dapat mengajukan penghapusan data kapan saja melalui pengaturan akun.
								</div>
							</label>
						</div>
					</div>

					<div class="mt-4 p-3 rounded-3 bg-light">
						<div class="small text-muted">Catatan</div>
						<div class="fw-semibold">Setelah onboarding berhasil, silakan login pakai email owner untuk masuk ke aplikasi.</div>
					</div>
					@if (config('turnstile.enabled') && config('turnstile.site_key'))
						<div class="mt-3">
							<div data-turnstile-container data-sitekey="{{ config('turnstile.site_key') }}"></div>
							<div class="form-text">Verifikasi keamanan untuk mencegah spam/bot.</div>
						</div>
					@endif
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
					<button type="submit" class="btn btn-primary" data-onboarding-submit>Proses</button>
				</div>
			</form>
		</div>
	</div>
</div>

<style>
	.landing-shell {
		--landing-primary: var(--bs-primary);
		--landing-primary-rgb: var(--bs-primary-rgb);
		--landing-accent: #0ea5a4;
		--landing-ink: #0f172a;
		--landing-muted: #5b6474;
		position: relative;
		isolation: isolate;
		overflow: clip;
		background:
			.landing-shell {
				--landing-primary: var(--bs-primary);
				--landing-primary-rgb: var(--bs-primary-rgb);
				--landing-accent: #0ea5a4;
				--landing-ink: #0f172a;
				--landing-muted: #5b6474;
				position: relative;
				isolation: isolate;
				overflow: clip;
				background:
					radial-gradient(1200px 620px at 12% 8%, rgba(var(--landing-primary-rgb), .20), transparent 60%),
					radial-gradient(860px 520px at 88% 6%, rgba(14,165,164,.16), transparent 55%),
					linear-gradient(180deg, #fbfdff 0%, #f4f8fc 54%, #ffffff 100%);
			}
			.landing-grid {
				position: absolute;
				inset: 0;
				background-image: linear-gradient(rgba(15, 23, 42, .035) 1px, transparent 1px), linear-gradient(90deg, rgba(15, 23, 42, .035) 1px, transparent 1px);
				background-size: 64px 64px;
				mask-image: linear-gradient(180deg, rgba(255,255,255,.85), transparent 82%);
				pointer-events: none;
				z-index: -3;
			}
			.landing-orb {
				position: absolute;
				border-radius: 999px;
				filter: blur(10px);
				pointer-events: none;
				z-index: -2;
			}
			.landing-orb-one {
				width: 28rem;
				height: 28rem;
				top: -8rem;
				left: -7rem;
				background: radial-gradient(circle, rgba(var(--landing-primary-rgb), .28) 0%, rgba(var(--landing-primary-rgb), 0) 70%);
			}
			.landing-orb-two {
				width: 26rem;
				height: 26rem;
				top: 12rem;
				right: -7rem;
				background: radial-gradient(circle, rgba(14,165,164,.20) 0%, rgba(14,165,164,0) 70%);
			}
			.landing-nav {
				position: sticky;
				top: 0;
				z-index: 10;
				backdrop-filter: blur(18px);
				background: rgba(255,255,255,.72);
				border-bottom: 1px solid rgba(15,23,42,.08);
			}
			.landing-brand small {
				font-size: .64rem;
				letter-spacing: .18em;
				color: rgba(15,23,42,.5);
				margin-bottom: .2rem;
			}
			.landing-brand-mark {
				width: 2.8rem;
				height: 2.8rem;
				display: inline-flex;
				align-items: center;
				justify-content: center;
				border-radius: 1rem;
				background: linear-gradient(135deg, rgba(var(--landing-primary-rgb), .94), rgba(14,165,164,.96));
				color: #fff;
				box-shadow: 0 1rem 2rem rgba(var(--landing-primary-rgb), .18);
			}
			.landing-nav-ghost,
			.landing-nav-cta {
				border-radius: 999px;
				padding-inline: 1rem;
			}
			.landing-nav-cta {
				box-shadow: 0 .9rem 2rem rgba(var(--landing-primary-rgb), .18);
			}
			.landing-pill {
				background: linear-gradient(90deg, rgba(var(--landing-primary-rgb), .10), rgba(14,165,164,.12));
				color: var(--landing-primary);
				border: 1px solid rgba(var(--landing-primary-rgb), .12);
				border-radius: 999px;
			}
			.landing-hero {
				position: relative;
				padding-top: 4rem !important;
				padding-bottom: 4rem !important;
			}
			.landing-title {
				letter-spacing: -0.04em;
				line-height: 1.02;
				max-width: 12ch;
				color: var(--landing-ink);
			}
			.landing-proof-list span {
				display: inline-flex;
				align-items: center;
				padding: .72rem 1rem;
				border-radius: 999px;
				background: rgba(255,255,255,.8);
				border: 1px solid rgba(15,23,42,.08);
				color: #334155;
				font-size: .95rem;
				box-shadow: 0 .8rem 2rem rgba(15,23,42,.06);
			}
			.landing-section {
				position: relative;
				padding-block: 5.5rem !important;
			}
			.landing-section-soft {
				background: linear-gradient(180deg, rgba(255,255,255,.28), rgba(248,250,252,.88));
			}
			.landing-section-kicker {
				display: inline-flex;
				align-items: center;
				gap: .5rem;
				padding: .45rem .9rem;
				border-radius: 999px;
				font-size: .72rem;
				letter-spacing: .16em;
				text-transform: uppercase;
				font-weight: 700;
				color: var(--landing-primary);
				background: rgba(var(--landing-primary-rgb), .08);
				margin-bottom: .9rem;
			}
			.landing-section-title {
				letter-spacing: -.03em;
				color: var(--landing-ink);
				max-width: 18ch;
			}
			.landing-section-copy {
				max-width: 60ch;
				font-size: 1rem;
				color: var(--landing-muted) !important;
			}
			.landing-surface,
			.landing-mock,
			.landing-card,
			.landing-feature,
			.landing-swiper .card,
			#how .card,
			#faq .accordion-item {
				background: rgba(255,255,255,.82);
				backdrop-filter: blur(18px);
				border: 1px solid rgba(255,255,255,.65);
				box-shadow: 0 1.2rem 3rem rgba(15,23,42,.08) !important;
			}
			.landing-mock { transform: translateY(0); transition: transform .35s ease, box-shadow .35s ease; }
			.landing-mock:hover { transform: translateY(-6px); box-shadow: 0 1.4rem 3rem rgba(15,23,42,.14) !important; }
			.landing-card { transform: translateY(0); transition: transform .25s ease, box-shadow .25s ease; }
			.landing-card:hover { transform: translateY(-5px); box-shadow: 0 1.2rem 2.6rem rgba(15,23,42,.12) !important; }
			.landing-feature { transition: transform .25s ease, box-shadow .25s ease, border-color .25s ease; }
			.landing-feature:hover { transform: translateY(-4px); border-color: rgba(var(--landing-primary-rgb), .18); box-shadow: 0 1.1rem 2.6rem rgba(15,23,42,.10) !important; }
			.landing-kpi { transition: transform .25s ease; background: linear-gradient(180deg, rgba(248,250,252,.96), rgba(255,255,255,.88)) !important; }
			.landing-mock:hover .landing-kpi { transform: translateY(-1px); }
			.landing-ico { width: 40px; height: 40px; display: inline-flex; align-items: center; justify-content: center; border-radius: 14px; font-size: 18px; }
			.landing-ico-sm { width: 34px; height: 34px; display: inline-flex; align-items: center; justify-content: center; border-radius: 12px; font-size: 16px; }
			.landing-stat { transition: transform .25s ease, box-shadow .25s ease; }
			.landing-stat:hover { transform: translateY(-3px); box-shadow: 0 1rem 2rem rgba(15,23,42,.10) !important; }
			.landing-swiper .swiper-pagination-bullet { background: rgba(15,23,42,.2); opacity: 1; }
			.landing-swiper .swiper-pagination-bullet-active { background: var(--landing-primary); }
			.landing-step {
				width: 52px;
				height: 52px;
				border-radius: 18px;
				display: inline-flex;
				align-items: center;
				justify-content: center;
				font-weight: 800;
				background: linear-gradient(135deg, rgba(var(--landing-primary-rgb), .14), rgba(14,165,164,.14));
				color: var(--landing-primary);
				margin-bottom: .95rem;
			}
			.landing-mock-head {
				background: linear-gradient(135deg, rgba(var(--landing-primary-rgb), .12), rgba(14,165,164,.12));
				border: 1px solid rgba(15,23,42,.06);
			}
			.landing-dot { width: 8px; height: 8px; border-radius: 999px; background: rgba(15,23,42,.18); }
			.landing-mini-chart { height: 74px; display: flex; align-items: flex-end; gap: 6px; }
			.landing-mini-chart span {
				width: 10px;
				border-radius: 999px;
				background: linear-gradient(180deg, rgba(var(--landing-primary-rgb), .95), rgba(var(--landing-primary-rgb), .22));
				box-shadow: 0 10px 18px rgba(var(--landing-primary-rgb), .16);
			}
			.landing-activity > div + div { border-top: 1px dashed rgba(15,23,42,.08); }
			.landing-next { border: 1px solid rgba(15,23,42,.08) !important; }
			.landing-cycle-toggle {
				background: rgba(255,255,255,.82) !important;
				box-shadow: 0 .8rem 1.6rem rgba(15,23,42,.06);
			}
			.landing-package-card {
				position: relative;
				overflow: hidden;
			}
			.landing-package-card::before {
				content: "";
				position: absolute;
				left: 1.25rem;
				right: 1.25rem;
				top: 0;
				height: 4px;
				border-radius: 999px;
				background: linear-gradient(90deg, var(--package-accent), rgba(var(--landing-primary-rgb), .85));
			}
			.landing-package-card .btn-primary {
				box-shadow: 0 .8rem 1.8rem rgba(var(--landing-primary-rgb), .16);
			}
			#faq .accordion-item {
				border-radius: 1rem;
				overflow: hidden;
				margin-bottom: .85rem;
			}
			#faq .accordion-button {
				background: transparent;
				font-weight: 600;
			}
			#faq .accordion-button:not(.collapsed) {
				color: var(--landing-primary);
				box-shadow: none;
			}
		background: radial-gradient(circle, rgba(var(--landing-primary-rgb), .28) 0%, rgba(var(--landing-primary-rgb), 0) 70%);
	}
	.landing-orb-two {
		width: 26rem;
			[data-parallax] { will-change: transform; }

			@media (max-width: 991.98px) {
				.landing-title { max-width: none; }
				.landing-brand small { display: none !important; }
				.landing-section { padding-block: 4rem !important; }
			}

			@media (max-width: 575.98px) {
				.landing-proof-list span {
					width: 100%;
					justify-content: flex-start;
				}
				.landing-brand-mark {
					width: 2.5rem;
					height: 2.5rem;
				}
			}

		height: 26rem;
		top: 12rem;
				[data-parallax] { transform: none !important; }
				.landing-card, .landing-feature, .landing-mock, .landing-stat { transition: none; }
		background: radial-gradient(circle, rgba(14,165,164,.20) 0%, rgba(14,165,164,0) 70%);
	}
	.landing-nav {
		position: sticky;
		top: 0;
		z-index: 10;
		backdrop-filter: blur(18px);
		background: rgba(255,255,255,.72);
		border-bottom: 1px solid rgba(15,23,42,.08);
	}
	.landing-nav .container {
		gap: 1rem;
	}
	.landing-nav .navbar-toggler {
		border: 1px solid rgba(15,23,42,.12);
		border-radius: 1rem;
		padding: .7rem .85rem;
		box-shadow: none;
	}
	.landing-nav .navbar-toggler:focus {
		box-shadow: 0 0 0 .2rem rgba(var(--landing-primary-rgb), .14);
	}
	.landing-nav-panel {
		transition: opacity .2s ease, transform .2s ease;
	}
	.landing-brand small {
		font-size: .64rem;
		letter-spacing: .18em;
		color: rgba(15,23,42,.5);
		margin-bottom: .2rem;
	}
	.landing-brand-mark {
		width: 2.8rem;
		height: 2.8rem;
		display: inline-flex;
		align-items: center;
		justify-content: center;
		border-radius: 1rem;
		background: linear-gradient(135deg, rgba(var(--landing-primary-rgb), .94), rgba(14,165,164,.96));
		color: #fff;
		box-shadow: 0 1rem 2rem rgba(var(--landing-primary-rgb), .18);
	}
	.landing-nav-ghost,
	.landing-nav-cta {
		border-radius: 999px;
		padding-inline: 1rem;
	}
	.landing-nav-cta {
		box-shadow: 0 .9rem 2rem rgba(var(--landing-primary-rgb), .18);
	}
	.landing-pill {
		background: linear-gradient(90deg, rgba(var(--landing-primary-rgb), .10), rgba(14,165,164,.12));
		color: var(--landing-primary);
		border: 1px solid rgba(var(--landing-primary-rgb), .12);
		border-radius: 999px;
	}
	.landing-hero {
		position: relative;
		padding-top: 4rem !important;
		padding-bottom: 4rem !important;
	}
	.landing-title {
		letter-spacing: -0.04em;
		line-height: 1.02;
		max-width: 12ch;
		color: var(--landing-ink);
	}
	.landing-proof-list span {
		display: inline-flex;
		align-items: center;
		padding: .72rem 1rem;
		border-radius: 999px;
		background: rgba(255,255,255,.8);
		border: 1px solid rgba(15,23,42,.08);
		color: #334155;
		font-size: .95rem;
		box-shadow: 0 .8rem 2rem rgba(15,23,42,.06);
	}
	.landing-gradient-text {
		background: linear-gradient(90deg, rgba(var(--landing-primary-rgb), 1), rgba(14,165,164,.95));
		-webkit-background-clip: text;
		background-clip: text;
		color: transparent;
	}
	.landing-hero-actions .btn {
		padding-inline: 1.35rem;
	}
	.landing-trust-strip {
		display: flex;
		flex-wrap: wrap;
		gap: .85rem 1.25rem;
		align-items: center;
		color: #475569;
		font-size: .95rem;
	}
	.landing-trust-strip span {
		display: inline-flex;
		align-items: center;
	}
	.landing-mock-stage {
		position: relative;
	}
	.landing-mock-aura {
		position: absolute;
		inset: -1rem;
		border-radius: 2rem;
		background: linear-gradient(135deg, rgba(var(--landing-primary-rgb), .18), rgba(14,165,164,.16), rgba(var(--landing-primary-rgb), .08));
		filter: blur(28px);
		opacity: .9;
		pointer-events: none;
	}
	.landing-float-card {
		position: absolute;
		z-index: 3;
		align-items: center;
		gap: .85rem;
		padding: .95rem 1rem;
		border-radius: 1.1rem;
		background: rgba(255,255,255,.92);
		border: 1px solid rgba(255,255,255,.72);
		box-shadow: 0 1rem 2rem rgba(15,23,42,.12);
		backdrop-filter: blur(18px);
	}
	.landing-float-card-top {
		top: 2rem;
		left: -2.4rem;
	}
	.landing-float-card-bottom {
		right: -2.2rem;
		bottom: 2rem;
	}
	.landing-section {
		position: relative;
		padding-block: 5.5rem !important;
	}
	.landing-section-soft {
		background: linear-gradient(180deg, rgba(255,255,255,.28), rgba(248,250,252,.88));
	}
	.landing-section-kicker {
		display: inline-flex;
		align-items: center;
		gap: .5rem;
		padding: .45rem .9rem;
		border-radius: 999px;
		font-size: .72rem;
		letter-spacing: .16em;
		text-transform: uppercase;
		font-weight: 700;
		color: var(--landing-primary);
		background: rgba(var(--landing-primary-rgb), .08);
		margin-bottom: .9rem;
	}
	.landing-section-title {
		letter-spacing: -.03em;
		color: var(--landing-ink);
		max-width: 18ch;
	}
	.landing-section-copy {
		max-width: 60ch;
		font-size: 1rem;
		color: var(--landing-muted) !important;
	}
	.landing-surface,
	.landing-mock,
	.landing-card,
	.landing-feature,
	.landing-swiper .card,
	#how .card,
	#faq .accordion-item {
		background: rgba(255,255,255,.82);
		backdrop-filter: blur(18px);
		border: 1px solid rgba(255,255,255,.65);
		box-shadow: 0 1.2rem 3rem rgba(15,23,42,.08) !important;
	}
	.landing-mock { transform: translateY(0); transition: transform .35s ease, box-shadow .35s ease; }
	.landing-mock:hover { transform: translateY(-6px); box-shadow: 0 1.4rem 3rem rgba(15,23,42,.14) !important; }
	.landing-card { transform: translateY(0); transition: transform .25s ease, box-shadow .25s ease; }
	.landing-card:hover { transform: translateY(-5px); box-shadow: 0 1.2rem 2.6rem rgba(15,23,42,.12) !important; }
	.landing-feature { transition: transform .25s ease, box-shadow .25s ease, border-color .25s ease; }
	.landing-feature:hover { transform: translateY(-4px); border-color: rgba(var(--landing-primary-rgb), .18); box-shadow: 0 1.1rem 2.6rem rgba(15,23,42,.10) !important; }
	.landing-kpi { transition: transform .25s ease; background: linear-gradient(180deg, rgba(248,250,252,.96), rgba(255,255,255,.88)) !important; }
	.landing-mock:hover .landing-kpi { transform: translateY(-1px); }
	.landing-ico { width: 40px; height: 40px; display: inline-flex; align-items: center; justify-content: center; border-radius: 14px; font-size: 18px; }
	.landing-ico-sm { width: 34px; height: 34px; display: inline-flex; align-items: center; justify-content: center; border-radius: 12px; font-size: 16px; }
	.landing-stat { transition: transform .25s ease, box-shadow .25s ease; }
	.landing-stat:hover { transform: translateY(-3px); box-shadow: 0 1rem 2rem rgba(15,23,42,.10) !important; }
	.landing-swiper .swiper-pagination-bullet { background: rgba(15,23,42,.2); opacity: 1; }
	.landing-swiper .swiper-pagination-bullet-active { background: var(--landing-primary); }
	.landing-step {
		width: 52px;
		height: 52px;
		border-radius: 18px;
		display: inline-flex;
		align-items: center;
		justify-content: center;
		font-weight: 800;
		background: linear-gradient(135deg, rgba(var(--landing-primary-rgb), .14), rgba(14,165,164,.14));
		color: var(--landing-primary);
		margin-bottom: .95rem;
	}
	.landing-mock-head {
		background: linear-gradient(135deg, rgba(var(--landing-primary-rgb), .12), rgba(14,165,164,.12));
		border: 1px solid rgba(15,23,42,.06);
	}
	.landing-dot { width: 8px; height: 8px; border-radius: 999px; background: rgba(15,23,42,.18); }
	.landing-mini-chart { height: 74px; display: flex; align-items: flex-end; gap: 6px; }
	.landing-mini-chart span {
		width: 10px;
		border-radius: 999px;
		background: linear-gradient(180deg, rgba(var(--landing-primary-rgb), .95), rgba(var(--landing-primary-rgb), .22));
		box-shadow: 0 10px 18px rgba(var(--landing-primary-rgb), .16);
	}
	.landing-activity > div + div { border-top: 1px dashed rgba(15,23,42,.08); }
	.landing-next { border: 1px solid rgba(15,23,42,.08) !important; }
	.landing-cycle-toggle {
		background: rgba(255,255,255,.82) !important;
		box-shadow: 0 .8rem 1.6rem rgba(15,23,42,.06);
	}
	.landing-package-card {
		position: relative;
		overflow: hidden;
	}
	.landing-package-card.is-recommended {
		transform: translateY(-12px) scale(1.02);
		border: 1px solid rgba(var(--landing-primary-rgb), .26) !important;
		box-shadow: 0 1.6rem 3.8rem rgba(var(--landing-primary-rgb), .16) !important;
	}
	.landing-package-card::before {
		content: "";
		position: absolute;
		left: 1.25rem;
		right: 1.25rem;
		top: 0;
		height: 4px;
		border-radius: 999px;
		background: linear-gradient(90deg, var(--package-accent), rgba(var(--landing-primary-rgb), .85));
	}
	.landing-package-card .btn-primary {
		box-shadow: 0 .8rem 1.8rem rgba(var(--landing-primary-rgb), .16);
	}
	.landing-package-badge {
		position: absolute;
		top: 1rem;
		right: 1rem;
		z-index: 2;
		padding: .4rem .75rem;
		border-radius: 999px;
		font-size: .72rem;
		font-weight: 700;
		letter-spacing: .08em;
		text-transform: uppercase;
		color: #fff;
		background: linear-gradient(90deg, var(--landing-accent), var(--landing-primary));
		box-shadow: 0 .6rem 1.3rem rgba(var(--landing-primary-rgb), .22);
	}
	.landing-pricing-caption {
		max-width: 42rem;
		font-size: .92rem;
		color: #64748b;
	}
	.landing-final-cta {
		border: 1px solid rgba(var(--landing-primary-rgb), .18) !important;
		background: linear-gradient(135deg, rgba(255,255,255,.95), rgba(247,250,252,.92), rgba(239,246,255,.92));
	}
	.landing-final-cta-orb {
		position: absolute;
		border-radius: 999px;
		filter: blur(10px);
		pointer-events: none;
	}
	.landing-final-cta-orb-one {
		width: 14rem;
		height: 14rem;
		top: -5rem;
		right: 4rem;
		background: radial-gradient(circle, rgba(var(--landing-primary-rgb), .18), rgba(var(--landing-primary-rgb), 0) 72%);
	}
	.landing-final-cta-orb-two {
		width: 12rem;
		height: 12rem;
		bottom: -4rem;
		left: 2rem;
		background: radial-gradient(circle, rgba(14,165,164,.16), rgba(14,165,164,0) 72%);
	}
	.landing-final-checks {
		display: flex;
		flex-wrap: wrap;
		gap: .85rem 1.1rem;
		color: #475569;
		font-size: .92rem;
	}
	.landing-final-checks span {
		display: inline-flex;
		align-items: center;
	}
	#faq .accordion-item {
		border-radius: 1rem;
		overflow: hidden;
		margin-bottom: .85rem;
	}
	#faq .accordion-button {
		background: transparent;
		font-weight: 600;
	}
	#faq .accordion-button:not(.collapsed) {
		color: var(--landing-primary);
		box-shadow: none;
	}

	/* Reveal animation */
	[data-reveal] { opacity: 0; transform: translateY(14px); transition: opacity .6s ease, transform .6s ease; }
	[data-reveal].is-visible { opacity: 1; transform: translateY(0); }
	[data-parallax] { will-change: transform; }

	@media (max-width: 991.98px) {
		.landing-nav {
			padding-block: .75rem;
		}
		.landing-nav .container {
			align-items: center;
		}
		.landing-nav-panel {
			width: 100%;
		}
		.landing-nav .navbar-collapse:not(.show) {
			display: none !important;
		}
		.landing-nav .navbar-collapse.show {
			display: block;
			margin-top: 1rem;
			padding: 1rem;
			border-radius: 1.25rem;
			background: rgba(255,255,255,.94);
			border: 1px solid rgba(15,23,42,.08);
			box-shadow: 0 1rem 2.4rem rgba(15,23,42,.10);
			backdrop-filter: blur(16px);
		}
		.landing-nav .navbar-nav {
			align-items: stretch !important;
			gap: .35rem;
		}
		.landing-nav .nav-link {
			padding: .8rem .25rem;
			font-weight: 600;
		}
		.landing-nav .nav-item.ms-lg-2 {
			margin-left: 0 !important;
		}
		.landing-nav-ghost,
		.landing-nav-cta {
			width: 100%;
			justify-content: center;
			padding-block: .78rem;
		}
		.landing-hero {
			padding-top: 2.25rem !important;
			padding-bottom: 3rem !important;
		}
		.landing-title { max-width: none; }
		.landing-brand small { display: none !important; }
		.landing-section { padding-block: 4rem !important; }
		.landing-package-card.is-recommended { transform: none; }
	}

	@media (max-width: 575.98px) {
		.landing-nav .container {
			gap: .75rem;
		}
		.landing-brand {
			max-width: calc(100% - 5rem);
		}
		.landing-brand > span:last-child {
			min-width: 0;
		}
		.landing-brand > span:last-child > span:last-child {
			display: block;
			font-size: 1rem;
			line-height: 1.2;
			word-break: break-word;
		}
		.landing-nav .navbar-toggler {
			padding: .65rem .8rem;
		}
		.landing-nav .navbar-collapse.show {
			padding: .9rem;
			border-radius: 1rem;
		}
		.landing-hero {
			padding-top: 1.5rem !important;
		}
		.landing-title {
			font-size: clamp(2.35rem, 12vw, 3.4rem);
			line-height: .98;
		}
		.landing-hero-actions .btn {
			width: 100%;
			justify-content: center;
		}
		.landing-proof-list span {
			width: 100%;
			justify-content: flex-start;
		}
		.landing-trust-strip {
			flex-direction: column;
			align-items: flex-start;
		}
		.landing-final-checks {
			flex-direction: column;
		}
		.landing-brand-mark {
			width: 2.5rem;
			height: 2.5rem;
		}
	}

	@media (prefers-reduced-motion: reduce) {
		[data-reveal] { opacity: 1; transform: none; transition: none; }
		[data-parallax] { transform: none !important; }
		.landing-card, .landing-feature, .landing-mock, .landing-stat { transition: none; }
	}

	.landing-react-root {
		display: none;
	}

	.landing-react-loading {
		min-height: 100vh;
		display: flex;
		flex-direction: column;
		align-items: center;
		justify-content: center;
		gap: .9rem;
		padding: 7rem 1.5rem 3rem;
		text-align: center;
		background: linear-gradient(180deg, rgba(251,252,255,.96), rgba(245,248,252,.98));
		color: #111827;
	}

	.landing-react-loading__mark {
		width: 3rem;
		height: 3rem;
		display: inline-flex;
		align-items: center;
		justify-content: center;
		border-radius: 1rem;
		background: linear-gradient(135deg, #2563eb, #4f46e5 60%, #f97316);
		color: #fff;
		box-shadow: 0 1rem 2rem rgba(37,99,235,.20);
	}

	.landing-react-loading__eyebrow {
		font-size: .76rem;
		font-weight: 700;
		letter-spacing: .14em;
		text-transform: uppercase;
		color: #2563eb;
	}

	.landing-react-loading__title {
		font-size: clamp(1.5rem, 4vw, 2.25rem);
		font-weight: 700;
		letter-spacing: -.03em;
	}

	.landing-react-ready .landing-react-root {
		display: block;
	}

	.landing-react-ready [data-landing-fallback] {
		display: none !important;
	}
</style>

<script src="{{ url('build/js/vendor/bootstrap.bundle.min.js') }}"></script>
<script src="{{ url('build/js/core/api-client.js') }}"></script>
<script src="{{ url('build/js/core/arcav-validation.js') }}"></script>
<link rel="stylesheet" href="{{ url('build/css/public-landing-react.min.css') }}?v={{ file_exists(public_path('build/css/public-landing-react.min.css')) ? filemtime(public_path('build/css/public-landing-react.min.css')) : time() }}">
<link rel="stylesheet" href="{{ url('build/vendor/swiper-bundle.min.css') }}?v={{ file_exists(public_path('build/vendor/swiper-bundle.min.css')) ? filemtime(public_path('build/vendor/swiper-bundle.min.css')) : time() }}">
<script src="{{ url('build/vendor/swiper-bundle.min.js') }}?v={{ file_exists(public_path('build/vendor/swiper-bundle.min.js')) ? filemtime(public_path('build/vendor/swiper-bundle.min.js')) : time() }}"></script>
<script src="{{ url('build/vendor/countUp.umd.js') }}?v={{ file_exists(public_path('build/vendor/countUp.umd.js')) ? filemtime(public_path('build/vendor/countUp.umd.js')) : time() }}"></script>
<script src="{{ url('build/js/public/landing-vendor-init.js') }}?v={{ file_exists(public_path('build/js/public/landing-vendor-init.js')) ? filemtime(public_path('build/js/public/landing-vendor-init.js')) : time() }}"></script>
<script src="{{ url('build/js/public/public-landing-onboarding.js') }}?v={{ file_exists(public_path('build/js/public/public-landing-onboarding.js')) ? filemtime(public_path('build/js/public/public-landing-onboarding.js')) : time() }}"></script>
<script type="module" src="{{ url('build/js/public-landing-react.js') }}?v={{ file_exists(public_path('build/js/public-landing-react.js')) ? filemtime(public_path('build/js/public-landing-react.js')) : time() }}"></script>
@endsection

