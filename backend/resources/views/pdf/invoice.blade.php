<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 28px 30px 34px; }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10.5px; color: #1f2a44; margin: 0; }
        .muted { color: #6b7280; }
        .small { font-size: 9.2px; }
        .right { text-align: right; }
        .center { text-align: center; }
        .bold { font-weight: bold; }
        .accent { color: #fc7f01; }
        .header-card { border: 1px solid #e6ebf2; background: #fbfcfe; padding: 14px 16px; margin-bottom: 16px; }
        .section-card { border: 1px solid #e6ebf2; padding: 12px 14px; margin-bottom: 14px; }
        .section-title { font-size: 9px; letter-spacing: 0.08em; text-transform: uppercase; color: #6b7280; margin: 0 0 8px; }
        .invoice-title { font-size: 22px; font-weight: bold; color: #18233d; margin: 0; }
        .invoice-subtitle { margin: 4px 0 0; font-size: 10px; color: #6b7280; }
        .hero-total { background: #fff7ed; border: 1px solid #fdba74; padding: 12px 14px; }
        .hero-total .label { font-size: 9px; text-transform: uppercase; letter-spacing: 0.06em; color: #9a3412; margin-bottom: 4px; }
        .hero-total .amount { font-size: 20px; font-weight: bold; color: #c2410c; }
        .hero-total .status { margin-top: 6px; font-size: 9px; color: #7c2d12; }
        table { width: 100%; border-collapse: collapse; }
        .meta-table td { vertical-align: top; padding: 0; }
        .meta-box { width: 48%; }
        .meta-grid { width: 100%; border-collapse: collapse; }
        .meta-grid td { padding: 7px 0; border-bottom: 1px solid #edf1f6; }
        .meta-grid td:first-child { width: 42%; color: #6b7280; }
        .summary-table th { text-align: left; font-size: 9px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.04em; padding: 8px 10px; background: #f8fafc; border-bottom: 1px solid #e6ebf2; }
        .summary-table td { padding: 10px; border-bottom: 1px solid #edf1f6; }
        .summary-table td:last-child,
        .summary-table th:last-child { text-align: right; }
        .summary-total td { background: #f8fafc; font-weight: bold; }
        .status-pill { display: inline-block; padding: 4px 9px; border: 1px solid #cbd5e1; color: #334155; font-size: 9px; text-transform: uppercase; letter-spacing: 0.05em; }
        .status-paid { background: #ecfdf5; border-color: #86efac; color: #166534; }
        .status-sent { background: #eff6ff; border-color: #93c5fd; color: #1d4ed8; }
        .status-draft { background: #f8fafc; border-color: #cbd5e1; color: #475569; }
        .status-expired { background: #fef2f2; border-color: #fca5a5; color: #b91c1c; }
        .footer-note { border-top: 1px solid #e6ebf2; padding-top: 12px; margin-top: 16px; }
        .notes-box { background: #fbfcfe; border: 1px solid #e6ebf2; padding: 10px 12px; min-height: 58px; }
    </style>
</head>
<body>
@php
    $issuer = trim((string) ($appName ?? config('app.name') ?? 'Arkav'));
    $issuerAddress = trim((string) ($companyAddress ?? ''));
    $company = $invoice->company;
    $companyName = trim((string) ($company?->name ?? 'Unknown Company'));
    $billTo = is_array($companyProfile ?? null) ? $companyProfile : [];
    $companyLegalName = trim((string) ($billTo['legalName'] ?? ($company?->legal_name ?? '')));
    $companyAddress = trim((string) ($billTo['address'] ?? ''));
    $companyCity = trim((string) ($billTo['city'] ?? ''));
    $companyState = trim((string) ($billTo['state'] ?? ''));
    $companyCountry = trim((string) ($billTo['country'] ?? ''));
    $companyPostalCode = trim((string) ($billTo['postalCode'] ?? ''));
    $locationChunks = array_values(array_filter([$companyCity, $companyState, $companyCountry], static fn ($value) => $value !== ''));
    $locationLine = !empty($locationChunks) ? implode(', ', $locationChunks) : '';
    $invoiceNumber = trim((string) ($invoice->invoice_number ?? '-'));
    $issueDate = optional($invoice->issue_date)->format('d M Y') ?? '-';
    $dueDate = optional($invoice->due_date)->format('d M Y') ?? '-';
    $paidDate = optional($invoice->paid_date)->format('d M Y') ?? null;
    $amountDue = 'Rp '.number_format((float) $invoice->amount_due, 0, ',', '.');
    $status = strtolower(trim((string) ($invoice->status ?? 'draft')));
    $statusClass = match ($status) {
        'paid' => 'status-paid',
        'sent', 'viewed' => 'status-sent',
        'expired' => 'status-expired',
        default => 'status-draft',
    };
    $statusLabel = strtoupper($status !== '' ? $status : 'draft');
    $notes = trim((string) ($invoice->notes ?? ''));
    $lineLabel = $invoice->subscription_id ? 'Subscription billing' : 'Invoice amount';
    $subscription = $invoice->subscription;
    $packageName = trim((string) ($subscription?->package?->name ?? ($subscription?->plan_code ? \Illuminate\Support\Str::headline((string) $subscription->plan_code) : '')));
    $billingCycle = $subscription?->billing_cycle;
    $billingCycleLabel = match ($billingCycle) {
        'monthly' => 'Bulanan',
        'yearly' => 'Tahunan',
        default => '-',
    };
    $nextBillingDate = $subscription?->status === 'trial'
        ? optional($subscription?->trial_ends_at)->format('d M Y')
        : optional($subscription?->ends_at)->format('d M Y');
@endphp

    <table class="meta-table" cellspacing="0" cellpadding="0">
        <tr>
            <td width="62%" style="padding-right:12px;">
                <div class="header-card">
                    <div class="small accent bold">Billing Document</div>
                    <p class="invoice-title">Invoice</p>
                    <p class="invoice-subtitle">Dokumen tagihan resmi untuk company tenant yang aktif.</p>

                    <div style="margin-top:14px;">
                        <div class="bold" style="font-size:11px; margin-bottom:4px;">{{ $issuer }}</div>
                        <div class="muted small">{{ $issuerAddress !== '' ? $issuerAddress : 'Billing & Finance Department' }}</div>
                    </div>
                </div>
            </td>
            <td width="38%" valign="top">
                <div class="hero-total">
                    <div class="label">Amount Due</div>
                    <div class="amount">{{ $amountDue }}</div>
                    <div class="status">
                        Status:
                        <span class="status-pill {{ $statusClass }}">{{ $statusLabel }}</span>
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <table class="meta-table" cellspacing="0" cellpadding="0">
        <tr>
            <td class="meta-box" style="padding-right:10px;">
                <div class="section-card">
                    <p class="section-title">Bill To</p>
                    <div class="bold" style="font-size:11px; margin-bottom:4px;">{{ $companyName }}</div>
                    @if ($companyLegalName !== '')
                        <div class="muted small">Legal Name: {{ $companyLegalName }}</div>
                    @endif
                    @if ($companyAddress !== '')
                        <div class="muted small">{{ $companyAddress }}</div>
                    @endif
                    @if ($locationLine !== '')
                        <div class="muted small">{{ $locationLine }}{{ $companyPostalCode !== '' ? ' '.$companyPostalCode : '' }}</div>
                    @elseif ($companyPostalCode !== '')
                        <div class="muted small">Postal Code: {{ $companyPostalCode }}</div>
                    @endif
                    <div class="muted small">Company ID: {{ $invoice->company_id }}</div>
                    @if (!empty($company?->code))
                        <div class="muted small">Company Code: {{ $company->code }}</div>
                    @endif
                </div>
            </td>
            <td class="meta-box" style="padding-left:10px;">
                <div class="section-card">
                    <p class="section-title">Invoice Meta</p>
                    <table class="meta-grid">
                        <tr><td>Invoice Number</td><td class="right bold">{{ $invoiceNumber }}</td></tr>
                        <tr><td>Issue Date</td><td class="right">{{ $issueDate }}</td></tr>
                        <tr><td>Due Date</td><td class="right">{{ $dueDate }}</td></tr>
                        <tr><td>Payment Date</td><td class="right">{{ $paidDate ?: '-' }}</td></tr>
                        <tr><td>Package</td><td class="right">{{ $packageName !== '' ? $packageName : '-' }}</td></tr>
                        <tr><td>Billing Cycle</td><td class="right">{{ $billingCycleLabel }}</td></tr>
                        <tr><td>Next Payment</td><td class="right">{{ $nextBillingDate ?: '-' }}</td></tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    <div class="section-card">
        <p class="section-title">Charge Summary</p>
        <table class="summary-table">
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Status</th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <div class="bold">{{ $lineLabel }}</div>
                        <div class="muted small">
                            @if ($invoice->subscription_id)
                                Tagihan untuk aktivasi atau perpanjangan subscription company.
                                @if ($packageName !== '') Package: {{ $packageName }}. @endif
                                Billing cycle: {{ $billingCycleLabel }}. Next payment: {{ $nextBillingDate ?: '-' }}.
                            @else
                                Tagihan billing company sesuai invoice yang diterbitkan sistem.
                            @endif
                        </div>
                    </td>
                    <td><span class="status-pill {{ $statusClass }}">{{ $statusLabel }}</span></td>
                    <td>{{ $amountDue }}</td>
                </tr>
                <tr class="summary-total">
                    <td colspan="2" class="right">Total Due</td>
                    <td>{{ $amountDue }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <table class="meta-table" cellspacing="0" cellpadding="0">
        <tr>
            <td class="meta-box" style="padding-right:10px;">
                <div class="section-card">
                    <p class="section-title">Payment Guidance</p>
                    <div class="small muted">Simpan dokumen ini sebagai bukti billing. Jika status invoice masih unpaid, selesaikan pembayaran sebelum tanggal jatuh tempo untuk menghindari gangguan aktivasi layanan.</div>
                </div>
            </td>
            <td class="meta-box" style="padding-left:10px;">
                <div class="section-card">
                    <p class="section-title">Notes</p>
                    <div class="notes-box small {{ $notes === '' ? 'muted' : '' }}">{{ $notes !== '' ? $notes : 'Tidak ada catatan tambahan untuk invoice ini.' }}</div>
                </div>
            </td>
        </tr>
    </table>

    <div class="footer-note small muted center">
        Dokumen ini dihasilkan otomatis oleh {{ $issuer }} pada {{ now()->format('d M Y H:i') }}.
    </div>
</body>
</html>