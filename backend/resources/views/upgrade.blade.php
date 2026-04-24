<?php $page = 'upgrade'; ?>
@extends('layout.mainlayout')
@section('content')

<!-- Page Wrapper -->
<div class="page-wrapper">
    <div class="content">

        <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
            <div class="my-auto mb-2">
                <h2 class="mb-1">Upgrade Paket</h2>
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="{{ url('index') }}"><i class="ti ti-smart-home"></i></a></li>
                        <li class="breadcrumb-item">Billing</li>
                        <li class="breadcrumb-item active" aria-current="page">Upgrade</li>
                    </ol>
                </nav>
            </div>
        </div>

        @php
            $blocked = request()->query('blocked');
        @endphp

        @if ($blocked)
            <div class="alert alert-warning d-flex align-items-start" role="alert">
                <i class="ti ti-alert-triangle me-2 fs-20"></i>
                <div>
                    <div class="fw-semibold mb-1">Fitur "<span data-testid="blocked-feature">{{ $blocked }}</span>" belum termasuk dalam paket aktif.</div>
                    <div class="small text-muted">Silakan ajukan upgrade untuk membuka akses fitur tersebut.</div>
                </div>
            </div>
        @endif

        @php
            $authUser = request()->user() ?: auth()->user();
            $primarySuperAdminEmail = strtolower(trim((string) config('hcm.admin_email', 'qa.login@example.com')));
            $authUserEmail = strtolower(trim((string) ($authUser->email ?? '')));
            $isPrimarySuperAdminCodeOne = (bool) ($authUser && $authUserEmail === $primarySuperAdminEmail);

            $normalizedBlockedFeature = strtolower(trim((string) $blocked));
            $normalizedBlockedFeature = str_replace(['-', ' '], '_', $normalizedBlockedFeature);
            $normalizedBlockedFeature = preg_replace('/[^a-z0-9_]/', '', $normalizedBlockedFeature) ?? '';

            $recommendedPackages = collect();
            if ($normalizedBlockedFeature !== '') {
                $recommendedPackages = \App\Models\Package::query()
                    ->where('status', 'active')
                    ->whereHas('features', function ($featureQuery) use ($normalizedBlockedFeature): void {
                        $featureQuery->where('feature_code', $normalizedBlockedFeature);
                    })
                    ->orderByRaw('COALESCE(monthly_price, 0) asc')
                    ->get(['uuid', 'name', 'code', 'monthly_price', 'yearly_price']);
            }
        @endphp

        <div id="upgrade-page-context"
             data-blocked-feature="{{ $normalizedBlockedFeature }}"
             data-is-primary-super-admin="{{ $isPrimarySuperAdminCodeOne ? '1' : '0' }}"
             data-recommended-packages='@json($recommendedPackages)'>
        </div>

        @if ($blocked && $recommendedPackages->isNotEmpty())
            <div class="alert alert-info" role="alert" data-upgrade-recommended-alert>
                <div class="fw-semibold mb-1">Target paket yang mendukung fitur ini:</div>
                <ul class="mb-0 ps-3 small">
                    @foreach ($recommendedPackages as $package)
                        <li>
                            <span class="fw-semibold">{{ $package->name }}</span>
                            ({{ $package->code }})
                            - Bulanan Rp {{ number_format((float) ($package->monthly_price ?? 0), 0, ',', '.') }}
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
        @endif

        <div class="card">
            <div class="card-header">
                <h4 class="card-title mb-0">Ajukan Perubahan Paket</h4>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">
                    Pilih paket target, sistem akan menampilkan preview harga & jadwal berlakunya. Permintaan
                    diteruskan ke admin platform untuk approval. Jika sudah disetujui, paket baru akan aktif
                    pada tanggal yang ditampilkan di preview.
                </p>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Aksi</label>
                        <select id="upgrade-action" class="form-select">
                            <option value="upgrade">Upgrade / Ganti Paket</option>
                            <option value="downgrade">Downgrade</option>
                            <option value="cancel">Cancel Subscription</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Paket Target</label>
                        <select id="upgrade-target-package" class="form-select"></select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Catatan (opsional)</label>
                        <textarea id="upgrade-notes" class="form-control" rows="2" maxlength="500"
                                  placeholder="Alasan upgrade / informasi tambahan"></textarea>
                    </div>
                    <div class="col-12 d-flex gap-2">
                        <button type="button" class="btn btn-outline-primary" id="upgrade-preview-btn">
                            <i class="ti ti-eye me-1"></i>Preview
                        </button>
                        <button type="button" class="btn btn-primary" id="upgrade-submit-btn">
                            <i class="ti ti-send me-1"></i>Ajukan Upgrade
                        </button>
                    </div>
                </div>

                <div id="upgrade-preview-pane" class="mt-3 d-none">
                    <h5 class="mb-2">Preview</h5>
                    <pre class="bg-light p-3 rounded small" id="upgrade-preview-content"></pre>
                </div>

                <div id="upgrade-status-pane" class="mt-3"></div>

                <div class="mt-4">
                    <h5 class="mb-2">Riwayat Pengajuan Saya</h5>
                    <div id="upgrade-request-list" class="border rounded p-3 small text-muted">Memuat data...</div>
                </div>

                @if ($isPrimarySuperAdminCodeOne)
                    <div class="mt-4">
                        <h5 class="mb-2">Pengajuan Upgrade Baru (Admin Code 1)</h5>
                        <div id="upgrade-admin-queue" class="border rounded p-3 small text-muted">Memuat data...</div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

@endsection
