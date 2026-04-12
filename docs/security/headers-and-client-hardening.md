# Security headers & perilaku klien

## Header global (`SecurityHeadersMiddleware`)

Diterapkan pada **semua** respons (web + API) setelah middleware berjalan:

| Header | Nilai | Tujuan |
|--------|--------|--------|
| `X-Content-Type-Options` | `nosniff` | Mengurangi MIME sniffing |
| `X-Frame-Options` | `SAMEORIGIN` | Mengurangi clickjacking (iframe lintas origin) |
| `Referrer-Policy` | `strict-origin-when-cross-origin` | Membatasi kebocoran URL penuh ke pihak ketiga |
| `Permissions-Policy` | `accelerometer=(), camera=(), microphone=(), payment=(), usb=()` | Mematikan API perangkat yang tidak dipakai global |

## Geolocation (absensi / peta)

`Permissions-Policy` **tidak** memasukkan `geolocation` sehingga halaman seperti `/attendance-employee` tetap dapat meminta lokasi lewat **Geolocation API** browser sesuai izin pengguna. Jika produk ingin membatasi ke domain tertentu, sesuaikan nilai policy (mis. `geolocation=(self)`) setelah uji regresi.

## Cache pada 404 tamu

Respons `error-404-guest` dari guard web menetapkan `Cache-Control: no-store` di middleware guard (bukan di `SecurityHeadersMiddleware`).

## Content-Security-Policy (CSP)

Belum diaktifkan global karena template memuat banyak skrip/style; rencanakan di [hardening-recommendations.md](./hardening-recommendations.md).
