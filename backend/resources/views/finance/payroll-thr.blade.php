<?php $page = 'payroll-thr'; ?>
@extends('layout.mainlayout')
@section('content')

    <div class="page-wrapper">
        <div class="content">

            <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
                <div class="my-auto mb-2">
                    <h2 class="mb-1">Payroll — THR</h2>
                    <nav>
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item">
                                <a href="{{ url('index') }}"><i class="ti ti-smart-home"></i></a>
                            </li>
                            <li class="breadcrumb-item">HR</li>
                            <li class="breadcrumb-item active" aria-current="page">Payroll / THR</li>
                        </ol>
                    </nav>
                </div>
                <div class="d-flex my-xl-auto right-content align-items-center flex-wrap ">
                    <div class="head-icons ms-2">
                        <a href="javascript:void(0);" class="" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-original-title="Collapse" id="collapse-header">
                            <i class="ti ti-chevrons-up"></i>
                        </a>
                    </div>
                </div>
            </div>



            <div class="alert alert-light border mb-4" role="status">
                <strong>THR (Tunjangan Hari Raya):</strong> jadwal bayar THR <strong>biasanya tidak mengikuti tanggal payroll bulanan</strong> — banyak perusahaan mentransfer THR <strong>terpisah</strong> (mis. <strong>7–10 hari sebelum hari raya</strong>), sehingga di bulan tersebut bisa terjadi <strong>dua kali pembayaran</strong>: gaji bulanan rutin + THR.
                Form di bawah memuat pengaturan per <em>tahun kalender</em> (tanggal H referensi, tanggal transfer THR, cut-off masa kerja untuk pro rata).
                Estimasi nominal mengikuti Permenaker 6/2016; <strong>PPh 21 TER</strong> di luar kalkulator ini.
            </div>

            <div class="row mb-4">
                <div class="col-xl-6 d-flex">
                    <div id="thr-periode-settings-card" class="card flex-fill mb-3 mb-xl-0 border-primary border-opacity-25" style="scroll-margin-top: 5.5rem;">
                        <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <h5 class="mb-0">Pengaturan periode THR</h5>
                            <span class="badge bg-light text-dark">HCM Admin</span>
                        </div>
                        <div class="card-body">
                            <p class="small text-muted mb-3">
                                Isi untuk tahun yang sama dengan siklus THR (mis. tahun saat Idul Fitri jatuh). Tanggal cut-off perhitungan biasanya <strong>H-1</strong> terhadap hari raya atau mengikuti SK perusahaan.
                            </p>
                            <div class="alert alert-danger d-none py-2 small mb-3" role="alert" data-thr-settings-error></div>
                            <form data-thr-settings-form class="row g-2">
                                <div class="col-md-6">
                                    <label class="form-label">Tahun kalender</label>
                                    <input type="number" name="calendarYear" class="form-control" min="2000" max="2100" step="1" required data-thr-settings-year>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Tanggal Lebaran (H) — referensi</label>
                                    <input type="date" name="eidDate" class="form-control" required data-thr-settings-eid>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Tanggal pembayaran THR</label>
                                    <input type="date" name="paymentDate" class="form-control" data-thr-settings-payment>
                                    <span class="text-muted small">Transfer THR (bukan tanggal gaji bulanan); biasanya H-7 s.d. H-10.</span>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Cut-off perhitungan pro rata</label>
                                    <input type="date" name="calculationCutoffDate" class="form-control" data-thr-settings-cutoff>
                                    <span class="text-muted small">Default bisa H-1 Lebaran; sesuaikan SK HR.</span>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Catatan internal</label>
                                    <textarea name="notes" class="form-control" rows="2" maxlength="2000" placeholder="Opsional" data-thr-settings-notes></textarea>
                                </div>
                                <div class="col-12 mt-2 d-flex flex-wrap gap-2">
                                    <button type="submit" class="btn btn-primary">Simpan pengaturan</button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" data-thr-suggest-cutoff>Isi cut-off = H-1 dari tanggal H</button>
                                </div>
                            </form>
                            <div class="mt-3 pt-3 border-top small text-muted" data-thr-settings-saved-list>
                                <span class="text-muted">Memuat riwayat tahun…</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-6 d-flex">
                    <div class="card flex-fill">
                        <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <h5 class="mb-0">Estimasi THR (pro rata)</h5>
                            <span class="badge bg-light text-dark">HCM Admin</span>
                        </div>
                        <div class="card-body">
                            <p class="small text-muted mb-3">
                                Upah acuan = gaji pokok. Masa kerja <em>M</em> bulan penuh sampai tanggal cut-off.
                                Tanggal cut-off di form bisa diisi otomatis dari pengaturan tahun di kiri setelah simpan / pilih tahun.
                            </p>
                            <div class="alert alert-danger d-none py-2 small mb-3" role="alert" data-payroll-thr-error></div>
                            <form data-payroll-thr-form class="row g-2">
                                <div class="col-md-6">
                                    <label class="form-label">Tanggal bergabung</label>
                                    <input type="date" name="joinDate" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Tanggal cut-off</label>
                                    <input type="date" name="cutoffDate" class="form-control" required data-thr-calc-cutoff>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Gaji pokok / bulan</label>
                                    <input type="number" name="baseMonthlySalary" class="form-control" min="0" step="1000" required placeholder="6000000">
                                </div>
                                <div class="col-12 mt-2">
                                    <button type="submit" class="btn btn-primary">Hitung estimasi THR</button>
                                </div>
                            </form>
                            <div class="mt-3 pt-3 border-top" data-payroll-thr-result>
                                <p class="text-muted small mb-0">Isi form lalu submit untuk melihat hasil.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4" data-thr-batch-panel>
                <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <h5 class="mb-0">Mass calculate &amp; pay THR</h5>
                    <span class="badge bg-light text-dark">HCM Admin</span>
                </div>
                <div class="card-body">
                    <p class="small text-muted mb-3" data-thr-batch-status-hint>
                        Jika <strong>pengaturan periode THR</strong> untuk tahun ini sudah disimpan dan <strong>cut-off</strong> terisi, daftar karyawan akan <strong>dibuat otomatis</strong> (kecuali sudah ada draft/run). Tanpa cut-off, generate otomatis tidak jalan — tetap bisa pakai tombol Generate.
                    </p>
                    <div class="alert alert-danger d-none py-2 small mb-3" role="alert" data-thr-batch-error></div>
                    <div class="alert alert-warning d-none py-2 small mb-3" role="alert" data-thr-reconciliation-hint></div>
                    <div class="d-none mb-3" role="status" data-thr-evidence-indicator>
                        <small class="text-muted">Evidence status: <span class="badge bg-success" data-evidence-status>Loading...</span></small>
                        <small class="d-block mt-1 text-muted" data-evidence-timestamp></small>
                    </div>
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                        <button type="button" class="btn btn-primary" data-thr-batch-generate>Generate list</button>
                        <button type="button" class="btn btn-outline-secondary" data-thr-batch-export-evidence title="Export data reconciliation untuk bukti audit sebelum pay/posting">Export Reconciliation</button>
                        <button type="button" class="btn btn-success" data-thr-batch-disburse disabled>Pay THR</button>
                        <button type="button" class="btn btn-outline-secondary" data-thr-batch-send-slip disabled>Kirim slip</button>
                    </div>
                    <div class="border rounded px-3 py-2 mb-0 bg-light small d-flex flex-wrap gap-4">
                        <div><span class="text-muted">Total eligible (batch):</span> <strong data-thr-batch-grand>—</strong></div>
                        <div><span class="text-muted">Terpilih:</span> <strong data-thr-batch-checked-count>0</strong> karyawan</div>
                        <div><span class="text-muted">Jumlah terpilih:</span> <strong data-thr-batch-checked-sum>Rp0</strong></div>
                    </div>
                </div>
                <div class="card-body p-0 border-top">
                    <p class="text-muted small mb-0 px-3 py-2 d-none border-bottom bg-white" data-thr-batch-empty>Belum ada daftar. Pastikan cut-off tahun ini sudah disimpan, lalu Generate.</p>
                    <div class="custom-datatable-filter table-responsive">
                        <table class="table table-nowrap mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th class="no-sort" style="width: 48px;">
                                        <div class="form-check form-check-md d-flex justify-content-center">
                                            <input type="checkbox" class="form-check-input" data-thr-select-all aria-label="Pilih semua" title="Pilih semua baris yang memiliki checkbox">
                                        </div>
                                    </th>
                                    <th>Karyawan</th>
                                    <th>Rekening</th>
                                    <th class="text-center">Eligible</th>
                                    <th>Tgl masuk</th>
                                    <th class="text-end">Gaji pokok</th>
                                    <th class="text-end">Upah acuan</th>
                                    <th class="text-center">M</th>
                                    <th class="text-end">%</th>
                                    <th>Hitungan</th>
                                    <th class="text-end">THR bruto</th>
                                    <th>Bayar</th>
                                    <th>Dibayar</th>
                                    <th title="Referensi gateway">Ref</th>
                                    <th>Slip</th>
                                </tr>
                            </thead>
                            <tbody data-thr-batch-body></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="thrDisburseConfirmModal" tabindex="-1" aria-hidden="true"
                data-thr-disburse-gateway-driver="{{ config('hcm.thr_disbursement_driver', 'stub') }}">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Eksekusi pembayaran (gateway)</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p class="mb-3">Sistem akan memanggil <strong>payment gateway</strong> untuk karyawan tercentang yang masih bisa dibayar (belum status <strong>paid</strong>).</p>
                            <ul class="list-unstyled small mb-3">
                                <li class="mb-2"><span class="text-muted">Tahun THR (batch):</span> <strong data-thr-disburse-modal-year>—</strong></li>
                                <li class="mb-2"><span class="text-muted">Mode gateway (server):</span> <strong data-thr-disburse-modal-driver>—</strong></li>
                                <li class="mb-2"><span class="text-muted">Karyawan tercentang (pay):</span> <strong data-thr-disburse-modal-checked>0</strong></li>
                                <li class="mb-2"><span class="text-muted">Akan diproses ke gateway:</span> <strong class="text-primary" data-thr-disburse-modal-count>0</strong></li>
                                <li class="mb-2"><span class="text-muted">Dilewati (sudah paid):</span> <strong data-thr-disburse-modal-skip-paid>0</strong></li>
                                <li class="mb-0"><span class="text-muted">Total THR bruto (yang diproses):</span> <strong data-thr-disburse-modal-total>—</strong></li>
                            </ul>
                            <p class="small text-muted mb-2">Yang sudah <strong>paid</strong> tidak dikirim ulang. Jika gagal (rekening, limit, dll.), status dan alasan tampil di tabel — centang lagi untuk <strong>retry</strong>.</p>
                            <p class="small text-muted mb-0" data-thr-disburse-modal-stub-note hidden>Mode <strong>stub</strong> hanya simulasi: tidak ada transfer bank sungguhan. Setelah sukses, sistem dapat menghasilkan <strong>slip THR (PDF)</strong> per karyawan.</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                            <button type="button" class="btn btn-success" data-thr-disburse-confirm>Eksekusi pembayaran</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="thr_reconciliation_preview_modal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl">
                    <div class="modal-content">
                        <div class="modal-header">
                            <div>
                                <h5 class="modal-title">Preview Reconciliation THR</h5>
                                <p class="text-muted small mb-0">Tinjau data THR sebelum membuat dan mengunduh file evidence.</p>
                            </div>
                            <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                                <i class="ti ti-x"></i>
                            </button>
                        </div>
                        <div class="modal-body p-0">
                            <div class="px-3 pt-3 pb-2 bg-light border-bottom">
                                <div class="row g-3 small">
                                    <div class="col-sm-3 d-flex justify-content-between border-end">
                                        <span class="text-muted">Tahun THR</span>
                                        <strong data-thr-recon-preview-year>—</strong>
                                    </div>
                                    <div class="col-sm-3 d-flex justify-content-between border-end">
                                        <span class="text-muted">Karyawan eligible</span>
                                        <strong data-thr-recon-preview-count>0</strong>
                                    </div>
                                    <div class="col-sm-3 d-flex justify-content-between border-end">
                                        <span class="text-muted">Grand total THR</span>
                                        <strong class="text-primary" data-thr-recon-preview-total>Rp0</strong>
                                    </div>
                                    <div class="col-sm-3 d-flex justify-content-between">
                                        <span class="text-muted">Status batch</span>
                                        <strong data-thr-recon-preview-status>—</strong>
                                    </div>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-nowrap table-hover mb-0 align-middle">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>Karyawan</th>
                                            <th class="text-end">Upah acuan</th>
                                            <th class="text-center">Masa kerja</th>
                                            <th class="text-center">Multiplier</th>
                                            <th class="text-end">THR bruto</th>
                                            <th class="text-center">Status bayar</th>
                                        </tr>
                                    </thead>
                                    <tbody data-thr-recon-preview-body>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-4">Memuat data…</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="modal-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <p class="text-muted small mb-0">
                                <i class="ti ti-info-circle me-1"></i>
                                File XLSX akan dibuat dari data di atas. Setelah diunduh, tombol Pay THR akan terbuka.
                            </p>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                                <button type="button" class="btn btn-secondary" data-thr-recon-preview-download>
                                    <i class="ti ti-download me-1"></i>Download XLSX &amp; Konfirmasi Evidence
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="thrBatchSlipPreviewModal" tabindex="-1" aria-hidden="true" aria-labelledby="thrBatchSlipPreviewModalLabel">
                <div class="modal-dialog modal-xl modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <div>
                                <h5 class="modal-title mb-0" id="thrBatchSlipPreviewModalLabel">Preview slip THR</h5>
                                <p class="small text-muted mb-0 mt-1">
                                    <span class="me-1">Nomor slip:</span>
                                    <strong class="text-primary" data-thr-slip-preview-slip-no>—</strong>
                                </p>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body p-0 position-relative bg-light" style="min-height: 65vh;">
                            <div class="d-flex align-items-center justify-content-center py-5 text-muted small" data-thr-slip-preview-loading>Memuat PDF…</div>
                            <iframe class="d-none w-100 bg-white" style="min-height: 65vh; height: 65vh;" data-thr-slip-preview-iframe title="Preview slip THR PDF"></iframe>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Tutup</button>
                            <button type="button" class="btn btn-dark d-inline-flex align-items-center" data-thr-slip-modal-download disabled>
                                <i class="ti ti-download me-2"></i>Unduh PDF
                            </button>
                        </div>
                    </div>
                </div>
            </div>


            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Klasifikasi penghasilan (ringkas)</h5>
                </div>
                <div class="card-body small">
                    <ul class="mb-0 ps-3">
                        <li><strong>Gaji pokok</strong> → menjadi basis upah acuan THR pada implementasi saat ini.</li>
                        <li><strong>Tunjangan tidak tetap</strong> (makan/harian, transport hadir) → biasanya tidak masuk upah acuan.</li>
                        <li>Daftar item penghasilan di katalog: <a href="{{ url('payroll') }}">Payroll — Additions</a>.</li>
                    </ul>
                </div>
            </div>

        </div>
    </div>

    @component('components.modal-popup')
    @endcomponent
@endsection
