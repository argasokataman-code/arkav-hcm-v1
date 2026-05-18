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
    <p>Salam,<br>{{ $appName }}</p>
</body>
</html>
