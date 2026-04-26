# Reviewer Compliance Checklist

Checklist ringkas ini dipakai reviewer sebelum approve perubahan operasional, docs, atau deploy prep.

## A. Format rekomendasi

- [ ] Langkah tindak lanjut atau saran ditulis sekaligus dalam satu list lengkap.
- [ ] Urutan saran disusun dari mandatory ke opsional.
- [ ] Tidak ada rekomendasi bertahap satu-per-satu untuk konteks yang sama.

## B. Deploy prep discipline

- [ ] Tidak ada auto-push tanpa konfirmasi operator.
- [ ] Alur commit code/docs -> build artifact -> commit artifact -> push sudah dipatuhi.
- [ ] Guard artifact sync menyatakan PASS untuk commit target.
- [ ] Runtime deploy guard menyatakan PASS.

## C. Local-first evidence

- [ ] Local test gate dijalankan dan hasilnya terdokumentasi.
- [ ] Jika API berubah, OpenAPI dan feature API docs sudah sinkron.
- [ ] Tracker/status docs fitur yang terdampak sudah diperbarui.

## D. Quick commands

```bash
bash scripts/local-test-gate.sh
bash scripts/check-shared-hosting-artifact-sync.sh
bash scripts/check-deploy-runtime-guard.sh
bash scripts/lint-next-step-format.sh
```
