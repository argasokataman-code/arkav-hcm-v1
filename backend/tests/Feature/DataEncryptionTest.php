<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\EmployeeProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DataEncryptionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that sensitive fields in EmployeeProfile are encrypted.
     * UU PDP C5: NIK, bank account fields must be encrypted at-rest.
     */
    public function test_employee_profile_sensitive_fields_are_encrypted(): void
    {
        // Create test user and company
        $user = User::factory()->create();
        $company = Company::factory()->create();

        // Create an employee with sensitive data
        $employee = EmployeeProfile::create([
            'uuid' => Str::uuid(),
            'company_id' => $company->id,
            'company_uuid' => $company->uuid,
            'user_id' => $user->id,
            'user_uuid' => $user->uuid,
            'nik' => '3201234567890001',
            'bank_account_no' => '9876543210',
            'bank_ifsc_code' => 'BKID0001234',
            'bank_branch' => 'Jakarta Pusat',
        ]);

        // Fetch the raw data from DB to verify it's encrypted
        $raw = \DB::table('employee_profiles')
            ->where('uuid', $employee->uuid)
            ->first();

        // Raw DB values should be ciphertext (prefixed with "eyJpdiI6I..." for Laravel encrypted format)
        $this->assertStringContainsString('eyJ', $raw->nik ?? '', 'NIK should be encrypted (Laravel format)');
        $this->assertStringContainsString('eyJ', $raw->bank_account_no ?? '', 'Bank account should be encrypted');
        $this->assertStringContainsString('eyJ', $raw->bank_ifsc_code ?? '', 'IFSC code should be encrypted');
        $this->assertStringContainsString('eyJ', $raw->bank_branch ?? '', 'Branch should be encrypted');
    }

    /**
     * Test that encrypted fields are auto-decrypted when accessed via model.
     */
    public function test_employee_profile_encrypted_fields_decrypted_on_read(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();

        $plaintext = [
            'uuid' => Str::uuid(),
            'company_id' => $company->id,
            'company_uuid' => $company->uuid,
            'user_id' => $user->id,
            'user_uuid' => $user->uuid,
            'nik' => '3201234567890001',
        ];

        EmployeeProfile::create($plaintext);

        // Reload from DB and verify decryption
        $reloaded = EmployeeProfile::where('uuid', $plaintext['uuid'])->first();
        $this->assertEquals($reloaded->nik, $plaintext['nik']);
        $this->assertNotNull($reloaded->nik, 'NIK should be decrypted and not null');
    }

    /**
     * Test that query searches still work on encrypted fields.
     * Note: This verifies that encrypted fields can still be queried via model scope.
     */
    public function test_encrypted_fields_can_be_queried_via_model(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();

        $employee = EmployeeProfile::create([
            'uuid' => Str::uuid(),
            'company_id' => $company->id,
            'company_uuid' => $company->uuid,
            'user_id' => $user->id,
            'user_uuid' => $user->uuid,
            'nik' => '3201234567890001',
        ]);

        // Query via model (uses decryption)
        $found = EmployeeProfile::query()
            ->where('uuid', $employee->uuid)
            ->first();

        $this->assertEquals($found->nik, '3201234567890001');
    }
}
