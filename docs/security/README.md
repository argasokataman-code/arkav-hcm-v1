# Dokumentasi keamanan Arcav HCM

Folder ini mengumpulkan **inventaris permukaan serangan**, **kebijakan guard**, dan **rekomendasi hardening**. Bukan pengganti audit pihak ketiga atau penetration test.

## Isi

| Dokumen | Isi |
|--------|-----|
| [inventory-and-surface.md](./inventory-and-surface.md) | Daftar layanan HTTP (web vs API), auth per grup, temuan audit |
| [hcm-web-route-guard.md](./hcm-web-route-guard.md) | Middleware web, whitelist publik tunggal, 404 tamu |
| [hardening-recommendations.md](./hardening-recommendations.md) | Langkah lanjutan (WAF, rate limit, secret, CSP, dll.) |
| [headers-and-client-hardening.md](./headers-and-client-hardening.md) | Security headers global + catatan geolocation / iframe |
| **[Security Check SOP](../features/security-check/)** | **3-tier gate system:** pre-push (manual), pre-merge (CI), pre-release (DAST). Cycles 0-4 complete: secret containment, validation hardening, npm audit → 0, SAST gates. |

## Checklist kesadaran develop (ringkas)

Versi lengkap ada di rule Cursor **`application-security-baseline`** (`.cursor/rules/application-security-baseline.mdc`). Inti:

- **Auth:** API pakai `api.token`; RBAC di server; web pakai **whitelist publik saja** (selain itu wajib auth).
- **Data:** validasi server-side; cegah IDOR pada resource per user; hati-hati upload & mass assignment.
- **Secret:** tidak di repo; audit dependency berkala.
- **Dokumen:** permukaan baru → update inventaris security + spec/OpenAPI jika kontrak berubah.
- **Verifikasi:** tes 401/403/422 + smoke guard web bila relevan.

## Prinsip singkat

1. **API** `/v1/hcm/*`: wajib token (`api.token`) kecuali identitas publik di bawah `/v1/identity/auth/register|login`.
2. **Web GET/HEAD**: hanya path di `public_paths` / `public_prefixes` yang boleh tanpa auth; selebihnya cookie API valid atau sesi web (satu mode, tanpa env legacy).
3. **RBAC bisnis** tetap di controller API (`EnsuresHcmAdmin`, ownership); guard web tidak menggantikan itu.
