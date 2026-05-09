<?php $page = 'saas-platform-tax'; ?>
@extends('layout.mainlayout')

@section('content')

<!-- Page Wrapper -->
<div class="page-wrapper">
    <div class="content" data-platform-tax-page>

        <!-- Breadcrumb -->
        <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
            <div class="my-auto mb-2">
                <h2 class="mb-1">SPT Pajak Platform & Estimasi PPh Badan</h2>
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="{{url('index')}}"><i class="ti ti-smart-home"></i></a></li>
                        <li class="breadcrumb-item">SaaS</li>
                        <li class="breadcrumb-item active" aria-current="page">SPT Pajak Platform & Estimasi PPh Badan</li>
                    </ol>
                </nav>
            </div>
            <div class="d-flex my-xl-auto right-content align-items-center flex-wrap gap-2">
                <div class="mb-2">
                    <a href="{{ route('platform-tax-compliance.policies') }}" class="btn btn-outline-primary btn-sm">
                        <i class="ti ti-adjustments me-1"></i>Buka Tax Compliance Settings
                    </a>
                </div>
                <div class="mb-2">
                    <button type="button" class="btn btn-outline-info btn-sm" data-bs-toggle="collapse" data-bs-target="#platformTaxGuide" aria-expanded="false" aria-controls="platformTaxGuide">
                        <i class="ti ti-info-circle me-1"></i>Panduan
                    </button>
                </div>
                <div class="mb-2">
                    <button class="btn btn-outline-secondary btn-sm" id="btn_print_tax" disabled>
                        <i class="ti ti-printer me-1"></i>Print / Export Excel (tab PPh Badan)
                    </button>
                </div>
            </div>
        </div>
        <!-- /Breadcrumb -->

        <!-- Panduan (collapsible) -->
        <div class="collapse mb-3" id="platformTaxGuide">
            <div class="alert alert-info mb-0">
                <div class="fw-semibold mb-2"><i class="ti ti-book me-1"></i>Panduan SPT Pajak Platform</div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <strong>Cara Pakai</strong>
                        <ul class="mb-0 mt-1 ps-3">
                            <li>Pilih <strong>Masa Pajak</strong> (bulan &amp; tahun) lalu klik <strong>Hitung Kewajiban Pajak</strong>.</li>
                            <li>Tab <strong>Dashboard</strong>: ringkasan KPI dan total kewajiban pajak bulan itu.</li>
                            <li>Tab <strong>SPT PPN</strong>: rincian faktur pajak keluaran per invoice (formulir 1111).</li>
                            <li>Tab <strong>SPT PPh 23</strong>: rincian pemotongan per pembayaran yang completed.</li>
                            <li>Tab <strong>PPh Badan (Estimasi)</strong>: ringkasan tahunan berbasis policy compliance aktif per bulan.</li>
                            <li>Gunakan <strong>Tarif PPN</strong> untuk override tarif jika ada perubahan regulasi.</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <strong>Informasi Pajak</strong>
                        <ul class="mb-0 mt-1 ps-3">
                            <li><strong>PPN 11%</strong> (UU HPP No. 7/2021): disetor paling lambat akhir bulan berikutnya, dilaporkan lewat e-Filing DJP.</li>
                            <li><strong>PPh 23 (2%)</strong>: dipotong oleh tenant saat bayar ke platform. Batas setor tgl 10, lapor tgl 20 bulan berikutnya.</li>
                            <li><strong>PPh Final 0,5%</strong> (PP 23/2018): ambang omzet tahunan yang relevan adalah Rp 4.800.000.000 (empat koma delapan miliar). Verifikasi kriteria sebelum menerapkan PPh Final.</li>
                            <li>Data ini <strong>estimasi</strong> — verifikasi dengan akuntan sebelum menyetor ke DJP.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Notice banner -->
        <div class="alert alert-warning alert-dismissible fade show mb-3" role="alert">
            <i class="ti ti-alert-triangle me-2"></i>
            <strong>Catatan:</strong> Data ini adalah <strong>kalkulasi estimasi</strong> berdasarkan invoice & pembayaran di sistem.
            Lakukan verifikasi dengan akuntan/konsultan pajak sebelum menyetor ke DJP.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>

        <div class="alert alert-info mb-3" role="alert">
            <i class="ti ti-arrows-exchange me-2"></i>
            Halaman ini fokus pada <strong>pelaporan</strong> (SPT PPN, SPT PPh 23, dan estimasi PPh Badan).
            Untuk pengaturan tarif/kebijakan platform, gunakan
            <a href="{{ route('platform-tax-compliance.policies') }}" class="alert-link">Platform Tax Compliance Settings</a>.
        </div>

        <!-- Period Selector -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label fw-medium">Masa Pajak (Bulan)</label>
                        <input type="month" class="form-control" id="input_tax_month" value="{{ date('Y-m') }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-medium">Tarif PPN (%)</label>
                        <div class="form-control-plaintext fw-semibold py-2" id="display_ppn_rate">
                            <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>memuat...
                        </div>
                        <small class="text-muted">
                            Bersumber dari <a href="{{ route('platform-tax-compliance.policies') }}">Tax Compliance Settings</a>.
                        </small>
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-primary w-100" id="btn_load_tax_data">
                            <i class="ti ti-calculator me-2"></i>Hitung Kewajiban Pajak
                        </button>
                    </div>
                    <div class="col-md-4">
                        <div class="nav nav-tabs" id="tax-tabs">
                            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-dashboard">Dashboard</button>
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-ppn">SPT PPN</button>
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-pph23">SPT PPh 23</button>
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-pph-badan">PPh Badan (Estimasi)</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab Content -->
        <div class="tab-content" id="tax-tab-content">

            <!-- ─── TAB: DASHBOARD ────────────────────────────────────── -->
            <div class="tab-pane fade show active" id="tab-dashboard">

                <!-- KPI Cards -->
                <div class="row g-3 mb-4" id="kpi_cards_container">
                    <div class="col-md-3">
                        <div class="card h-100">
                            <div class="card-body d-flex flex-column">
                                <div class="placeholder-glow mb-3"><span class="placeholder col-8 rounded"></span></div>
                                <div class="placeholder-glow"><span class="placeholder col-5 rounded" style="height:2rem"></span></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card h-100">
                            <div class="card-body d-flex flex-column">
                                <div class="placeholder-glow mb-3"><span class="placeholder col-8 rounded"></span></div>
                                <div class="placeholder-glow"><span class="placeholder col-5 rounded" style="height:2rem"></span></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card h-100">
                            <div class="card-body d-flex flex-column">
                                <div class="placeholder-glow mb-3"><span class="placeholder col-8 rounded"></span></div>
                                <div class="placeholder-glow"><span class="placeholder col-5 rounded" style="height:2rem"></span></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card h-100">
                            <div class="card-body d-flex flex-column">
                                <div class="placeholder-glow mb-3"><span class="placeholder col-8 rounded"></span></div>
                                <div class="placeholder-glow"><span class="placeholder col-5 rounded" style="height:2rem"></span></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tax Obligations Table -->
                <div class="card" id="tax_obligations_container">
                    <div class="card-header">
                        <h5 class="mb-0">Ringkasan Kewajiban Pajak Platform</h5>
                        <small class="text-muted">Estimasi berdasarkan data invoice & pembayaran bulan ini</small>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Jenis Pajak</th>
                                    <th>Dasar Hukum</th>
                                    <th>Tarif</th>
                                    <th class="text-end">DPP (Rp)</th>
                                    <th class="text-end">Pajak Terutang (Rp)</th>
                                    <th>Batas Setor</th>
                                    <th>Batas Lapor</th>
                                    <th>Kode Akun</th>
                                </tr>
                            </thead>
                            <tbody id="tax_obligations_tbody">
                                <tr><td colspan="8" class="text-center text-muted py-4">
                                    <div class="placeholder-glow"><span class="placeholder col-6 rounded"></span></div>
                                </td></tr>
                            </tbody>
                            <tfoot>
                                <tr class="table-light fw-bold" id="tax_total_row" style="display:none">
                                    <td colspan="4" class="text-end">Total Kewajiban Pajak:</td>
                                    <td class="text-end text-danger" id="tax_total_amount">—</td>
                                    <td colspan="3"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <!-- Revenue Breakdown -->
                <div class="card mt-3" id="revenue_breakdown_container">
                    <div class="card-header">
                        <h5 class="mb-0">Rincian Pendapatan Platform</h5>
                    </div>
                    <div class="card-body" id="revenue_breakdown_body">
                        <div class="placeholder-glow"><span class="placeholder col-8 rounded"></span></div>
                    </div>
                </div>
            </div>

            <!-- ─── TAB: SPT PPN 1111 ──────────────────────────────────── -->
            <div class="tab-pane fade" id="tab-ppn">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0">SPT Masa PPN 1111</h5>
                            <small class="text-muted" id="ppn_period_label">—</small>
                        </div>
                        <div class="d-flex gap-2">
                            <span class="badge text-bg-info" id="ppn_batas_lapor_badge">Batas lapor: —</span>
                        </div>
                    </div>
                    <div class="card-body pb-0">
                        <div class="row g-3 mb-3">
                            <div class="col-md-3">
                                <div class="bg-light rounded p-3 text-center">
                                    <small class="text-muted d-block">Total DPP</small>
                                    <strong class="fs-3 fw-bold" id="ppn_total_dpp">—</strong>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="bg-light rounded p-3 text-center">
                                    <small class="text-muted d-block">PPN Keluaran</small>
                                    <strong class="fs-3 fw-bold text-danger" id="ppn_total_keluaran">—</strong>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="bg-light rounded p-3 text-center">
                                    <small class="text-muted d-block">PPN Masukan</small>
                                    <strong class="fs-3 fw-bold text-success" id="ppn_total_masukan">Rp 0</strong>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="bg-danger bg-opacity-10 rounded p-3 text-center">
                                    <small class="text-muted d-block">PPN Kurang Bayar</small>
                                    <strong class="fs-3 fw-bold text-danger" id="ppn_kurang_bayar">—</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th width="40">No</th>
                                    <th>No. Faktur / Invoice</th>
                                    <th>Tanggal</th>
                                    <th>Nama Pembeli (Tenant)</th>
                                    <th>NPWP Pembeli</th>
                                    <th class="text-end">DPP (Rp)</th>
                                    <th class="text-center">Tarif PPN</th>
                                    <th class="text-end">PPN (Rp)</th>
                                    <th class="text-center">Status Invoice</th>
                                </tr>
                            </thead>
                            <tbody id="ppn_detail_tbody">
                                <tr><td colspan="9" class="text-center text-muted py-4">Klik "Hitung Kewajiban Pajak" untuk memuat data.</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer">
                        <div class="alert alert-info mb-0 py-2 fs-12">
                            <i class="ti ti-info-circle me-1"></i>
                            DPP = <code>amount_due</code> dari invoices. PPN Masukan (PM) belum dikelola — isi manual saat lapor ke DJP.
                        </div>
                    </div>
                </div>
            </div>

            <!-- ─── TAB: SPT PPh 23 ────────────────────────────────────── -->
            <div class="tab-pane fade" id="tab-pph23">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0">SPT Masa PPh Pasal 23</h5>
                            <small class="text-muted" id="pph23_period_label">—</small>
                        </div>
                        <div class="d-flex gap-2">
                            <span class="badge text-bg-info" id="pph23_batas_setor_badge">Batas setor: —</span>
                            <span class="badge text-bg-warning" id="pph23_batas_lapor_badge">Batas lapor: —</span>
                        </div>
                    </div>
                    <div class="card-body pb-0">
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <div class="bg-light rounded p-3 text-center">
                                    <small class="text-muted d-block">Total Bruto</small>
                                    <strong class="fs-3 fw-bold" id="pph23_total_bruto">—</strong>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="bg-danger bg-opacity-10 rounded p-3 text-center">
                                    <small class="text-muted d-block">PPh 23 Terutang (2%)</small>
                                    <strong class="fs-3 fw-bold text-danger" id="pph23_total_terutang">—</strong>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="bg-light rounded p-3 text-center">
                                    <small class="text-muted d-block">Jumlah Pembayaran</small>
                                    <strong class="fs-3 fw-bold" id="pph23_payment_count">—</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th width="40">No</th>
                                    <th>Nama Pemotong (Tenant)</th>
                                    <th>NPWP Pemotong</th>
                                    <th>Jenis Penghasilan</th>
                                    <th>Kode Objek Pajak</th>
                                    <th>Tanggal Bayar</th>
                                    <th class="text-end">Jumlah Bruto (Rp)</th>
                                    <th class="text-center">Tarif</th>
                                    <th class="text-end">PPh 23 (Rp)</th>
                                </tr>
                            </thead>
                            <tbody id="pph23_detail_tbody">
                                <tr><td colspan="9" class="text-center text-muted py-4">Klik "Hitung Kewajiban Pajak" untuk memuat data.</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer">
                        <div class="alert alert-info mb-0 py-2 fs-12">
                            <i class="ti ti-info-circle me-1"></i>
                            PPh 23 dipotong oleh pembayar (tenant) saat melakukan pembayaran ke platform.
                            Kode objek pajak <code>24-100-09</code>: Jasa Manajemen &amp; Konsultasi Lainnya.
                            NPWP pemotong belum tersedia — wajib dilengkapi sebelum lapor.
                        </div>
                    </div>
                </div>
            </div>

            <!-- ─── TAB: PPh Badan (Estimasi) ─────────────────────────────── -->
            <div class="tab-pane fade" id="tab-pph-badan">
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <h5 class="mb-0">Estimasi PPh Badan (Basis Laporan Platform)</h5>
                            <small class="text-muted" id="pph_badan_period_label">—</small>
                        </div>
                        <div>
                            <span class="badge text-bg-info" id="pph_badan_status_badge">Status: menunggu data</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row g-3 mb-3">
                            <div class="col-md-3">
                                <div class="bg-light rounded p-3 text-center">
                                    <small class="text-muted d-block">Taxable Revenue (Estimasi)</small>
                                    <strong class="fs-4 fw-bold" id="pph_badan_taxable_revenue">—</strong>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="bg-warning bg-opacity-10 rounded p-3 text-center">
                                    <small class="text-muted d-block">PPh Badan Payable (Estimasi)</small>
                                    <strong class="fs-4 fw-bold text-warning" id="pph_badan_tax_payable">—</strong>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="bg-light rounded p-3 text-center">
                                    <small class="text-muted d-block">Net Revenue (After Liability)</small>
                                    <strong class="fs-4 fw-bold" id="pph_badan_net_revenue">—</strong>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="bg-success bg-opacity-10 rounded p-3 text-center">
                                    <small class="text-muted d-block">Net Profit (Estimasi)</small>
                                    <strong class="fs-4 fw-bold text-success" id="pph_badan_net_profit">—</strong>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Tenant</th>
                                        <th class="text-end">Taxable Revenue</th>
                                        <th class="text-end">Tax Liability</th>
                                        <th class="text-end">PPh Badan Payable</th>
                                        <th class="text-end">Net Profit</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody id="pph_badan_detail_tbody">
                                    <tr><td colspan="6" class="text-center text-muted py-4">Klik "Hitung Kewajiban Pajak" untuk memuat data.</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="alert alert-info mb-0 py-2 fs-12">
                            <i class="ti ti-info-circle me-1"></i>
                            Data tab ini ditarik dari endpoint <strong>SPT Tahunan PPh Badan (estimasi internal)</strong> berbasis policy compliance aktif per bulan.
                            Ini bukan pengganti SPT Tahunan 1771 final, tetap wajib rekonsiliasi dan validasi akuntan.
                        </div>
                    </div>
                </div>
            </div>

        </div>
        <!-- /Tab Content -->

    </div>
</div>
<!-- /Page Wrapper -->

<script src="{{ asset('build/js/platform-tax.js') }}?v={{ filemtime(public_path('build/js/platform-tax.js')) }}"></script>
@endsection
