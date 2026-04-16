# Multi-Tenancy Isolation & Data Leak Audit Plan

## Tujuan
Menjamin seluruh data HCM/HRIS hanya dapat diakses oleh tenant (company) yang berhak, mencegah kebocoran data antar customer, dan memastikan role/permission berjalan sesuai scope.

---

## 1. Daftar Model Tenant Utama (Prioritas Patch)
1. EmployeeProfile
2. AttendanceRecord
3. LeaveRequest
4. HcmPayrollRun
5. HcmPayrollPeriod
6. HcmPromotion
7. HcmResignation
8. HcmTermination
9. OvertimeRequest
10. PerformanceReview

---

## 2. Langkah Bertahap
- [x] Patch global scope `company_id` di EmployeeProfile
- [ ] Jalankan seluruh test (`php artisan test`)
- [ ] Jika lolos, lanjut ke model berikutnya
- [ ] Jika error, audit root cause, tambahkan pengecualian/adjustment, baru lanjut
- [ ] Ulangi hingga semua model utama aman

---

## 3. Audit Controller & Query Manual
- [ ] Audit semua controller/service untuk query manual, join, atau raw SQL
- [ ] Pastikan semua query selalu filter `company_id`
- [ ] Refactor ke Eloquent (dengan global scope) jika memungkinkan

---

## 4. Perluas Test Otomatis
- [ ] Tambahkan/extend feature test untuk semua endpoint penting
- [ ] Test dengan user dari company berbeda
- [ ] Pastikan data yang keluar hanya milik company user

---

## 5. Audit Frontend
- [ ] Pastikan frontend tidak pernah cache atau akses data global tanpa filter

---

## 6. Dokumentasi & Checklist
- [ ] Catat model yang sudah di-patch
- [ ] Catat test yang sudah lolos
- [ ] Catat exception/relasi cross-company yang perlu penanganan khusus

---

## Catatan
- Setiap patch model → test → review error → lanjut.
- Jika ada relasi/reporting yang memang cross-company, gunakan `withoutGlobalScope('company')` secara eksplisit.
- Semua perubahan harus evidence-based (ada log/test/laporan).
