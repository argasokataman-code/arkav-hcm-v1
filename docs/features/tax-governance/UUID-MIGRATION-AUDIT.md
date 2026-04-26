# UUID Migration Audit - Tax Governance (Phase 8)

Tanggal audit: 2026-04-27

## Ringkasan

Audit ini memetakan penggunaan identifier policy tax governance untuk menutup numeric exposure pada API publik sambil menjaga backward compatibility sementara.

## Temuan Runtime

1. Endpoint lifecycle policy awalnya menerima UUID-only path.
2. Tidak ada endpoint migration bridge UUID+numeric pada runtime policy lifecycle.
3. Tidak ada telemetry khusus numeric path usage.
4. Tidak ada deprecation headers untuk client migration guidance.

## Temuan Kontrak Dokumentasi

1. OpenAPI menggunakan path `{policyUuid}` UUID-only.
2. Belum ada catatan transisi UUID+numeric fallback di OpenAPI.
3. Belum ada migration guide terpisah untuk client integrator.

## Perubahan Phase 8

1. Policy path di route runtime diganti menjadi `{policyRef}` dengan regex UUID or numeric.
2. Controller menambahkan resolver internal UUID/numeric bridge.
3. Numeric usage menambahkan telemetry log event.
4. Numeric usage menambahkan response deprecation headers + sunset.
5. Event history payload dipindah dari numeric-centric ke UUID-centric fields.
6. Dokumen migration guide ditambahkan di `docs/api/uuid-migration-guide.md`.

## Status Pasca Implementasi

1. API publik tetap kompatibel selama migration window.
2. UUID menjadi identifier utama di payload kontrak.
3. Numeric path usage bisa dimonitor sampai sunset date.
4. Roadmap next step: disable numeric fallback setelah usage turun ke nol.
