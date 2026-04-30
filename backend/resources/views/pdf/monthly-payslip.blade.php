<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10.5px; color: #202c4b; margin: 0; padding: 22px 24px 28px; }
        .primary { color: #fc7f01; }
        .muted { color: #6b7280; font-size: 9.5px; }
        .fw-bold { font-weight: bold; }
        .text-end { text-align: right; }
        .text-center { text-align: center; }
        .border-b { border-bottom: 1px solid #e8ecf1; }
        .mb-0 { margin-bottom: 0; }
        .mb-1 { margin-bottom: 4px; }
        .mb-2 { margin-bottom: 8px; }
        .mb-3 { margin-bottom: 14px; }
        .logo { max-height: 42px; max-width: 160px; }
        .section-title { font-size: 10px; font-weight: bold; color: #202c4b; margin: 0 0 6px; }
        .panel-head { background: #f8f9fa; border: 1px solid #e8ecf1; border-bottom: none; padding: 8px 10px; font-weight: bold; font-size: 10px; }
        .panel-row { border: 1px solid #e8ecf1; border-top: none; padding: 7px 10px; }
        .panel-row-flex { width: 100%; border-collapse: collapse; }
        .panel-row-flex td { padding: 0; vertical-align: middle; }
        .panel-row-flex td:last-child { text-align: right; font-weight: bold; }
        .total-bar { border: 1px solid #e8ecf1; background: #fafbfc; padding: 12px 14px; margin-top: 16px; }
        .total-amount { font-size: 13px; font-weight: bold; color: #202c4b; }
    </style>
</head>
<body>
@php
    $period = $slip['period'] ?? null;
    $employee = $slip['employee'] ?? [];
    $earnings = $slip['earnings'] ?? [];
    $deductions = $slip['deductions'] ?? [];
    $totals = $slip['totals'] ?? ['earningsTotal' => 0, 'deductionsTotal' => 0, 'netPay' => 0];
    $slipNumber = $slip['slipNumber'] ?? '#';
    $addr = is_string($companyAddress ?? null) ? trim((string) $companyAddress) : '';
    $cname = is_string($companyName ?? null) ? trim((string) $companyName) : '';
    $periodLabel = $period ? sprintf('%02d/%04d', $period['periodMonth'], $period['periodYear']) : '—';
@endphp

    <div style="border:1px solid #e8ecf1; background:#fafbfc; padding:10px 14px; margin-bottom:14px; text-align:center;">
        <div class="muted" style="font-size:9px; text-transform:uppercase; letter-spacing:0.04em;">Slip Gaji Bulanan</div>
        <div class="primary" style="font-size:17px; font-weight:bold; margin-top:2px; letter-spacing:0.02em;">#{{ $slipNumber }}</div>
    </div>

    <table width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;" class="mb-3 border-b">
        <tr>
            <td width="52%" valign="top" style="padding-bottom:14px;">
                @if (!empty($logoDataUri))
                    <img src="{{ $logoDataUri }}" alt="Logo" class="logo" />
                @endif
                <p class="fw-bold mb-1" style="font-size:11px;margin-top:6px;">{{ $cname }}</p>
                @if ($addr !== '')
                    <p class="muted mb-0" style="max-width:260px;">{{ $addr }}</p>
                @endif
            </td>
            <td width="48%" valign="top" class="text-end" style="padding-bottom:14px;">
                <p class="mb-1"><span class="muted">Periode:</span> <span class="fw-bold">{{ $periodLabel }}</span></p>
                <p class="mb-1"><span class="muted">Status run:</span> <span class="fw-bold">{{ $slip['run']['status'] ?? '—' }}</span></p>
                <p class="mb-0"><span class="muted">Dicetak:</span> <span class="fw-bold">{{ now()->format('d/m/Y H:i') }}</span></p>
            </td>
        </tr>
    </table>

    <table width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;" class="mb-3 border-b">
        <tr>
            <td width="48%" valign="top" style="padding:0 12px 14px 0;">
                <p class="section-title">Dari</p>
                <p class="fw-bold mb-1" style="font-size:11px;">{{ $cname }}</p>
            </td>
            <td width="48%" valign="top" style="padding:0 0 14px 12px;">
                <p class="section-title">Kepada</p>
                <p class="fw-bold mb-1" style="font-size:11px;">{{ $employee['name'] ?? '—' }}</p>
                <p class="mb-1"><span class="muted">Email:</span> {{ $employee['email'] ?? '—' }}</p>
                <p class="mb-1"><span class="muted">Jabatan:</span> {{ $employee['designation'] ?? '—' }}</p>
                <p class="mb-0"><span class="muted">Tim:</span> {{ $employee['team'] ?? '—' }}</p>
            </td>
        </tr>
    </table>

    <p class="text-center fw-bold mb-3" style="font-size:12px;">Slip gaji untuk periode {{ $periodLabel }}</p>

    <table width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
        <tr>
            <td width="49%" valign="top" style="padding-right:8px;">
                <div class="panel-head">Additions</div>
                @forelse ($earnings as $row)
                    <div class="panel-row">
                        <table class="panel-row-flex"><tr>
                            <td>{{ $row['componentName'] ?? '—' }}</td>
                            <td>Rp {{ number_format((float) ($row['amount'] ?? 0), 2, ',', '.') }}</td>
                        </tr></table>
                    </div>
                @empty
                    <div class="panel-row"><span class="muted">Belum ada komponen pendapatan.</span></div>
                @endforelse
                <div class="panel-row" style="background:#f8fafc;">
                    <table class="panel-row-flex"><tr>
                        <td class="fw-bold">Total earnings</td>
                        <td class="primary fw-bold">Rp {{ number_format((float) ($totals['earningsTotal'] ?? 0), 2, ',', '.') }}</td>
                    </tr></table>
                </div>
            </td>
            <td width="49%" valign="top" style="padding-left:8px;">
                <div class="panel-head">Deductions</div>
                @forelse ($deductions as $row)
                    <div class="panel-row">
                        <table class="panel-row-flex"><tr>
                            <td>{{ $row['componentName'] ?? '—' }}</td>
                            <td>Rp {{ number_format((float) ($row['amount'] ?? 0), 2, ',', '.') }}</td>
                        </tr></table>
                    </div>
                @empty
                    <div class="panel-row"><span class="muted">Belum ada komponen potongan.</span></div>
                @endforelse
                <div class="panel-row" style="background:#f8fafc;">
                    <table class="panel-row-flex"><tr>
                        <td class="fw-bold">Total deductions</td>
                        <td class="fw-bold">Rp {{ number_format((float) ($totals['deductionsTotal'] ?? 0), 2, ',', '.') }}</td>
                    </tr></table>
                </div>
            </td>
        </tr>
    </table>

    <div class="total-bar">
        <p class="total-amount mb-0">Take home pay: Rp {{ number_format((float) ($totals['netPay'] ?? 0), 2, ',', '.') }}</p>
        <p class="muted mb-0" style="margin-top:6px;">Dokumen ini dihasilkan otomatis dari run payroll yang sudah difinalisasi.</p>
    </div>
</body>
</html>
