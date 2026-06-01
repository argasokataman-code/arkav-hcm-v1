@component('mail::message')
# Pembayaran Berhasil

Halo {{ $company->name ?? 'Pelanggan' }},

Pembayaran untuk invoice **{{ $invoice->invoice_number }}** berhasil kami terima.

**Ringkasan pembayaran:**
- Invoice: {{ $invoice->invoice_number }}
- Jumlah: {{ number_format((float) $invoice->amount_due, 2) }}
- Tanggal bayar: {{ optional($invoice->paid_date)->format('Y-m-d H:i') ?? now()->format('Y-m-d H:i') }}
- Status: {{ ucfirst((string) $invoice->status) }}

**⚠️ Penting — Simpan Company Code Anda:**

Company code **{{ $company->code ?? '-' }}** wajib digunakan setiap kali login ke Arkav HCM. Tanpa company code ini, Anda tidak dapat mengakses sistem. Harap simpan dengan baik.

@component('mail::button', ['url' => config('app.url') . '/subscription'])
Buka Halaman Subscription
@endcomponent

Terima kasih,

{{ $issuerName ?? config('app.name') }}
@endcomponent
