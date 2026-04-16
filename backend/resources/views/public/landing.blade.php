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
						<a href="{{ url('/login') }}" class="btn btn-outline-secondary btn-sm">Login</a>
					</li>
					<li class="nav-item">
						<a href="#pricing" class="btn btn-primary btn-sm">Mulai</a>
					</li>
				</ul>
			</div>
		</div>
	</nav>

	<section class="landing-hero py-5">
		<div class="container">
			<div class="row align-items-center g-4">
				<div class="col-lg-6" data-reveal>
					<div class="mb-3">
						<span class="badge bg-primary-subtle text-primary fw-semibold px-3 py-2">HCM + SaaS • Self-serve onboarding</span>
					</div>
					<h1 class="display-5 fw-bold mb-3 landing-title">
						Satu platform untuk HR, Absensi, Cuti, Payroll, dan laporan—siap dipakai.
					</h1>
					<p class="text-muted fs-5 mb-4">
						Pilih paket. Buat company & owner. Mulai trial atau langsung subscribe. Semua lewat alur yang rapi dan aman.
					</p>

					<div class="d-flex flex-wrap gap-2">
						<a href="#pricing" class="btn btn-primary btn-lg">
							Lihat Paket
						</a>
						<a href="{{ url('/trial') }}" class="btn btn-outline-secondary btn-lg">
							Coba Trial Gratis!!
						</a>
					</div>

					<div class="landing-badges d-flex flex-wrap gap-2 mt-4">
						<span class="badge bg-light text-dark border"><i class="ti ti-shield-check me-1"></i>RBAC admin vs karyawan</span>
						<span class="badge bg-light text-dark border"><i class="ti ti-device-laptop me-1"></i>UI selaras template</span>
						<span class="badge bg-light text-dark border"><i class="ti ti-mail-forward me-1"></i>Invoice email (opsional)</span>
					</div>

					<div class="row g-2 mt-4" data-reveal>
						<div class="col-4">
							<div class="p-3 rounded-3 bg-white border landing-stat">
								<div class="text-muted small">Modul siap</div>
								<div class="h4 fw-bold mb-0" data-countup data-countup-end="6" data-countup-suffix="+">0+</div>
							</div>
						</div>
						<div class="col-4">
							<div class="p-3 rounded-3 bg-white border landing-stat">
								<div class="text-muted small">Onboarding</div>
								<div class="h4 fw-bold mb-0" data-countup data-countup-end="1" data-countup-suffix=" menit">0</div>
							</div>
						</div>
						<div class="col-4">
							<div class="p-3 rounded-3 bg-white border landing-stat">
								<div class="text-muted small">Audit-ready</div>
								<div class="h4 fw-bold mb-0" data-countup data-countup-end="100" data-countup-suffix="%">0%</div>
							</div>
						</div>
					</div>
				</div>

				<div class="col-lg-6" data-reveal>
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
									<span class="badge bg-success-subtle text-success"><i class="ti ti-bolt me-1"></i>HCM ready</span>
								</div>
								<div class="d-flex flex-wrap gap-2 mt-3">
									<span class="badge bg-light text-dark border"><i class="ti ti-layout-dashboard me-1"></i>Overview</span>
									<span class="badge bg-light text-dark border"><i class="ti ti-users me-1"></i>Employees</span>
									<span class="badge bg-light text-dark border"><i class="ti ti-fingerprint me-1"></i>Attendance</span>
									<span class="badge bg-light text-dark border"><i class="ti ti-receipt-2 me-1"></i>Payroll</span>
								</div>
							</div>

							<div class="row g-3">
								<div class="col-7">
									<div class="p-3 rounded-3 bg-light landing-kpi h-100">
										<div class="d-flex align-items-center justify-content-between">
											<div class="fw-semibold">Ringkasan minggu ini</div>
											<i class="ti ti-chart-line text-primary"></i>
										</div>
										<div class="text-muted small mt-1">Aktivitas & progress modul HCM</div>

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
												<div class="text-muted small">Employees</div>
												<div class="fw-bold">—</div>
											</div>
											<div class="flex-fill p-2 rounded-3 bg-white border">
												<div class="text-muted small">Attendance</div>
												<div class="fw-bold">—</div>
											</div>
										</div>
									</div>
								</div>
								<div class="col-5">
									<div class="p-3 rounded-3 bg-light landing-kpi h-100">
										<div class="d-flex align-items-center justify-content-between">
											<div class="fw-semibold">Aktivitas terbaru</div>
											<i class="ti ti-bell-ringing text-warning"></i>
										</div>
										<div class="landing-activity mt-2">
											<div class="d-flex align-items-center gap-2 py-2">
												<span class="landing-ico-sm bg-primary-subtle text-primary"><i class="ti ti-user-plus"></i></span>
												<div class="small">
													<div class="fw-semibold">Employee ditambahkan</div>
													<div class="text-muted">Directory update</div>
												</div>
											</div>
											<div class="d-flex align-items-center gap-2 py-2">
												<span class="landing-ico-sm bg-success-subtle text-success"><i class="ti ti-calendar-check"></i></span>
												<div class="small">
													<div class="fw-semibold">Leave request</div>
													<div class="text-muted">Approval flow</div>
												</div>
											</div>
											<div class="d-flex align-items-center gap-2 py-2">
												<span class="landing-ico-sm bg-warning-subtle text-warning"><i class="ti ti-receipt-2"></i></span>
												<div class="small">
													<div class="fw-semibold">Payroll draft</div>
													<div class="text-muted">Ready to finalize</div>
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
										<div class="small text-muted">Next step</div>
										<div class="fw-semibold">Mulai onboarding</div>
										<div class="text-muted small">Buat company + owner, pilih trial / subscribe.</div>
									</div>
									<a class="btn btn-sm btn-primary" href="{{ url('/trial') }}">
										<i class="ti ti-rocket me-1"></i> Mulai
									</a>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</section>

	<section class="py-5">
		<div class="container">
			<div class="row align-items-end g-3 mb-4" data-reveal>
				<div class="col-lg-8">
					<h2 class="h3 fw-bold mb-2">Apa yang tim kamu rasakan</h2>
					<p class="text-muted mb-0">Bukan janji kosong—ini ringkasan outcome yang biasanya dicari tim HR & finance.</p>
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

	<section id="features" class="py-5">
		<div class="container">
			<div class="row align-items-end g-3 mb-4" data-reveal>
				<div class="col-lg-8">
					<h2 class="h3 fw-bold mb-2">Fitur lengkap, bukan sekadar landing</h2>
					<p class="text-muted mb-0">Ini modul nyata yang sudah ada di sistem—bukan janji kosong.</p>
				</div>
			</div>

			<div class="row g-3">
				<div class="col-md-6 col-lg-4" data-reveal>
					<div class="card h-100 border-0 shadow-sm landing-feature">
						<div class="card-body p-4">
							<div class="d-flex align-items-center gap-2 mb-2">
								<span class="landing-ico bg-primary-subtle text-primary"><i class="ti ti-users"></i></span>
								<div class="fw-semibold">Employee & Organisasi</div>
							</div>
							<div class="text-muted">Direktori karyawan, department, designation, policy, dan detail employee yang rapi.</div>
						</div>
					</div>
				</div>
				<div class="col-md-6 col-lg-4" data-reveal>
					<div class="card h-100 border-0 shadow-sm landing-feature">
						<div class="card-body p-4">
							<div class="d-flex align-items-center gap-2 mb-2">
								<span class="landing-ico bg-success-subtle text-success"><i class="ti ti-fingerprint"></i></span>
								<div class="fw-semibold">Attendance</div>
							</div>
							<div class="text-muted">Punch in/out, timesheets, schedule timing, dan kontrol admin yang jelas.</div>
						</div>
					</div>
				</div>
				<div class="col-md-6 col-lg-4" data-reveal>
					<div class="card h-100 border-0 shadow-sm landing-feature">
						<div class="card-body p-4">
							<div class="d-flex align-items-center gap-2 mb-2">
								<span class="landing-ico bg-info-subtle text-info"><i class="ti ti-calendar-time"></i></span>
								<div class="fw-semibold">Leave & Holidays</div>
							</div>
							<div class="text-muted">Leave request self vs admin approval, leave settings, dan holidays sync baseline.</div>
						</div>
					</div>
				</div>
				<div class="col-md-6 col-lg-4" data-reveal>
					<div class="card h-100 border-0 shadow-sm landing-feature">
						<div class="card-body p-4">
							<div class="d-flex align-items-center gap-2 mb-2">
								<span class="landing-ico bg-warning-subtle text-warning"><i class="ti ti-receipt-2"></i></span>
								<div class="fw-semibold">Payroll Run + THR</div>
							</div>
							<div class="text-muted">Draft, finalize, disburse, payslip self, dan modul THR batch sesuai kebutuhan lokal.</div>
						</div>
					</div>
				</div>
				<div class="col-md-6 col-lg-4" data-reveal>
					<div class="card h-100 border-0 shadow-sm landing-feature">
						<div class="card-body p-4">
							<div class="d-flex align-items-center gap-2 mb-2">
								<span class="landing-ico bg-secondary-subtle text-secondary"><i class="ti ti-chart-bar"></i></span>
								<div class="fw-semibold">Reports</div>
							</div>
							<div class="text-muted">Reporting hub (live vs archive) untuk kebutuhan audit dan rekap.</div>
						</div>
					</div>
				</div>
				<div class="col-md-6 col-lg-4" data-reveal>
					<div class="card h-100 border-0 shadow-sm landing-feature">
						<div class="card-body p-4">
							<div class="d-flex align-items-center gap-2 mb-2">
								<span class="landing-ico bg-danger-subtle text-danger"><i class="ti ti-user-star"></i></span>
								<div class="fw-semibold">SaaS Billing (Admin)</div>
							</div>
							<div class="text-muted">Trial vs subscribed overview, invoice, resend email, dan kontrol status yang jelas.</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</section>

	<section id="solutions" class="py-5">
		<div class="container">
			<div class="row align-items-end g-3 mb-4" data-reveal>
				<div class="col-lg-8">
					<h2 class="h3 fw-bold mb-2">Solusi per role</h2>
					<p class="text-muted mb-0">Biar jelas: admin HR, karyawan, dan finance punya kebutuhan berbeda.</p>
				</div>
			</div>

			<div class="card border-0 shadow-sm" data-reveal>
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
										<div class="text-muted small">RBAC jelas: karyawan hanya bisa akses data miliknya.</div>
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

	<section id="how" class="py-5 bg-light">
		<div class="container">
			<div class="row g-3 mb-4" data-reveal>
				<div class="col-lg-8">
					<h2 class="h3 fw-bold mb-2">Cara kerja (end-to-end)</h2>
					<p class="text-muted mb-0">Dari pilih paket sampai siap dipakai—tanpa ribet.</p>
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
				<div class="col-md-6 col-lg-3" data-reveal>
					<div class="card h-100 border-0 shadow-sm">
						<div class="card-body p-4">
							<div class="landing-step">03</div>
							<div class="fw-semibold mb-1">Trial / Pending payment</div>
							<div class="text-muted small">Mulai trial atau langsung invoice bila pilih subscribe.</div>
						</div>
					</div>
				</div>
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

	<section id="pricing" class="py-5">
		<div class="container">
			<div class="row align-items-end g-3 mb-4" data-reveal>
				<div class="col-lg-8">
					<h2 class="h3 fw-bold mb-2">Pilih paket</h2>
					<p class="text-muted mb-0">Paket real dari sistem (DB). Upgrade kapan saja.</p>
				</div>
				<div class="col-lg-4 text-lg-end" data-reveal>
					<div class="d-inline-flex align-items-center gap-2 p-2 rounded-3 bg-light border">
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
				<div class="card h-100 border-0 shadow-sm landing-card" data-reveal>
					<div class="card-body p-4">
						<div class="d-flex align-items-start justify-content-between">
							<div>
								<div class="fw-bold">{{ $package->name }}</div>
								<div class="text-muted small">{{ $package->code }}</div>
							</div>
							<span class="badge bg-success-subtle text-success">Active</span>
						</div>

						@if ($package->description)
							<p class="text-muted mt-3 mb-3">{{ $package->description }}</p>
						@else
							<p class="text-muted mt-3 mb-3">Paket siap pakai untuk memulai operasional HR dengan rapi.</p>
						@endif

						<div class="d-flex flex-wrap gap-2 mb-3">
							<span class="badge bg-light text-dark border"><i class="ti ti-check me-1"></i>Dashboard</span>
							<span class="badge bg-light text-dark border"><i class="ti ti-check me-1"></i>Employees</span>
							<span class="badge bg-light text-dark border"><i class="ti ti-check me-1"></i>Attendance</span>
							<span class="badge bg-light text-dark border"><i class="ti ti-check me-1"></i>Leave</span>
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
								href="{{ url('/trial') }}?packageId={{ $package->id }}"
							>
								Mulai
							</a>
						</div>

						<div class="mt-3 small text-muted">
							<span class="me-2">Tahunan: Rp {{ number_format((float) $package->yearly_price, 0, ',', '.') }}</span>
							<span class="badge bg-primary-subtle text-primary">Hemat</span>
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
				<p class="text-muted mb-0">Detail berikut mengikuti konfigurasi `package_features` di sistem.</p>
			</div>

			@php
				$featureCatalog = [
					'employee_management' => ['label' => 'Employee Management', 'group' => 'Core HCM'],
					'attendance' => ['label' => 'Attendance', 'group' => 'Core HCM'],
					'leave_management' => ['label' => 'Leave Management', 'group' => 'Core HCM'],
					'payroll' => ['label' => 'Payroll', 'group' => 'Payroll'],
					'performance' => ['label' => 'Performance', 'group' => 'Performance'],
					'training' => ['label' => 'Training', 'group' => 'Performance'],
					'goal_tracking' => ['label' => 'Goal Tracking', 'group' => 'Performance'],
					'asset_management' => ['label' => 'Asset Management', 'group' => 'Asset'],
					'api_access' => ['label' => 'API Access', 'group' => 'Platform'],
					'priority_support' => ['label' => 'Priority Support', 'group' => 'Platform'],
				];

				$featureGroups = [];
				foreach ($featureCatalog as $code => $meta) {
					$featureGroups[$meta['group']][] = ['code' => $code, 'label' => $meta['label']];
				}

				$formatLimit = function ($limit) {
					if ($limit === null) return 'Unlimited';
					if ((int) $limit === 0) return '—';
					return (string) $limit;
				};
			@endphp

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
						<div class="text-muted">Klik “Mulai” pada paket pilihanmu, isi onboarding, lalu login sebagai owner.</div>
					</div>
					<div class="d-flex gap-2">
						<a class="btn btn-primary" href="{{ url('/trial') }}">Mulai onboarding</a>
						<a class="btn btn-outline-secondary" href="{{ url('/login') }}">Login</a>
					</div>
				</div>
			</div>
		</div>
	</section>

	<section id="faq" class="py-5 bg-light">
		<div class="container">
			<div class="row g-3 mb-4" data-reveal>
				<div class="col-lg-8">
					<h2 class="h3 fw-bold mb-2">FAQ</h2>
					<p class="text-muted mb-0">Jawaban cepat biar nggak bolak-balik nanya.</p>
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
							<select class="form-select" name="package_id" required data-onboarding-package></select>
						</div>
						<div class="col-md-3">
							<label class="form-label">Billing cycle <span class="text-danger">*</span></label>
							<select class="form-select" name="billing_cycle" required>
								<option value="monthly">Monthly</option>
								<option value="yearly">Yearly</option>
							</select>
							<div class="form-text">Trial otomatis <span class="fw-semibold">30 hari</span> untuk paket yang kamu pilih.</div>
						</div>
						<div class="col-md-3">
							<label class="form-label">Start mode</label>
							<select class="form-select" name="start_mode">
								<option value="trial">Trial</option>
								<option value="pending_payment">Pending payment</option>
							</select>
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
						<div class="col-md-6">
							<label class="form-label">Confirm password <span class="text-danger">*</span></label>
							<input type="password" class="form-control" name="owner_confirm_password" required minlength="8" maxlength="64">
						</div>
						<div class="col-12">
							<label class="form-label">Billing email (optional)</label>
							<input type="email" class="form-control" name="billing_email" maxlength="255" placeholder="billing@company.com">
						</div>
					</div>

					<div class="mt-4 p-3 rounded-3 bg-light">
						<div class="small text-muted">Catatan</div>
						<div class="fw-semibold">Setelah onboarding berhasil, silakan login pakai email owner untuk masuk ke aplikasi.</div>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
					<button type="submit" class="btn btn-primary" data-onboarding-submit>Proses</button>
				</div>
			</form>
			@if (config('turnstile.enabled') && config('turnstile.site_key'))
				<div class="px-4 pb-4">
					<div class="cf-turnstile" data-sitekey="{{ config('turnstile.site_key') }}"></div>
					<div class="form-text">Verifikasi keamanan untuk mencegah spam/bot.</div>
				</div>
				<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
			@endif
		</div>
	</div>
</div>

<style>
	.landing-shell { background: radial-gradient(1200px 600px at 20% 10%, rgba(45,127,249,.18), transparent 60%), radial-gradient(900px 500px at 80% 0%, rgba(0,167,111,.16), transparent 55%), #ffffff; }
	.landing-nav { position: sticky; top: 0; z-index: 10; backdrop-filter: blur(12px); background: rgba(255,255,255,.75); border-bottom: 1px solid rgba(0,0,0,.06); }
	.landing-hero { position: relative; }
	.landing-title { letter-spacing: -0.02em; }
	.landing-mock { transform: translateY(0); transition: transform .35s ease, box-shadow .35s ease; }
	.landing-mock:hover { transform: translateY(-4px); box-shadow: 0 1rem 2.5rem rgba(0,0,0,.14) !important; }
	.landing-card { transform: translateY(0); transition: transform .25s ease, box-shadow .25s ease; }
	.landing-card:hover { transform: translateY(-3px); box-shadow: 0 1rem 2rem rgba(0,0,0,.12) !important; }
	.landing-feature { transition: transform .25s ease, box-shadow .25s ease; }
	.landing-feature:hover { transform: translateY(-2px); box-shadow: 0 1rem 2rem rgba(0,0,0,.10) !important; }
	.landing-kpi { transition: transform .25s ease; }
	.landing-mock:hover .landing-kpi { transform: translateY(-1px); }
	.landing-ico { width: 40px; height: 40px; display: inline-flex; align-items: center; justify-content: center; border-radius: 12px; font-size: 18px; }
	.landing-ico-sm { width: 34px; height: 34px; display: inline-flex; align-items: center; justify-content: center; border-radius: 12px; font-size: 16px; }
	.landing-stat { transition: transform .25s ease, box-shadow .25s ease; }
	.landing-stat:hover { transform: translateY(-2px); box-shadow: 0 1rem 2rem rgba(0,0,0,.10) !important; }
	.landing-swiper .swiper-pagination-bullet { background: rgba(0,0,0,.25); opacity: 1; }
	.landing-swiper .swiper-pagination-bullet-active { background: #2D7FF9; }
	.landing-step { width: 44px; height: 44px; border-radius: 14px; display: inline-flex; align-items: center; justify-content: center; font-weight: 800; background: rgba(45,127,249,.12); color: #2D7FF9; margin-bottom: .75rem; }
	.landing-mock-head { background: linear-gradient(135deg, rgba(45,127,249,.14), rgba(0,167,111,.10)); border: 1px solid rgba(0,0,0,.06); }
	.landing-dot { width: 8px; height: 8px; border-radius: 999px; background: rgba(0,0,0,.18); }
	.landing-mini-chart { height: 74px; display: flex; align-items: flex-end; gap: 6px; }
	.landing-mini-chart span { width: 10px; border-radius: 999px; background: linear-gradient(180deg, rgba(45,127,249,.95), rgba(45,127,249,.25)); box-shadow: 0 10px 18px rgba(45,127,249,.18); }
	.landing-activity > div + div { border-top: 1px dashed rgba(0,0,0,.08); }
	.landing-next { border: 1px solid rgba(0,0,0,.08) !important; }

	/* Reveal animation */
	[data-reveal] { opacity: 0; transform: translateY(14px); transition: opacity .6s ease, transform .6s ease; }
	[data-reveal].is-visible { opacity: 1; transform: translateY(0); }
	@media (prefers-reduced-motion: reduce) {
		[data-reveal] { opacity: 1; transform: none; transition: none; }
		.landing-card, .landing-feature, .landing-mock { transition: none; }
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

