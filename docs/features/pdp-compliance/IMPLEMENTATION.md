# Panduan Implementasi Teknis — UU PDP Compliance

**Dokumen ini adalah panduan teknis untuk engineer yang mengerjakan remediasi.**  
Ikuti urutan siklus dari `FIX-PLAN-CYCLES.md`. Setiap finding memiliki:
- Lokasi file yang perlu diubah
- Kode yang perlu ditambah/dimodifikasi
- Migrasi DB yang dibutuhkan
- Test yang harus dibuat

---

## CYCLE 1 — Quick Wins

---

### C1 — Consent Checkbox di Form Onboarding

#### 1a. Migrasi DB — Tambah kolom consent ke companies

```php
// database/migrations/xxxx_add_consent_fields_to_companies.php
Schema::table('companies', function (Blueprint $table) {
    $table->boolean('onboarding_consent_accepted')->default(false)->after('billing_email');
    $table->timestamp('onboarding_consent_at')->nullable()->after('onboarding_consent_accepted');
    $table->string('onboarding_consent_ip', 45)->nullable()->after('onboarding_consent_at');
});
```

#### 1b. View — Tambah consent checkbox ke form onboarding

```html
<!-- backend/resources/views/public/landing.blade.php -->
<!-- Di dalam #onboardingModal, sebelum tombol submit, tambah: -->
<div class="mb-3">
    <div class="form-check">
        <input class="form-check-input" type="checkbox" id="consentAccepted" name="consent_accepted" required>
        <label class="form-check-label" for="consentAccepted">
            Saya setuju dengan <a href="/privacy-policy" target="_blank">Kebijakan Privasi</a>
            dan <a href="/terms-condition" target="_blank">Syarat &amp; Ketentuan</a> ARCAV HCM.
            Data saya akan digunakan untuk keperluan layanan HR sesuai yang tercantum dalam kebijakan privasi.
        </label>
    </div>
    <div class="invalid-feedback">Anda harus menyetujui kebijakan privasi untuk melanjutkan.</div>
</div>
```

#### 1c. Controller — Validasi dan simpan consent

```php
// backend/app/Http/Controllers/Api/PublicOnboardingController.php
// Di method store(), tambah ke rules validasi:
'consent_accepted' => ['required', 'accepted'],

// Di dalam store() setelah company dibuat, simpan consent:
$company->update([
    'onboarding_consent_accepted' => true,
    'onboarding_consent_at'       => now(),
    'onboarding_consent_ip'       => $request->ip(),
]);
```

---

### C6 — Aktifkan Email Verifikasi

#### 6a. Model User

```php
// backend/app/Models/User.php
// Ubah baris yang dikomentari:
// use Illuminate\Contracts\Auth\MustVerifyEmail;
// Menjadi:
use Illuminate\Contracts\Auth\MustVerifyEmail;

// Tambahkan implements ke class:
class User extends Authenticatable implements MustVerifyEmail
```

#### 6b. Route — Pastikan email verification route aktif

```php
// backend/routes/auth.php atau web.php
// Pastikan route ini ada:
Route::get('/email/verify', [EmailVerificationPromptController::class, '__invoke'])
    ->middleware('auth')
    ->name('verification.notice');

Route::get('/email/verify/{id}/{hash}', [VerifyEmailController::class, '__invoke'])
    ->middleware(['auth', 'signed', 'throttle:6,1'])
    ->name('verification.verify');

Route::post('/email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
    ->middleware(['auth', 'throttle:6,1'])
    ->name('verification.send');
```

---

### H5 — Export Audit Log

#### 5a. Migrasi — Tabel export audit log

```php
// database/migrations/xxxx_create_export_audit_logs_table.php
Schema::create('export_audit_logs', function (Blueprint $table) {
    $table->id();
    $table->uuid('user_uuid')->index();
    $table->uuid('company_id')->index();
    $table->string('action', 100); // 'export_employees', 'export_departments', dll
    $table->string('format', 20);  // 'csv', 'xlsx', 'pdf'
    $table->integer('record_count')->nullable();
    $table->string('ip_address', 45)->nullable();
    $table->string('user_agent')->nullable();
    $table->json('filters_applied')->nullable(); // filter apa yang aktif saat export
    $table->timestamp('exported_at');
    $table->timestamps();
});
```

#### 5b. Helper method di HcmEmployeeController (atau trait)

```php
// Tambah ke backend/app/Http/Controllers/Api/HcmEmployeeController.php
// Atau buat trait: backend/app/Traits/LogsExportActivity.php

private function logExportAuditTrail(Request $request, string $action, string $format, int $recordCount, array $filters = []): void
{
    DB::table('export_audit_logs')->insert([
        'user_uuid'       => auth()->user()->uuid,
        'company_id'      => $request->header('X-Company-Id'),
        'action'          => $action,
        'format'          => $format,
        'record_count'    => $recordCount,
        'ip_address'      => $request->ip(),
        'user_agent'      => $request->userAgent(),
        'filters_applied' => json_encode($filters),
        'exported_at'     => now(),
        'created_at'      => now(),
        'updated_at'      => now(),
    ]);
}

// Panggil di semua method export:
public function exportEmployees(Request $request)
{
    // ... existing logic ...
    $this->logExportAuditTrail($request, 'export_employees', $format, count($employees));
    // ... return response ...
}
```

---

### H6 — Privacy Policy

Buat ulang `backend/resources/views/misc/privacy-policy.blade.php` dengan konten dalam Bahasa Indonesia yang mencakup:

1. **Identitas Pengendali Data**: ARCAV HCM (ganti "SmratHR")
2. **Data yang Dikumpulkan**: list lengkap per kategori
3. **Tujuan Pemrosesan**: HR management, payroll, absensi, billing
4. **Dasar Hukum**: Pasal 20 UU PDP (kontrak, kewajiban hukum, persetujuan)
5. **Pihak Ketiga**: Xendit (Singapura), Stripe (AS), OpenAI-compatible AI API
6. **Transfer Internasional**: penjelasan + dasar hukum (Pasal 49 UU PDP)
7. **Retensi Data**: tabel per kategori data
8. **Hak Subjek Data**: daftar Pasal 5-13 + cara mengajukan
9. **Kontak DPO**: `privacy@arcav.id` (atau email yang ditentukan tim)
10. **Perubahan Kebijakan**: prosedur notifikasi jika ada perubahan

---

## CYCLE 2 — Consent Karyawan + Biometrik/GPS

---

### C2 — Consent/Disclosure saat HR Input Karyawan

#### 2a. Migrasi — Kolom disclosure di employee_profiles

```php
// database/migrations/xxxx_add_data_disclosure_to_employee_profiles.php
Schema::table('employee_profiles', function (Blueprint $table) {
    $table->timestamp('data_disclosed_at')->nullable()->after('religion');
    $table->string('data_disclosed_by_uuid', 36)->nullable()->after('data_disclosed_at');
    $table->string('data_disclosed_ip', 45)->nullable()->after('data_disclosed_by_uuid');
});
```

#### 2b. View — Disclosure acknowledgment di form tambah karyawan

```html
<!-- Di form tambah karyawan (Blade), sebelum tombol simpan: -->
<div class="mb-3 border rounded p-3 bg-light">
    <small class="text-muted d-block mb-2">
        <strong>Dasar Pemrosesan Data:</strong> Data karyawan dikumpulkan berdasarkan 
        hubungan kerja (Pasal 20 huruf c UU No. 27/2022). Data yang akan disimpan meliputi 
        identitas, kontak, data keuangan (nomor rekening), dan data pajak (NPWP/BPJS).
    </small>
    <div class="form-check">
        <input class="form-check-input" type="checkbox" id="dataDisclosureAck" name="data_disclosure_acknowledged" required>
        <label class="form-check-label" for="dataDisclosureAck">
            Saya memahami bahwa saya mengumpulkan data ini atas dasar hubungan kerja
            dan bertanggung jawab atas keakuratan serta keamanannya.
        </label>
    </div>
</div>
```

#### 2c. Controller — Simpan acknowledgment

```php
// backend/app/Http/Controllers/Api/HcmEmployeeController.php
// Di method store(), tambah ke validasi:
'data_disclosure_acknowledged' => ['required', 'accepted'],

// Setelah employee profile dibuat:
$employeeProfile->update([
    'data_disclosed_at'      => now(),
    'data_disclosed_by_uuid' => auth()->user()->uuid,
    'data_disclosed_ip'      => $request->ip(),
]);
```

---

### C3 & C4 — Consent Biometrik dan GPS untuk Absensi

#### 3a. Migrasi — Tabel consent biometrik

```php
// database/migrations/xxxx_create_employee_biometric_consents_table.php
Schema::create('employee_biometric_consents', function (Blueprint $table) {
    $table->id();
    $table->uuid('employee_uuid')->index();
    $table->uuid('company_id')->index();
    $table->boolean('selfie_consent')->default(false);
    $table->boolean('gps_consent')->default(false);
    $table->timestamp('consent_given_at')->nullable();
    $table->timestamp('consent_withdrawn_at')->nullable();
    $table->string('consent_ip', 45)->nullable();
    $table->timestamps();

    $table->unique(['employee_uuid', 'company_id']);
});
```

#### 3b. Middleware — Cek consent biometrik sebelum check-in

```php
// backend/app/Http/Middleware/RequiresBiometricConsent.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\EmployeeBiometricConsent;

class RequiresBiometricConsent
{
    public function handle(Request $request, Closure $next)
    {
        $employeeUuid = auth()->user()->employee?->uuid;
        $companyId    = $request->header('X-Company-Id');

        $consent = EmployeeBiometricConsent::where('employee_uuid', $employeeUuid)
            ->where('company_id', $companyId)
            ->whereNull('consent_withdrawn_at')
            ->first();

        if (!$consent || !$consent->selfie_consent || !$consent->gps_consent) {
            return response()->json([
                'success' => false,
                'error'   => 'Consent biometrik diperlukan. Harap berikan persetujuan sebelum menggunakan fitur absensi.',
                'code'    => 'BIOMETRIC_CONSENT_REQUIRED',
            ], 403);
        }

        return $next($request);
    }
}
```

#### 3c. Endpoint — Berikan consent biometrik

```php
// Tambah ke route file: backend/routes/api/hcm.php
Route::post('/me/biometric-consent', [HcmAttendanceController::class, 'storeBiometricConsent']);
Route::delete('/me/biometric-consent', [HcmAttendanceController::class, 'withdrawBiometricConsent']);

// Di HcmAttendanceController:
public function storeBiometricConsent(Request $request): JsonResponse
{
    $request->validate([
        'selfie_consent' => ['required', 'boolean'],
        'gps_consent'    => ['required', 'boolean'],
    ]);

    $consent = EmployeeBiometricConsent::updateOrCreate(
        [
            'employee_uuid' => auth()->user()->employee->uuid,
            'company_id'    => $request->header('X-Company-Id'),
        ],
        [
            'selfie_consent'        => $request->boolean('selfie_consent'),
            'gps_consent'           => $request->boolean('gps_consent'),
            'consent_given_at'      => now(),
            'consent_withdrawn_at'  => null,
            'consent_ip'            => $request->ip(),
        ]
    );

    return response()->json(['success' => true, 'data' => $consent]);
}
```

---

### M5 — Notifikasi Karyawan saat Profil Diupdate

#### 5a. Event dan Listener

```php
// backend/app/Events/EmployeeProfileUpdated.php
namespace App\Events;

use App\Models\EmployeeProfile;
use Illuminate\Foundation\Events\Dispatchable;

class EmployeeProfileUpdated
{
    use Dispatchable;

    public function __construct(
        public readonly EmployeeProfile $profile,
        public readonly array $changedFields,
        public readonly string $updatedByUuid,
    ) {}
}

// backend/app/Listeners/SendProfileUpdateNotification.php
namespace App\Listeners;

use App\Events\EmployeeProfileUpdated;
use App\Mail\ProfileUpdatedNotification;
use Illuminate\Support\Facades\Mail;

class SendProfileUpdateNotification
{
    public function handle(EmployeeProfileUpdated $event): void
    {
        $email = $event->profile->user?->email;
        if (!$email) return;

        Mail::to($email)->queue(new ProfileUpdatedNotification(
            $event->profile,
            $event->changedFields,
        ));
    }
}
```

#### 5b. Di controller saat update

```php
// backend/app/Http/Controllers/Api/HcmEmployeeController.php
// Di method update():
$original = $employeeProfile->only(['nik', 'bank_account_no', 'date_of_birth', 'religion', 'marital_status']);
$employeeProfile->update($validatedData);
$changed = array_keys(array_diff_assoc($employeeProfile->fresh()->only(array_keys($original)), $original));

if (!empty($changed)) {
    event(new EmployeeProfileUpdated($employeeProfile, $changed, auth()->user()->uuid));
}
```

---

## CYCLE 3 — SoftDeletes dan Right to Erasure

---

### H1 — Implementasi SoftDeletes + Erasure Workflow

#### 1a. Tambah SoftDeletes ke model-model kritis

```php
// backend/app/Models/User.php
use Illuminate\Database\Eloquent\SoftDeletes;
class User extends Authenticatable implements MustVerifyEmail
{
    use SoftDeletes;
    // ...
}

// backend/app/Models/EmployeeProfile.php
use Illuminate\Database\Eloquent\SoftDeletes;
class EmployeeProfile extends Model
{
    use SoftDeletes;
    // ...
}

// Sama untuk: EmployeeTaxProfile, EmployeeBenefit, AttendanceRecord, AiChatLog
```

#### 1b. Migrasi SoftDeletes

```php
// Buat satu file migrasi per tabel:
// database/migrations/xxxx_add_soft_deletes_to_users.php
Schema::table('users', function (Blueprint $table) {
    $table->softDeletes();
});

// Ulangi untuk: employee_profiles, employee_tax_profiles, employee_benefits,
// attendance_records, ai_chat_logs
```

#### 1c. Migrasi — Tabel erasure requests

```php
// database/migrations/xxxx_create_erasure_requests_table.php
Schema::create('erasure_requests', function (Blueprint $table) {
    $table->id();
    $table->uuid('uuid')->unique()->default(DB::raw('(UUID())'));
    $table->uuid('subject_uuid')->index();      // user yang minta
    $table->uuid('company_id')->index();
    $table->string('status', 20)->default('pending'); // pending, approved, rejected, completed
    $table->text('reason')->nullable();
    $table->uuid('reviewed_by_uuid')->nullable();
    $table->timestamp('reviewed_at')->nullable();
    $table->timestamp('completed_at')->nullable();
    $table->text('admin_notes')->nullable();
    $table->timestamps();
});
```

#### 1d. Endpoint erasure request

```php
// backend/routes/api/hcm.php
Route::post('/me/request-erasure', [HcmDataPrivacyController::class, 'requestErasure']);

// backend/app/Http/Controllers/Api/HcmDataPrivacyController.php
public function requestErasure(Request $request): JsonResponse
{
    $request->validate([
        'reason' => ['required', 'string', 'max:1000'],
    ]);

    $existingPending = ErasureRequest::where('subject_uuid', auth()->user()->uuid)
        ->where('status', 'pending')
        ->exists();

    if ($existingPending) {
        return response()->json([
            'success' => false,
            'error'   => 'Anda sudah memiliki permintaan hapus data yang sedang dalam proses.',
        ], 422);
    }

    $erasureRequest = ErasureRequest::create([
        'uuid'         => (string) Str::uuid(),
        'subject_uuid' => auth()->user()->uuid,
        'company_id'   => $request->header('X-Company-Id'),
        'status'       => 'pending',
        'reason'       => $request->reason,
    ]);

    // Notifikasi admin
    // event(new ErasureRequested($erasureRequest));

    return response()->json([
        'success' => true,
        'data'    => ['uuid' => $erasureRequest->uuid, 'status' => 'pending'],
    ], 201);
}
```

#### 1e. Job pemrosesan erasure

```php
// backend/app/Jobs/ProcessApprovedErasure.php
namespace App\Jobs;

use App\Models\ErasureRequest;
use App\Models\User;
use App\Models\EmployeeProfile;
use App\Models\AttendanceRecord;
use App\Models\AiChatLog;

class ProcessApprovedErasure implements ShouldQueue
{
    public function __construct(private readonly ErasureRequest $erasureRequest) {}

    public function handle(): void
    {
        $subjectUuid = $this->erasureRequest->subject_uuid;

        // Soft delete semua data terkait
        AttendanceRecord::where('employee_uuid', $subjectUuid)->delete();
        AiChatLog::where('user_uuid', $subjectUuid)->delete();
        EmployeeProfile::where('user_uuid', $subjectUuid)->delete();
        User::where('uuid', $subjectUuid)->delete();

        $this->erasureRequest->update([
            'status'       => 'completed',
            'completed_at' => now(),
        ]);

        // Kirim email konfirmasi ke subjek (jika email masih accessible)
        // Mail::to($email)->send(new ErasureCompletedNotification());
    }
}

// backend/app/Console/Commands/PurgeCompletedErasures.php
// Jalankan setiap hari: hapus data yang sudah 30+ hari di soft-deleted state
class PurgeCompletedErasures extends Command
{
    protected $signature = 'pdp:purge-completed-erasures';

    public function handle(): void
    {
        $cutoff = now()->subDays(30);

        User::onlyTrashed()->where('deleted_at', '<', $cutoff)->forceDelete();
        EmployeeProfile::onlyTrashed()->where('deleted_at', '<', $cutoff)->forceDelete();
        AttendanceRecord::onlyTrashed()->where('deleted_at', '<', $cutoff)->forceDelete();
        AiChatLog::onlyTrashed()->where('deleted_at', '<', $cutoff)->forceDelete();

        $this->info("Purge selesai. Cutoff: {$cutoff}");
    }
}
```

---

## CYCLE 4 — Enkripsi Data Sensitif + AI Chat Consent

---

### C5 — Enkripsi Field Sensitif menggunakan Laravel Encrypted Cast

#### 5a. Update model casts

```php
// backend/app/Models/EmployeeProfile.php
protected $casts = [
    // field yang sudah ada...
    'nik'             => 'encrypted',
    'bank_account_no' => 'encrypted',
    'bank_ifsc_code'  => 'encrypted',
    'bank_branch'     => 'encrypted',
    // catatan: field string biasa cukup encrypted cast, tidak perlu tipe DB baru
    // tapi ukuran kolom DB harus cukup besar untuk menampung ciphertext
];

// backend/app/Models/EmployeeTaxProfile.php
protected $casts = [
    'npwp' => 'encrypted',
];

// backend/app/Models/EmployeeBenefit.php
protected $casts = [
    'bpjs_kesehatan_no'       => 'encrypted',
    'bpjs_ketenagakerjaan_no' => 'encrypted',
];
```

#### 5b. Pertimbangan ukuran kolom DB

```php
// PENTING: Setelah enkripsi, data yang disimpan akan lebih panjang dari plaintext.
// Kolom yang sebelumnya VARCHAR(20) mungkin tidak cukup.
// Ubah ke TEXT atau VARCHAR(500+):

Schema::table('employee_profiles', function (Blueprint $table) {
    $table->text('nik')->nullable()->change();
    $table->text('bank_account_no')->nullable()->change();
    $table->text('bank_ifsc_code')->nullable()->change();
    $table->text('bank_branch')->nullable()->change();
});

Schema::table('employee_tax_profiles', function (Blueprint $table) {
    $table->text('npwp')->nullable()->change();
});
```

#### 5c. Command migrasi data existing

```php
// backend/app/Console/Commands/EncryptExistingSensitiveData.php
// JALANKAN SEKALI setelah deploy Cycle 4

class EncryptExistingSensitiveData extends Command
{
    protected $signature = 'pdp:encrypt-existing-data {--dry-run : Hitung saja tanpa mengubah data}';
    protected $description = 'Enkripsi field sensitif yang sudah ada di DB (one-time migration)';

    public function handle(): void
    {
        // Karena cast 'encrypted' sudah aktif, cukup read + write ulang
        // Model akan otomatis encrypt saat $model->save()
        
        $bar = $this->output->createProgressBar(EmployeeProfile::count());
        
        EmployeeProfile::withoutGlobalScopes()->chunk(100, function ($profiles) use ($bar) {
            foreach ($profiles as $profile) {
                if (!$this->option('dry-run')) {
                    // Re-save: Laravel akan encrypt karena cast sudah aktif
                    // Tapi jika data lama sudah encrypted, ini akan double-encrypt!
                    // Solusi: gunakan DB::table() untuk baca raw, lalu update via model
                    $rawNik = DB::table('employee_profiles')
                        ->where('id', $profile->id)
                        ->value('nik');
                    
                    // Jika belum encrypted (tidak dimulai dengan 'eyJ' base64 prefix):
                    if ($rawNik && !str_starts_with($rawNik, 'eyJ')) {
                        DB::table('employee_profiles')
                            ->where('id', $profile->id)
                            ->update(['nik' => encrypt($rawNik)]);
                    }
                }
                $bar->advance();
            }
        });
        
        $bar->finish();
        $this->newLine();
        $this->info('Enkripsi selesai.');
    }
}
```

---

### H3 — AI Chat Disclosure dan Consent

#### 3a. Migrasi — Tabel AI consent

```php
// database/migrations/xxxx_create_employee_ai_consents_table.php
Schema::create('employee_ai_consents', function (Blueprint $table) {
    $table->id();
    $table->uuid('user_uuid')->index();
    $table->uuid('company_id')->index();
    $table->boolean('ai_processing_consent')->default(false);
    $table->timestamp('consent_given_at')->nullable();
    $table->timestamp('consent_withdrawn_at')->nullable();
    $table->string('consent_ip', 45)->nullable();
    $table->timestamps();

    $table->unique(['user_uuid', 'company_id']);
});
```

#### 3b. Endpoint AI consent + check di AiLlmService

```php
// Cek consent sebelum kirim ke AI:
// backend/app/Services/Ai/AiLlmService.php
public function chat(string $userUuid, string $companyId, array $messages): array
{
    // Cek consent
    $hasConsent = EmployeeAiConsent::where('user_uuid', $userUuid)
        ->where('company_id', $companyId)
        ->whereNotNull('consent_given_at')
        ->whereNull('consent_withdrawn_at')
        ->where('ai_processing_consent', true)
        ->exists();

    if (!$hasConsent) {
        throw new AiConsentRequiredException(
            'Consent untuk pemrosesan data oleh AI diperlukan sebelum menggunakan fitur ini.'
        );
    }

    return Http::withToken($this->apiKey)
        ->post("{$this->baseUrl}/chat/completions", [
            'model'    => $this->model,
            'messages' => $messages,
        ])
        ->json();
}
```

---

## CYCLE 5 — Breach Notification + Retensi Data

---

### H2 — Sistem Notifikasi Breach

#### 2a. Migrasi — Tabel incident

```php
// database/migrations/xxxx_create_data_breach_incidents_table.php
Schema::create('data_breach_incidents', function (Blueprint $table) {
    $table->id();
    $table->uuid('uuid')->unique()->default(DB::raw('(UUID())'));
    $table->string('title');
    $table->text('description');
    $table->json('affected_data_types');       // ['nik', 'email', 'bank_account_no']
    $table->json('affected_company_ids')->nullable();
    $table->integer('estimated_affected_subjects')->nullable();
    $table->string('severity', 20);            // 'low', 'medium', 'high', 'critical'
    $table->string('status', 30)->default('detected'); // detected, investigating, notified, resolved
    $table->timestamp('detected_at');
    $table->timestamp('bssn_reported_at')->nullable();
    $table->timestamp('subjects_notified_at')->nullable();
    $table->uuid('created_by_uuid');
    $table->timestamps();
});
```

#### 2b. Job notifikasi breach

```php
// backend/app/Jobs/SendBreachNotificationToSubjects.php
class SendBreachNotificationToSubjects implements ShouldQueue
{
    public function __construct(
        private readonly DataBreachIncident $incident,
        private readonly array $affectedUserUuids,
    ) {}

    public function handle(): void
    {
        $emailTemplate = new DataBreachNotificationMail($this->incident);
        
        User::whereIn('uuid', $this->affectedUserUuids)
            ->chunk(100, function ($users) use ($emailTemplate) {
                foreach ($users as $user) {
                    Mail::to($user->email)->queue($emailTemplate);
                }
            });

        $this->incident->update(['subjects_notified_at' => now()]);
    }
}
```

---

### M3 — Data Retention Jobs

```php
// backend/app/Console/Commands/PurgeExpiredAiChatLogs.php
class PurgeExpiredAiChatLogs extends Command
{
    protected $signature = 'pdp:purge-ai-chat-logs';

    public function handle(): void
    {
        $cutoff = now()->subMonths(12);
        $count  = AiChatLog::where('created_at', '<', $cutoff)->delete();
        $this->info("Dihapus {$count} AI chat log older than {$cutoff}.");
    }
}

// backend/app/Console/Commands/PurgeExpiredAttendanceRecords.php
class PurgeExpiredAttendanceRecords extends Command
{
    protected $signature = 'pdp:purge-attendance-records';

    public function handle(): void
    {
        $cutoff = now()->subYears(5);
        $count  = AttendanceRecord::onlyTrashed()
            ->where('deleted_at', '<', $cutoff)
            ->forceDelete();
        $this->info("Dihapus {$count} attendance records older than 5 years.");
    }
}
```

#### Daftarkan ke Kernel (Laravel 11 menggunakan routes/console.php):

```php
// backend/routes/console.php
use Illuminate\Support\Facades\Schedule;

Schedule::command('pdp:purge-ai-chat-logs')->daily();
Schedule::command('pdp:purge-attendance-records')->monthly();
Schedule::command('pdp:purge-completed-erasures')->daily();
```

---

## CYCLE 6 — Portal Hak Subjek + Withdraw Consent

---

### M4 — Withdraw Consent Endpoint

```php
// backend/routes/api/hcm.php
Route::post('/me/withdraw-consent', [HcmDataPrivacyController::class, 'withdrawConsent']);

// backend/app/Http/Controllers/Api/HcmDataPrivacyController.php
public function withdrawConsent(Request $request): JsonResponse
{
    $request->validate([
        'consent_type' => ['required', 'in:biometric,ai,general'],
        'reason'       => ['nullable', 'string', 'max:500'],
    ]);

    $userUuid  = auth()->user()->uuid;
    $companyId = $request->header('X-Company-Id');

    match ($request->consent_type) {
        'biometric' => EmployeeBiometricConsent::where('employee_uuid', $userUuid)
            ->where('company_id', $companyId)
            ->update(['consent_withdrawn_at' => now()]),
        'ai' => EmployeeAiConsent::where('user_uuid', $userUuid)
            ->where('company_id', $companyId)
            ->update(['consent_withdrawn_at' => now()]),
        'general' => $this->withdrawGeneralConsent($userUuid, $companyId),
    };

    // Kirim konfirmasi email
    Mail::to(auth()->user()->email)->queue(new ConsentWithdrawnConfirmation(auth()->user(), $request->consent_type));

    return response()->json([
        'success' => true,
        'message' => 'Persetujuan Anda telah dicabut. Fitur terkait akan dinonaktifkan.',
    ]);
}
```

---

### L2 — Halaman "Data Saya" untuk Karyawan

```php
// Route baru: GET /hcm/me/data-privacy
// View: backend/resources/views/hcm/employees/data-privacy.blade.php
// Menampilkan:
// 1. Summary data yang disimpan (nama, email, NIK masked, dll)
// 2. Daftar consent yang diberikan + timestamp
// 3. Riwayat perubahan profil (dari audit log)
// 4. Tombol: Unduh Data Saya, Ajukan Hapus Data, Tarik Persetujuan

// API endpoint data portability:
Route::get('/me/data-export', [HcmDataPrivacyController::class, 'exportMyData']);

// Controller method:
public function exportMyData(Request $request): JsonResponse
{
    $user    = auth()->user();
    $profile = $user->employeeProfile;

    $data = [
        'exported_at' => now()->toIso8601String(),
        'user' => [
            'uuid'       => $user->uuid,
            'name'       => $user->name,
            'email'      => $user->email,
            'created_at' => $user->created_at,
        ],
        'profile'            => $profile?->toArray(),
        'attendance_summary' => AttendanceRecord::where('employee_uuid', $profile?->uuid)
            ->select('date', 'check_in_time', 'check_out_time', 'status')
            ->orderByDesc('date')
            ->limit(365)
            ->get(),
        'consents' => [
            'biometric' => EmployeeBiometricConsent::where('employee_uuid', $profile?->uuid)->first(),
            'ai'        => EmployeeAiConsent::where('user_uuid', $user->uuid)->first(),
        ],
    ];

    return response()->json(['success' => true, 'data' => $data]);
}
```

---

## M1 — Audit Log untuk Semua Operasi HCM

```php
// Buat helper trait: backend/app/Traits/LogsHcmActivity.php
trait LogsHcmActivity
{
    protected function logHcmActivity(
        Request $request,
        string $entityType,
        string $entityUuid,
        string $action,
        array $changedFields = []
    ): void {
        DB::table('hcm_activity_logs')->insert([
            'entity_type'    => $entityType,        // 'employee', 'payroll_run', dll
            'entity_uuid'    => $entityUuid,
            'action'         => $action,             // 'create', 'update', 'delete', 'export'
            'performed_by'   => auth()->user()->uuid,
            'company_id'     => $request->header('X-Company-Id'),
            'ip_address'     => $request->ip(),
            'changed_fields' => json_encode($changedFields),
            'created_at'     => now(),
        ]);
    }
}
```

---

## Catatan Penting untuk Semua Siklus

### 1. Urutan Deploy yang Aman

Untuk setiap siklus, urutan deploy yang aman adalah:
```
1. Jalankan migrasi DB terlebih dulu
2. Deploy kode (controller, model, route)
3. Jalankan command one-time (jika ada, misalnya encrypt data)
4. Clear cache: php artisan config:cache && php artisan route:cache
5. Verifikasi dengan test suite
```

### 2. Feature Flag untuk Fitur Disruptif

Fitur yang mengubah UX secara signifikan (consent modal, email verif) sebaiknya menggunakan feature flag:
```php
// config/pdp.php
return [
    'enforce_onboarding_consent'   => env('PDP_ENFORCE_ONBOARDING_CONSENT', false),
    'enforce_biometric_consent'    => env('PDP_ENFORCE_BIOMETRIC_CONSENT', false),
    'enforce_email_verification'   => env('PDP_ENFORCE_EMAIL_VERIFICATION', false),
    'encryption_enabled'           => env('PDP_ENCRYPTION_ENABLED', false),
];
```

### 3. Rollback Plan

Setiap migrasi yang mengubah tipe kolom harus memiliki `down()` method yang tepat:
```php
public function down(): void
{
    Schema::table('employee_profiles', function (Blueprint $table) {
        // Revert text back to varchar — tapi data encrypted tidak bisa di-revert!
        // Dokumentasikan bahwa rollback setelah data dienkripsi butuh manual intervention
    });
}
```

### 4. Testing Strategy

Setiap finding harus memiliki test:
- **Happy path**: consent diberikan → operasi berhasil
- **Rejection path**: consent tidak diberikan → 403/422
- **Audit path**: operasi dicatat di log
- **Erasure path**: data terhapus properly + grace period

### 5. APP_KEY dan Enkripsi

Enkripsi Laravel bergantung pada `APP_KEY`. Jika `APP_KEY` berubah, semua data encrypted tidak bisa dibaca. Pastikan:
- `APP_KEY` di-backup di password manager
- Tidak ada rotasi `APP_KEY` tanpa prosedur re-enkripsi data
