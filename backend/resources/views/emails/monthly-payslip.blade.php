<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Payslip</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.5;">
    @php
        $period = $slip['period'] ?? [];
        $employee = $slip['employee'] ?? [];
        $totals = $slip['totals'] ?? [];
        $label = sprintf('%02d/%04d', (int) ($period['periodMonth'] ?? 0), (int) ($period['periodYear'] ?? 0));
    @endphp

    <p>Halo {{ $employee['name'] ?? $user->name }},</p>
    <p>Berikut kami kirim slip gaji periode <strong>{{ $label }}</strong>.</p>

    <table cellpadding="0" cellspacing="0" border="0" style="margin: 16px 0; min-width: 320px;">
        <tr>
            <td style="padding: 4px 12px 4px 0; color: #6b7280;">No. Slip</td>
            <td style="padding: 4px 0;"><strong>{{ $slip['slipNumber'] ?? '-' }}</strong></td>
        </tr>
        <tr>
            <td style="padding: 4px 12px 4px 0; color: #6b7280;">Penghasilan</td>
            <td style="padding: 4px 0;">Rp {{ number_format((float) ($totals['earningsTotal'] ?? 0), 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td style="padding: 4px 12px 4px 0; color: #6b7280;">Potongan</td>
            <td style="padding: 4px 0;">Rp {{ number_format((float) ($totals['deductionsTotal'] ?? 0), 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td style="padding: 4px 12px 4px 0; color: #6b7280;">Overtime</td>
            <td style="padding: 4px 0;">Rp {{ number_format((float) ($totals['overtimeTotal'] ?? 0), 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td style="padding: 4px 12px 4px 0; color: #6b7280;">Take Home Pay</td>
            <td style="padding: 4px 0;"><strong>Rp {{ number_format((float) ($totals['netPay'] ?? 0), 0, ',', '.') }}</strong></td>
        </tr>
    </table>

    <p>File PDF slip gaji terlampir pada email ini.</p>

    @if($isEncrypted ?? false)
    <div style="margin: 20px 0; padding: 16px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px;">
        <p style="margin: 0 0 8px; font-weight: 600; color: #856404;">🔒 Dokumen Terenkripsi</p>
        <p style="margin: 0; font-size: 13px; color: #856404;">
            File PDF ini telah dienkripsi untuk keamanan data Anda.<br>
            Gunakan password berikut untuk membuka file:<br>
            <strong style="font-size: 18px; font-family: monospace;">{{ $decryptionPassword }}</strong><br>
            <small>Password: <strong>SLIP</strong> + 6 digit terakhir NIK Anda.</small>
        </p>
    </div>
    @endif

    <p>Salam,<br>{{ $appName }}</p>
</body>
</html>
