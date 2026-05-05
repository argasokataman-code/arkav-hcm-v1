<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\EmployeeAiConsent;
use App\Models\EmployeeProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AiChatConsentTest extends TestCase
{
    use RefreshDatabase;

    private function createEmployeeForUser(User $user): EmployeeProfile
    {
        $company = Company::factory()->create();

        return EmployeeProfile::create([
            'uuid' => Str::uuid(),
            'company_id' => $company->id,
            'company_uuid' => $company->uuid,
            'user_id' => $user->id,
            'user_uuid' => $user->uuid,
        ]);
    }

    /**
     * Test that a consent record can be created for an employee.
     * UU PDP H3: Employee AI consent model.
     */
    public function test_employee_ai_consent_can_be_created(): void
    {
        $user = User::factory()->create();
        $employee = $this->createEmployeeForUser($user);

        $consent = EmployeeAiConsent::create([
            'employee_uuid' => $employee->uuid,
            'user_uuid' => $user->uuid,
            'consent_given_at' => now(),
            'consent_ip_address' => '127.0.0.1',
            'consent_text' => 'Test consent text',
        ]);

        $this->assertDatabaseHas('employee_ai_consents', [
            'employee_uuid' => $employee->uuid,
            'user_uuid' => $user->uuid,
            'id' => $consent->id,
        ]);
    }

    /**
     * Test that isActive() returns true when consent is not withdrawn.
     */
    public function test_active_consent_is_recognized(): void
    {
        $user = User::factory()->create();
        $employee = $this->createEmployeeForUser($user);

        $consent = EmployeeAiConsent::create([
            'employee_uuid' => $employee->uuid,
            'user_uuid' => $user->uuid,
            'consent_given_at' => now(),
        ]);

        $this->assertTrue($consent->isActive());
        $this->assertNull($consent->withdrawn_at);
    }

    /**
     * Test that isActive() returns false when consent is withdrawn.
     */
    public function test_withdrawn_consent_is_not_active(): void
    {
        $user = User::factory()->create();
        $employee = $this->createEmployeeForUser($user);

        $consent = EmployeeAiConsent::create([
            'employee_uuid' => $employee->uuid,
            'user_uuid' => $user->uuid,
            'consent_given_at' => now(),
            'withdrawn_at' => now(),
        ]);

        $this->assertFalse($consent->isActive());
        $this->assertNotNull($consent->withdrawn_at);
    }

    /**
     * Test that getActiveForEmployee returns the latest active consent.
     */
    public function test_get_active_for_employee_returns_latest(): void
    {
        $user = User::factory()->create();
        $employee = $this->createEmployeeForUser($user);

        // Create old withdrawn consent
        EmployeeAiConsent::create([
            'employee_uuid' => $employee->uuid,
            'user_uuid' => $user->uuid,
            'consent_given_at' => now()->subDay(),
            'withdrawn_at' => now()->subHours(2),
        ]);

        // Create new active consent
        $newConsent = EmployeeAiConsent::create([
            'employee_uuid' => $employee->uuid,
            'user_uuid' => $user->uuid,
            'consent_given_at' => now(),
        ]);

        $active = EmployeeAiConsent::getActiveForEmployee($employee->uuid);

        $this->assertNotNull($active);
        $this->assertEquals($active->id, $newConsent->id);
    }

    /**
     * Test that AiLlmService can check user consent.
     * UU PDP H3: Service guard method.
     */
    public function test_ai_llm_service_checks_user_consent(): void
    {
        $user = User::factory()->create();
        $employee = $this->createEmployeeForUser($user);
        $service = new \App\Services\Ai\AiLlmService();

        // Before consent
        $this->assertFalse($service->checkUserHasAiConsent($user->uuid));

        // Grant consent
        EmployeeAiConsent::create([
            'employee_uuid' => $employee->uuid,
            'user_uuid' => $user->uuid,
            'consent_given_at' => now(),
            'consent_ip_address' => '127.0.0.1',
        ]);

        // After consent
        $this->assertTrue($service->checkUserHasAiConsent($user->uuid));
    }

    /**
     * Test that withdrawn consent is not recognized by service.
     */
    public function test_ai_llm_service_rejects_withdrawn_consent(): void
    {
        $user = User::factory()->create();
        $employee = $this->createEmployeeForUser($user);
        $service = new \App\Services\Ai\AiLlmService();

        // Create and withdraw consent
        EmployeeAiConsent::create([
            'employee_uuid' => $employee->uuid,
            'user_uuid' => $user->uuid,
            'consent_given_at' => now(),
            'withdrawn_at' => now(),
        ]);

        // Service should reject withdrawn consent
        $this->assertFalse($service->checkUserHasAiConsent($user->uuid));
    }

    /**
     * Test that service returns false for user without employee profile.
     */
    public function test_ai_llm_service_rejects_user_without_profile(): void
    {
        $user = User::factory()->create();
        // No employee profile
        $service = new \App\Services\Ai\AiLlmService();

        $this->assertFalse($service->checkUserHasAiConsent($user->uuid));
    }
}
