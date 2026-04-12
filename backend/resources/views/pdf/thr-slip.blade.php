<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10.5px;
            color: #202c4b;
            margin: 0;
            padding: 22px 24px 28px;
        }
        .primary { color: #fc7f01; }
        .muted { color: #6b7280; font-size: 9.5px; }
        .fw-bold { font-weight: bold; }
        .fw-semibold { font-weight: bold; }
        .text-end { text-align: right; }
        .text-center { text-align: center; }
        .border-b { border-bottom: 1px solid #e8ecf1; }
        .mb-0 { margin-bottom: 0; }
        .mb-1 { margin-bottom: 4px; }
        .mb-2 { margin-bottom: 8px; }
        .mb-3 { margin-bottom: 14px; }
        .mt-2 { margin-top: 8px; }
        .logo { max-height: 42px; max-width: 160px; }
        .slip-no { font-size: 15px; font-weight: bold; }
        .section-title { font-size: 10px; font-weight: bold; color: #202c4b; margin: 0 0 6px; }
        .panel-head {
            background: #f8f9fa;
            border: 1px solid #e8ecf1;
            border-bottom: none;
            padding: 8px 10px;
            font-weight: bold;
            font-size: 10px;
        }
        .panel-row {
            border: 1px solid #e8ecf1;
            border-top: none;
            padding: 7px 10px;
        }
        .panel-row-flex { width: 100%; border-collapse: collapse; }
        .panel-row-flex td { padding: 0; vertical-align: middle; }
        .panel-row-flex td:last-child { text-align: right; font-weight: bold; }
        .total-bar {
            border: 1px solid #e8ecf1;
            background: #fafbfc;
            padding: 12px 14px;
            margin-top: 16px;
        }
        .total-amount { font-size: 13px; font-weight: bold; color: #202c4b; }
        .audit { font-size: 8.5px; color: #6b7280; margin-top: 4px; letter-spacing: 0.02em; }
    </style>
</head>
<body>
@php
    $rowLabel = match ($line->row_status) {
        'full' => 'Penuh (12+ bln)',
        'pro_rata' => 'Pro rata',
        'nihil' => 'Nihil',
        'invalid' => 'Tanggal tidak valid',
        default => (string) $line->row_status,
    };
    $mult = (float) $line->multiplier;
    $pct = round($mult * 100, 3);
    $code = trim((string) ($line->thr_slip_public_no ?? ''));
    $slipPublicNo = $code !== '' ? $code : ('THR-'.$batch->calendar_year.'-'.$line->id);
    $cutoffDisp = $batch->cutoff_date?->format('d/m/Y') ?? '—';
    $paidDisp = $line->paid_at?->format('d/m/Y H:i') ?? '—';
    $addr = is_string($companyAddress ?? null) ? trim((string) $companyAddress) : '';
@endphp

    {{-- Bar nomor slip full width: tetap terbaca di pratinjau iframe sempit / zoom --}}
    <div style="border:1px solid #e8ecf1; background:#fafbfc; padding:10px 14px; margin-bottom:14px; text-align:center;">
        <div class="muted" style="font-size:9px; text-transform:uppercase; letter-spacing:0.04em;">Slip THR (Tunjangan Hari Raya)</div>
        <div style="font-size:8px; color:#9ca3af; margin-top:2px;">Nomor slip resmi</div>
        <div class="primary" style="font-size:17px; font-weight:bold; margin-top:2px; letter-spacing:0.02em;">#{{ $slipPublicNo }}</div>
    </div>

    {{-- Header: logo + perusahaan | nomor slip (seperti template payslip) --}}
    <table width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;" class="mb-3 border-b">
        <tr>
            <td width="52%" valign="top" style="padding-bottom:14px;">
                @if (!empty($logoDataUri))
                    <img src="{{ $logoDataUri }}" alt="Logo" class="logo" />
                @endif
                <p class="fw-bold mb-1" style="font-size:11px;margin-top:6px;">{{ config('app.name') }}</p>
                @if ($addr !== '')
                    <p class="muted mb-0" style="max-width:260px;">{{ $addr }}</p>
                @else
                    <p class="muted mb-0">SDM / Payroll</p>
                @endif
            </td>
            <td width="48%" valign="top" class="text-end" style="padding-bottom:14px;">
                <p class="mb-1 muted" style="margin-top:0;">Nomor slip THR</p>
                <p class="slip-no primary mb-1">#{{ $slipPublicNo }}</p>
                <p class="mb-1"><span class="muted">Tahun kalender:</span> <span class="fw-semibold">{{ $batch->calendar_year }}</span></p>
                <p class="mb-1"><span class="muted">Cut-off perhitungan:</span> <span class="fw-semibold">{{ $cutoffDisp }}</span></p>
                <p class="mb-0"><span class="muted">Dibayarkan:</span> <span class="fw-semibold">{{ $paidDisp }}</span></p>
                <p class="audit text-end">
                    Ref. audit: BATCH-{{ $batch->id }} · LINE-{{ $line->id }} · USER-{{ $line->user_id }}
                </p>
            </td>
        </tr>
    </table>

    {{-- From / To --}}
    <table width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;" class="mb-3 border-b">
        <tr>
            <td width="48%" valign="top" style="padding:0 12px 14px 0;">
                <p class="section-title">Dari</p>
                <p class="fw-bold mb-1" style="font-size:11px;">{{ config('app.name') }}</p>
                <p class="muted mb-0">Divisi SDM / Payroll</p>
            </td>
            <td width="48%" valign="top" style="padding:0 0 14px 12px;">
                <p class="section-title">Kepada (karyawan)</p>
                <p class="fw-bold mb-1" style="font-size:11px;">{{ $line->full_name }}</p>
                <p class="mb-1"><span class="muted">No. pegawai:</span> {{ $line->employee_no ?: '—' }}</p>
                <p class="mb-0"><span class="muted">Tgl bergabung (acuan):</span> {{ $line->join_date_used?->format('d/m/Y') ?? '—' }}</p>
            </td>
        </tr>
    </table>

    <p class="text-center fw-bold mb-3" style="font-size:12px;margin-top:4px;">
        Slip THR untuk tahun kalender {{ $batch->calendar_year }}
    </p>

    {{-- Dua kolom: rincian | pajak & catatan (layout mirip payslip earnings / deductions) --}}
    <table width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
        <tr>
            <td width="49%" valign="top" style="padding-right:8px;">
                <div class="panel-head">Rincian perhitungan</div>
                <div class="panel-row">
                    <table class="panel-row-flex"><tr>
                        <td>Gaji pokok / bulan</td>
                        <td>Rp {{ number_format((float) $line->base_salary, 0, ',', '.') }}</td>
                    </tr></table>
                </div>
                <div class="panel-row">
                    <table class="panel-row-flex"><tr>
                        <td>Tunjangan tetap / bulan</td>
                        <td>Rp {{ number_format((float) $line->fixed_allowance, 0, ',', '.') }}</td>
                    </tr></table>
                </div>
                <div class="panel-row">
                    <table class="panel-row-flex"><tr>
                        <td>Upah acuan</td>
                        <td>Rp {{ number_format((float) $line->reference_wage, 0, ',', '.') }}</td>
                    </tr></table>
                </div>
                <div class="panel-row">
                    <table class="panel-row-flex"><tr>
                        <td>Masa kerja (bulan penuh)</td>
                        <td>{{ $line->months_of_service }}</td>
                    </tr></table>
                </div>
                <div class="panel-row">
                    <table class="panel-row-flex"><tr>
                        <td>Proporsi THR</td>
                        <td>{{ number_format($pct, 3, ',', '.') }}%</td>
                    </tr></table>
                </div>
                <div class="panel-row">
                    <table class="panel-row-flex"><tr>
                        <td>Status hitungan</td>
                        <td>{{ $rowLabel }}</td>
                    </tr></table>
                </div>
                <div class="panel-row" style="background:#f8fafc;">
                    <table class="panel-row-flex"><tr>
                        <td class="fw-bold">THR bruto</td>
                        <td class="primary fw-bold">Rp {{ number_format((float) $line->thr_gross, 0, ',', '.') }}</td>
                    </tr></table>
                </div>
            </td>
            <td width="49%" valign="top" style="padding-left:8px;">
                <div class="panel-head">Pajak &amp; catatan</div>
                <div class="panel-row">
                    <p class="mb-1"><span class="muted">PPh 21 (TER / pemotongan)</span></p>
                    <p class="mb-0" style="line-height:1.45;">Dihitung dan dipotong terpisah melalui proses payroll sesuai kebijakan perusahaan.</p>
                </div>
                <div class="panel-row">
                    <p class="mb-1 fw-semibold">Identitas dokumen</p>
                    <p class="muted mb-0" style="line-height:1.45;">
                        Simpan nomor slip <strong>#{{ $slipPublicNo }}</strong> sebagai referensi resmi THR Anda.
                        Untuk verifikasi internal HR gunakan kode BATCH-{{ $batch->id }} · LINE-{{ $line->id }}.
                    </p>
                </div>
                <div class="panel-row">
                    <p class="muted mb-0" style="line-height:1.45;">
                        Dokumen ini dihasilkan secara sistem. PPh 21 final mengikuti bukti potong / laporan pajak yang berlaku.
                    </p>
                </div>
            </td>
        </tr>
    </table>

    <div class="total-bar">
        <p class="total-amount mb-0">
            THR bruto: Rp {{ number_format((float) $line->thr_gross, 0, ',', '.') }}
        </p>
        <p class="muted mt-2 mb-0" style="line-height:1.45;">
            Nomor slip THR <strong>#{{ $slipPublicNo }}</strong> — tunjukkan nomor ini saat konsultasi ke HR atau audit data.
        </p>
    </div>
</body>
</html>
