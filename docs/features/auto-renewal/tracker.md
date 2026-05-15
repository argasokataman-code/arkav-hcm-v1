# Auto-Renewal Tracker

## Status Snapshot

- Tanggal: 2026-05-14
- Status: runtime implementation done (Xendit-only policy)
- Scope aktif: hardening non-gateway follow-up selesai (UI 422 handling + docs/tracker sync + lifecycle notification fixes)

## Coverage Checklist vs Prompt Spec

1. lifecycle states lengkap: done
2. database design lengkap: done
3. auto-renewal flow detail: done
4. scheduler policy 30/60 menit: done (design)
5. idempotency and locking strategy: done
6. payment retry policy 1h/24h/3d: done
7. grace period design: done
8. webhook idempotency and signature validation: done
9. reconciliation flow: done
10. notification lifecycle: done
11. plan snapshot immutable: done
12. entitlement layer: done
13. negative scenario matrix lengkap: done
14. monitoring and alerting: done
15. testing strategy (unit/integration/concurrency): done
16. audit logging strategy: done

## Evidence Files

- README index: [README.md](README.md)
- implementation blueprint: [IMPLEMENTATION.md](IMPLEMENTATION.md)
- canonical API contract: [../../api/saas-renewal-monitoring-api.md](../../api/saas-renewal-monitoring-api.md)
- canonical OpenAPI: [../../api/openapi.yaml](../../api/openapi.yaml)

Catatan:
- detail database, monitoring, scenario, dan verification gate sudah dikonsolidasikan ke `IMPLEMENTATION.md`

## Remaining Work (Code, Bukan Dokumen)

1. hardening UX validasi filter 422 di monitoring page: done
2. sinkronisasi reason-code documentation terhadap runtime terbaru: done
3. sinkronisasi grace period docs terhadap config runtime: done

## Tracker Implementasi Runtime (Actionable)

Status legend:
- not_started
- in_progress
- blocked
- done

### Fase 1 - Fondasi Data dan Idempotency

1. [x] migration idempotency renewal key per subscription+period - status: done
2. [x] migration reason_code + reason_message untuk status renewal - status: done
3. [x] unique constraint anti duplicate renewal invoice period - status: done
4. [x] backfill/compatibility script untuk data lama - status: done
5. [x] test: duplicate retry tidak membuat invoice/charge ganda - status: done

### Fase 2 - Renewal Engine dan Lifecycle

1. [x] orchestrator: lock row + generate renewal_period_key di awal transaksi - status: done
2. [x] retry policy runtime 1h/24h/3d - status: done
3. [x] lifecycle grace_period -> suspended -> resumed - status: done
4. [x] audit event immutable untuk seluruh transisi status - status: done
5. [x] test concurrency (parallel worker/webhook) - status: done

### Fase 3 - Reconciliation, API, dan Monitoring UI

1. [x] job reconciliation pending renewal payments - status: done
2. [x] API summary/records/anomalies untuk global monitoring - status: done
3. [x] halaman `/saas/renewal-monitoring` + data loader JS - status: done
4. [x] metric dan alert (gateway down, worker crash, failure spike) - status: done
5. [x] E2E role guard: global admin boleh akses, non-global 403/redirect - status: done

## Start Point Hari Ini (Disiplin Eksekusi)

Mulai dari item Fase 1 nomor 1-3 dulu dalam satu PR kecil.

Active focus sekarang:
1. monitoring UI global admin
2. metric + alerting renewal anomaly
3. E2E role guard monitoring page

Kriteria selesai PR pertama:
1. duplicate renewal per period mustahil secara DB constraint
2. reason_code + reason_message sudah tersimpan saat status berubah
3. test minimal untuk idempotency basic lulus

## Next Checkpoint

- Checkpoint 1 (setelah PR Fase 1): tandai 5 item Fase 1 menjadi done/in_progress sesuai real progress.
- Checkpoint 2 (setelah PR Fase 2): validasi ulang flow paid/failed/grace/suspended di test integration.
- Checkpoint 3 (setelah PR Fase 3): manual smoke pada halaman global monitoring + alert trigger simulation.

## Tambahan Requirement 2026-05-14 (Global Renewal Monitoring)

Closed di dokumen:
1. Menu/fitur global admin untuk renewal monitoring ditambahkan.
2. API monitoring lintas tenant ditambahkan.
3. Status renewal wajib reason_code + reason_message didokumentasikan.
4. Katalog anomali mencakup feature crash dan third-party gateway down (contoh Xendit).

Open untuk implementasi runtime:
1. none (runtime core closed untuk scope saat ini)

## Policy Notes (2026-05-14)

1. Renewal reconciliation runtime yang aktif saat ini: Xendit-only.
2. Temuan terkait multi-gateway reconciliation dicatat sebagai backlog policy, bukan bug runtime aktif.
3. Auto-renew toggle belum diekspos sebagai kontrol di company checkout UI; status saat ini hanya ditampilkan sebagai informasi.

## Audit Follow-up (2026-05-14)

1. Bug notifikasi invoice issued/payment failed terkait pemakaian kolom invoice `amount` sudah diperbaiki ke `amount_due`.
2. Notifikasi lifecycle sekarang mencakup:
	- grace period started
	- warning H-1 sebelum suspend
	- subscription suspended
3. Bug `$company->billingContact`/`primaryContact` tidak ada di model — seluruh notifikasi diam-diam tidak terkirim. Fixed ke `$company->owner`.
4. Bug `$invoice->currency` tidak ada di model Invoice (3 tempat). Fixed ke `$company->currency ?? 'IDR'`.
5. Bug R1 webhook: `handleXenditPaymentSuccessful()` hanya mark invoice paid tanpa extend subscription. Fixed ke `markRecurringRenewalPaidFromWebhook()` (idempotent + extend).
6. Admin email alert ditambahkan via `notifyAdminOperationalAlert()`:
	- `gateway_down` / `XENDIT_DOWN` → email ke semua super-admin
	- `worker_crash` / `RENEWAL_WORKER_CRASHED` → email ke semua super-admin
	- `failure_spike` / `RENEWAL_FAILURE_SPIKE` (>= 3 failures/24h) → email ke semua super-admin

## Boundary Dokumen

- Folder `docs/features/auto-renewal/` sekarang hanya menyimpan tiga file inti: `README.md`, `IMPLEMENTATION.md`, dan `tracker.md`.
- Folder `docs/api/` adalah sumber tunggal untuk kontrak request/response/RBAC endpoint.
- Jika route/controller berubah, update `docs/api/openapi.yaml` + dokumen API terkait; jangan tambah ulang kontrak ke folder feature ini.

## Cleanup Status

1. appendix feature docs dihapus agar tidak drift
2. feature folder dipersempit ke tiga file inti
3. kontrak API tetap canonical di `docs/api`
