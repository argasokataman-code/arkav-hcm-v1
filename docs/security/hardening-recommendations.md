# Rekomendasi hardening (lanjutan)

Dokumen ini mencatat peningkatan keamanan yang **belum** wajib diimplementasi sebagai satu patch, tetapi disarankan untuk lingkungan produksi keras.

## Jaringan & infrastruktur

1. **TLS penuh** — terminasi HTTPS di reverse proxy; HSTS.
2. **Pembatasan `/api-docs` dan `/health`** — allowlist IP internal atau nonaktifkan docs di publik.
3. **WAF / rate-based filter** — melengkapi throttle aplikasi untuk pola abuse.

## Aplikasi

1. **CSP (Content-Security-Policy)** — mengurangi XSS; butuh audit aset inline (Blade/JS) agar tidak memutus UI.
2. **SameSite cookie** — pastikan cookie token sesuai kebutuhan cross-site (biasanya `Lax` atau `Strict` untuk HRMS same-site).
3. **Rotasi & revoke token** — logout sudah revoke; pertimbangkan masa pakai pendek + refresh untuk risiko tinggi.
4. **2FA** — untuk admin HCM / super admin (belum standar di Phase 1).
5. **Jangan menambah `public_paths` / `public_prefixes` di produksi** tanpa review — itu satu-satunya cara membuka halaman web bagi tamu.

## Operasi

1. **Logging akses gagal** — agregasi 401/403 (tanpa menyimpan password).
2. **Backup terenkripsi** — skema DB berisi data personal.
3. **Dependency audit** — `composer audit` / CI berkala.

## Kepatuhan

- Kebijakan retensi data dan akses internal mengacu ke kebutuhan organisasi (UU PDP, dll.) — di luar cakupan teknis file ini.
