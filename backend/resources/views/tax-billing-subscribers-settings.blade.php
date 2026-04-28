<?php $page = 'tax-rates'; ?>
@php
    $taxGovernanceScreen = $taxGovernanceScreen ?? 'platform-billing';
    $taxGovernancePolicyUuid = null;
@endphp
@extends('layout.mainlayout')
@section('content')
<div class="page-wrapper"
     data-tax-governance-page
     data-tax-governance-screen="{{ $taxGovernanceScreen }}"
     data-tax-governance-policy-uuid="{{ $taxGovernancePolicyUuid }}"
     role="main"
    aria-label="Platform Finance Billing and Revenue">
    <div class="content">
        <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
            <div class="my-auto mb-2">
                <h2 class="mb-1">Platform Finance - Billing & Revenue</h2>
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item">
                            <a href="{{ url('index') }}"><i class="ti ti-smart-home"></i></a>
                        </li>
                        <li class="breadcrumb-item">Settings</li>
                        <li class="breadcrumb-item active" aria-current="page">Platform Finance - Billing & Revenue</li>
                    </ol>
                </nav>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <button type="button" class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#platformBillingGuideModal">
                    <i class="ti ti-info-circle me-1"></i>Panduan
                </button>
                <button type="button" class="btn btn-outline-primary" data-tax-governance-refresh>
                    <i class="ti ti-refresh me-1"></i>Muat Ulang
                </button>
            </div>
        </div>

        <div class="modal fade" id="platformBillingGuideModal" tabindex="-1" aria-labelledby="platformBillingGuideModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="platformBillingGuideModalLabel">Panduan Billing & Revenue Platform</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <h6 class="fw-semibold mb-2">1) Tujuan Halaman Ini</h6>
                            <ul class="mb-0 ps-3">
                                <li>Dipakai untuk mengatur tarif global revenue platform, bukan edit manual per tenant.</li>
                                <li>Konfigurasi di sini dipakai untuk perhitungan charge dan ringkasan pendapatan platform dari tenant.</li>
                            </ul>
                        </div>

                        <div class="mb-3">
                            <h6 class="fw-semibold mb-2">2) Arti Tiap Field</h6>
                            <ul class="mb-0 ps-3">
                                <li><span class="fw-medium">Subscription Charge Rate (%)</span>: tarif charge untuk komponen subscription tenant.</li>
                                <li><span class="fw-medium">Tarif Biaya Layanan Payroll (%)</span>: persentase service fee untuk payroll run tenant.</li>
                                <li><span class="fw-medium">Tarif Add-on Fitur (%)</span>: persentase markup charge untuk pembelian add-on fitur.</li>
                                <li><span class="fw-medium">Status</span>: gunakan Draft untuk simulasi, Active untuk berlaku runtime, Inactive untuk menonaktifkan versi.</li>
                                <li><span class="fw-medium">Catatan / Versi Kebijakan</span>: wajib diisi ringkas agar perubahan mudah diaudit.</li>
                            </ul>
                        </div>

                        <div class="mb-3">
                            <h6 class="fw-semibold mb-2">3) Alur Pakai yang Disarankan</h6>
                            <ol class="mb-0 ps-3">
                                <li>Isi tarif baru dan catatan perubahan (contoh: Update Q3 2026).</li>
                                <li>Simpan dengan status Draft untuk review internal.</li>
                                <li>Setelah diverifikasi finance/compliance, ubah ke Active.</li>
                                <li>Buka Ringkasan Platform Revenue dan cek dampak pada bulan berjalan.</li>
                            </ol>
                        </div>

                        <div class="mb-3">
                            <h6 class="fw-semibold mb-2">4) Contoh Perhitungan Cepat</h6>
                            <div class="small text-muted">
                                Misal tenant A punya Subscription Rp1.000.000, Payroll Service Rp200.000, Add-on Rp100.000.<br>
                                Jika tarif masing-masing 5%, 0,5%, dan 10% maka komponen charge/fee akan dihitung dari basis masing-masing stream,
                                lalu direkap menjadi total billing revenue platform.
                            </div>
                        </div>

                        <div class="mb-0">
                            <h6 class="fw-semibold mb-2">5) Troubleshooting</h6>
                            <ul class="mb-0 ps-3">
                                <li>Jika tabel kosong, klik Muat Ulang lalu pastikan bulan report sudah terisi.</li>
                                <li>Jika nilai terlihat tidak sesuai, cek apakah status kebijakan masih Draft/Inactive.</li>
                                <li>Jika simpan gagal, lihat pesan error merah lalu validasi input angka 0 sampai 100.</li>
                            </ul>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Mengerti</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="alert alert-info d-flex align-items-start gap-2 mb-3" role="alert">
            <i class="ti ti-info-circle mt-1"></i>
            <div>
                <div class="fw-semibold">Layer ini khusus domain tenant charge, invoice, dan revenue platform.</div>
                <div class="small">Untuk kewajiban pajak platform ke pemerintah, gunakan menu Government Tax & Compliance.</div>
            </div>
        </div>

        <div class="alert alert-danger d-none" data-tax-governance-error></div>
        <div class="alert alert-info d-none" data-tax-platform-gate></div>

        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="mb-0">Tarif Billing & Revenue</h5>
                <small class="text-muted">Konfigurasi tarif charge subscription, biaya layanan, dan pricing add-on</small>
            </div>
            <div class="card-body border-bottom">
                <form class="row g-3" data-tax-platform-policy-form>
                    <div class="col-md-3">
                        <label class="form-label">Subscription Charge Rate (%)</label>
                        <input type="number" class="form-control" name="subscription_tax_rate" min="0" max="100" step="0.01" placeholder="Misal: 5.00" required>
                        <small class="text-muted">Tarif charge untuk setiap subscription tenant</small>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Tarif Biaya Layanan Payroll (%)</label>
                        <input type="number" class="form-control" name="payroll_service_fee" min="0" max="100" step="0.01" placeholder="Misal: 0.50" required>
                        <small class="text-muted">Biaya service per payroll run yang dijalankan</small>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Tarif Add-on Fitur (%)</label>
                        <input type="number" class="form-control" name="addon_markup_rate" min="0" max="100" step="0.01" placeholder="Misal: 10.00" required>
                        <small class="text-muted">Markup untuk setiap pembelian add-on fitur</small>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status" required>
                            <option value="active">Aktif</option>
                            <option value="draft">Draft</option>
                            <option value="inactive">Nonaktif</option>
                        </select>
                        <small class="text-muted">Status berlaku untuk semua tarif platform</small>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Catatan / Versi Kebijakan</label>
                        <textarea class="form-control" name="notes" rows="2" placeholder="Misal: Update Q2 2026 - Rate adjustment per revenue growth"></textarea>
                    </div>
                    <div class="col-12 d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div class="small text-muted">Tarif ini adalah konfigurasi GLOBAL platform yang berlaku untuk semua tenant. Perubahan akan efektif setelah disimpan.</div>
                        <button type="submit" class="btn btn-primary" data-tax-platform-policy-submit>
                            <i class="ti ti-device-floppy me-1"></i>Simpan Tarif Global
                        </button>
                    </div>
                </form>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>Versi</th>
                                <th>Tarif Subscription</th>
                                <th>Service Fee Payroll</th>
                                <th>Add-on Markup</th>
                                <th>Status</th>
                                <th>Dibuat</th>
                                <th>Efektif Sejak</th>
                            </tr>
                        </thead>
                        <tbody data-tax-platform-policy-table>
                            <tr><td colspan="7" class="text-center text-muted py-4">Memuat riwayat tarif global...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="mb-0">Ringkasan Platform Revenue (Semua Tenant)</h5>
                <div class="d-flex align-items-center gap-2">
                    <input type="month" class="form-control" data-tax-platform-report-month>
                    <button type="button" class="btn btn-outline-primary" data-tax-platform-report-refresh>
                        <i class="ti ti-refresh me-1"></i>Muat
                    </button>
                </div>
            </div>
            <div class="card-body border-bottom">
                <div class="row g-3">
                    <div class="col-md-3">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted fs-13 mb-1">Total Subscription Revenue</div>
                            <h5 class="mb-0" data-tax-platform-summary-subscription-revenue>Rp 0</h5>
                            <small class="text-muted">Sebelum pajak</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted fs-13 mb-1">Total Payroll Service Fee</div>
                            <h5 class="mb-0" data-tax-platform-summary-payroll-fee>Rp 0</h5>
                            <small class="text-muted">Dari payroll runs</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted fs-13 mb-1">Total Add-on Revenue</div>
                            <h5 class="mb-0" data-tax-platform-summary-addon-revenue>Rp 0</h5>
                            <small class="text-muted">Dari fitur tambahan</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted fs-13 mb-1">Total Billing Revenue</div>
                            <h5 class="mb-0" data-tax-platform-summary-net-revenue>Rp 0</h5>
                            <small class="text-success">Akumulasi revenue lintas stream</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>Tenant</th>
                                <th>Plan</th>
                                <th>Subscription (Rp)</th>
                                <th>Service Fee (Rp)</th>
                                <th>Add-on (Rp)</th>
                                <th>Gross Revenue (Rp)</th>
                                <th>Billing Charge (Rp)</th>
                                <th>Total Revenue (Rp)</th>
                            </tr>
                        </thead>
                        <tbody data-tax-platform-report-table>
                            <tr><td colspan="8" class="text-center text-muted py-4">Memuat ringkasan revenue semua tenant...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
