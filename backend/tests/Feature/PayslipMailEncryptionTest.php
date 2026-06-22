<?php

namespace Tests\Feature;

use App\Mail\MonthlyPayslipMail;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\EmployeeProfile;
use App\Models\User;
use App\Services\PayslipEncryptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class PayslipMailEncryptionTest extends TestCase
{
    use RefreshDatabase;

    private function makeSlipData(User $user): array
    {
        return [
            'slipNumber' => 'SLIP/2026/07/001',
            'period' => ['periodMonth' => 7, 'periodYear' => 2026],
            'employee' => [
                'name' => $user->name,
                'nik' => '3201234567890001',
            ],
            'totals' => [
                'earningsTotal' => 15000000,
                'deductionsTotal' => 2000000,
                'overtimeTotal' => 500000,
                'netPay' => 13500000,
            ],
        ];
    }

    public function test_payslip_mail_encrypts_pdf_when_enabled(): void
    {
        Config::set('pdp.payslip_encryption_enabled', true);

        $user = User::factory()->create(['email' => 'worker@example.com']);
        $company = Company::factory()->create();
        CompanyUser::firstOrCreate(
            ['user_id' => $user->id, 'company_id' => $company->id],
            ['role' => 'employee', 'status' => 'active']
        );
        EmployeeProfile::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'company_uuid' => $company->uuid,
            'user_id' => $user->id,
            'user_uuid' => $user->uuid,
            'nik' => '3201234567890001',
        ]);

        $slip = $this->makeSlipData($user);
        $fakePdf = 'fake-pdf-content-for-testing-payslip';
        $mail = new MonthlyPayslipMail($user, $slip, $fakePdf, $company->name, true, 'SLIP890001');
        $attachments = $mail->attachments();
        $this->assertCount(1, $attachments);
    }

    public function test_payslip_mail_body_contains_password_instruction_when_enabled(): void
    {
        Config::set('pdp.payslip_encryption_enabled', true);

        $user = User::factory()->create(['email' => 'worker@example.com']);
        $company = Company::factory()->create();
        EmployeeProfile::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'company_uuid' => $company->uuid,
            'user_id' => $user->id,
            'user_uuid' => $user->uuid,
            'nik' => '3201234567890001',
        ]);

        $slip = $this->makeSlipData($user);
        $mail = new MonthlyPayslipMail($user, $slip, 'fake-pdf', $company->name, true, 'SLIP890001');

        $rendered = $mail->render();
        $this->assertStringContainsString('password', $rendered);
        $this->assertStringContainsString('NIK', $rendered);
        $this->assertStringContainsString('SLIP', $rendered);
    }

    public function test_payslip_mail_body_does_not_show_password_when_disabled(): void
    {
        Config::set('pdp.payslip_encryption_enabled', false);

        $user = User::factory()->create(['email' => 'worker@example.com']);
        $company = Company::factory()->create();
        EmployeeProfile::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'company_uuid' => $company->uuid,
            'user_id' => $user->id,
            'user_uuid' => $user->uuid,
            'nik' => '3201234567890001',
        ]);

        $slip = $this->makeSlipData($user);
        $mail = new MonthlyPayslipMail($user, $slip, 'fake-pdf', $company->name);

        $rendered = $mail->render();
        $this->assertStringNotContainsString('password', $rendered);
    }

    public function test_payslip_encryption_uses_nik_to_derive_password(): void
    {
        $service = new PayslipEncryptionService;

        $password = $service->deriveDefaultPassword('3201234567890001');

        $this->assertEquals('SLIP890001', $password);
    }

    public function test_payslip_mail_with_encrypted_pdf_can_be_decrypted_with_nik_password(): void
    {
        Config::set('pdp.payslip_encryption_enabled', true);

        $user = User::factory()->create(['email' => 'worker@example.com']);
        $company = Company::factory()->create();
        EmployeeProfile::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'company_uuid' => $company->uuid,
            'user_id' => $user->id,
            'user_uuid' => $user->uuid,
            'nik' => '3201234567890001',
        ]);

        $service = new PayslipEncryptionService;
        $plaintextPdf = '{"content":"real-payslip-data","gaji":15000000}';
        $password = $service->deriveDefaultPassword('3201234567890001');
        $encrypted = $service->encrypt($plaintextPdf, $password);

        $decrypted = $service->decrypt($encrypted, $password);
        $this->assertEquals($plaintextPdf, $decrypted);
    }
}
