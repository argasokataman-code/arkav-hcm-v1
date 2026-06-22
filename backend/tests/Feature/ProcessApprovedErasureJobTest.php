<?php

namespace Tests\Feature;

use App\Jobs\ProcessApprovedErasure;
use App\Models\AiChatLog;
use App\Models\AttendanceRecord;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\EmployeeBiometricConsent;
use App\Models\EmployeeProfile;
use App\Models\ErasureRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProcessApprovedErasureJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // WORKAROUND: ai_chat_logs table is missing deleted_at column due to
        // migration ordering bug (000005_add_soft_deletes runs before 000100_create_ai_chat_logs).
        // Add column at test runtime so the AiChatLog SoftDeletes trait works.
        if (! \Schema::hasColumn('ai_chat_logs', 'deleted_at')) {
            \Schema::table('ai_chat_logs', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    /**
     * @return array{user: User, company: Company, profile: EmployeeProfile, erasureRequest: ErasureRequest}
     */
    private function setupErasureContext(): array
    {
        $company = Company::factory()->create();
        $user = User::factory()->create();

        CompanyUser::firstOrCreate(
            ['user_id' => $user->id, 'company_id' => $company->id],
            ['role' => 'employee', 'status' => 'active']
        );

        $profile = EmployeeProfile::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'company_uuid' => $company->uuid,
            'user_id' => $user->id,
            'user_uuid' => $user->uuid,
            'nik' => '3201234567890001',
            'phone' => '081234567890',
            'address' => 'Jl. Merdeka No. 1',
            'bank_name' => 'BCA',
            'bank_account_no' => '9876543210',
            'bank_ifsc_code' => 'CENA0001234',
            'bank_branch' => 'Jakarta Pusat',
            'base_salary' => 10000000,
        ]);

        // Create biometric consent
        EmployeeBiometricConsent::query()->create([
            'employee_uuid' => $profile->uuid,
            'company_id' => $company->id,
            'selfie_consent' => true,
            'gps_consent' => true,
            'consent_given_at' => now()->subMonth(),
        ]);

        // NOTE: ai_chat_logs table needs deleted_at column (see setUp workaround)

        // Create AI chat log
        AiChatLog::query()->create([
            'user_uuid' => (string) $user->uuid,
            'user_legacy_id' => $user->id,
            'company_id' => $company->id,
            'session_id' => (string) Str::uuid(),
            'intent' => 'payroll_question',
            'allowed' => true,
        ]);

        // Create attendance record
        AttendanceRecord::query()->create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'user_id' => $user->id,
            'work_date' => now()->subDays(10)->toDateString(),
            'status' => 'present',
            'check_in_at' => now()->subDays(10),
        ]);

        // Create erasure request
        $erasureRequest = ErasureRequest::query()->create([
            'uuid' => (string) Str::uuid(),
            'subject_uuid' => (string) $user->uuid,
            'company_id' => $company->id,
            'status' => 'approved',
            'reason' => 'I want my data deleted.',
        ]);

        return [
            'user' => $user,
            'company' => $company,
            'profile' => $profile,
            'erasureRequest' => $erasureRequest,
        ];
    }

    public function test_process_approved_erasure_anonymizes_user_and_cleans_data(): void
    {
        $ctx = $this->setupErasureContext();

        $job = new ProcessApprovedErasure($ctx['erasureRequest']->id);
        $job->handle();

        // 1. Erasure request marked completed
        $ctx['erasureRequest']->refresh();
        $this->assertEquals('completed', $ctx['erasureRequest']->status);
        $this->assertNotNull($ctx['erasureRequest']->completed_at);

        // 2. User anonymized (not deleted)
        $ctx['user']->refresh();
        $this->assertEquals('Anonymized User', $ctx['user']->name);
        $this->assertStringContainsString('anonymized_', $ctx['user']->email);
        $this->assertStringContainsString('@erased.local', $ctx['user']->email);

        // 3. Employee profile PII fields nullified
        $ctx['profile']->refresh();
        $this->assertNull($ctx['profile']->nik);
        $this->assertNull($ctx['profile']->phone);
        $this->assertNull($ctx['profile']->address);
        $this->assertNull($ctx['profile']->bank_name);
        $this->assertNull($ctx['profile']->bank_account_no);
        $this->assertNull($ctx['profile']->bank_ifsc_code);
        $this->assertNull($ctx['profile']->bank_branch);

        // 4. Employee profile soft-deleted
        $this->assertSoftDeleted('employee_profiles', ['uuid' => (string) $ctx['profile']->uuid]);

        // 5. Biometric consent withdrawn
        $this->assertDatabaseHas('employee_biometric_consents', [
            'employee_uuid' => (string) $ctx['profile']->uuid,
            'company_id' => $ctx['company']->id,
        ]);
        $consent = EmployeeBiometricConsent::query()
            ->where('employee_uuid', (string) $ctx['profile']->uuid)
            ->first();
        $this->assertNotNull($consent->consent_withdrawn_at);

        // 6. AI chat logs soft-deleted
        $this->assertSoftDeleted('ai_chat_logs', [
            'user_legacy_id' => $ctx['user']->id,
            'company_id' => $ctx['company']->id,
        ]);

        // 7. Attendance records soft-deleted
        $this->assertSoftDeleted('attendance_records', [
            'user_id' => $ctx['user']->id,
            'company_id' => $ctx['company']->id,
        ]);
    }

    public function test_process_erasure_skips_when_status_not_approved(): void
    {
        $ctx = $this->setupErasureContext();

        // Change status to pending (not approved)
        $ctx['erasureRequest']->update(['status' => 'pending']);

        $job = new ProcessApprovedErasure($ctx['erasureRequest']->id);
        $job->handle();

        // Nothing should change
        $ctx['erasureRequest']->refresh();
        $this->assertEquals('pending', $ctx['erasureRequest']->status);
        $this->assertNull($ctx['erasureRequest']->completed_at);

        // User not anonymized
        $ctx['user']->refresh();
        $this->assertNotEquals('Anonymized User', $ctx['user']->name);
    }

    public function test_process_erasure_handles_missing_user_gracefully(): void
    {
        $ctx = $this->setupErasureContext();

        // Delete the user (simulate already deleted)
        $userUuid = (string) $ctx['user']->uuid;
        $userId = $ctx['user']->id;
        $ctx['user']->forceDelete();

        // Update erasure request to reference non-existent user
        $ctx['erasureRequest']->update(['subject_uuid' => $userUuid]);

        $job = new ProcessApprovedErasure($ctx['erasureRequest']->id);
        $job->handle();

        // Should still mark as completed
        $ctx['erasureRequest']->refresh();
        $this->assertEquals('completed', $ctx['erasureRequest']->status);
        $this->assertNotNull($ctx['erasureRequest']->completed_at);
    }

    public function test_process_erasure_does_not_affect_other_company_attendance(): void
    {
        $ctx = $this->setupErasureContext();

        // Create attendance record in another company for a different user
        $otherUser = User::factory()->create();
        $otherCompany = Company::factory()->create();
        $otherAttendance = AttendanceRecord::query()->create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $otherCompany->id,
            'user_id' => $otherUser->id,
            'work_date' => now()->subDays(5)->toDateString(),
            'status' => 'present',
            'check_in_at' => now()->subDays(5),
        ]);

        $job = new ProcessApprovedErasure($ctx['erasureRequest']->id);
        $job->handle();

        // Other user's attendance should NOT be affected
        $this->assertDatabaseHas('attendance_records', ['id' => $otherAttendance->id]);
    }
}
