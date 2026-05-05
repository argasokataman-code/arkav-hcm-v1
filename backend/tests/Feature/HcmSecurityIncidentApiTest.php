<?php

namespace Tests\Feature;

use App\Jobs\SendBreachNotificationToSubjects;
use App\Models\CompanyUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class HcmSecurityIncidentApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{token: string, companyId: int}
     */
    private function adminContext(): array
    {
        $email = 'incident.admin@example.com';
        $password = 'StrongPass1';

        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Incident Admin',
            'email' => $email,
            'password' => $password,
            'confirmPassword' => $password,
        ])->assertStatus(201);

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => $email,
            'password' => $password,
        ])->assertOk();

        $user = User::query()->where('email', $email)->firstOrFail();
        $companyId = (int) CompanyUser::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->value('company_id');

        CompanyUser::query()
            ->where('user_id', $user->id)
            ->where('company_id', $companyId)
            ->update(['role' => 'admin', 'status' => 'active']);

        return [
            'token' => (string) $login->json('data.accessToken'),
            'companyId' => $companyId,
        ];
    }

    public function test_admin_can_manage_security_incident_lifecycle(): void
    {
        Queue::fake();

        $ctx = $this->adminContext();

        $create = $this->withHeaders([
            'Authorization' => 'Bearer '.$ctx['token'],
            'X-Company-Id' => (string) $ctx['companyId'],
        ])->postJson('/v1/admin/security-incidents', [
            'title' => 'Unauthorized payroll export',
            'description' => 'Potential exposure of payroll report outside approved recipients.',
            'affected_data_types' => ['salary', 'bank_account_no'],
            'affected_subjects_count' => 2,
            'affected_user_uuids' => [(string) Str::uuid()],
            'detected_at' => now()->toDateTimeString(),
        ]);

        $create->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'detected');

        $incidentUuid = (string) $create->json('data.uuid');
        $this->assertNotSame('', $incidentUuid);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$ctx['token'],
            'X-Company-Id' => (string) $ctx['companyId'],
        ])->getJson('/v1/admin/security-incidents')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.data.0.uuid', $incidentUuid);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$ctx['token'],
            'X-Company-Id' => (string) $ctx['companyId'],
        ])->getJson('/v1/admin/security-incidents/'.$incidentUuid)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.uuid', $incidentUuid);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$ctx['token'],
            'X-Company-Id' => (string) $ctx['companyId'],
        ])->postJson('/v1/admin/security-incidents/'.$incidentUuid.'/notify-subjects')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.queued', true)
            ->assertJsonPath('data.incident_uuid', $incidentUuid);

        Queue::assertPushed(SendBreachNotificationToSubjects::class, function (SendBreachNotificationToSubjects $job) use ($create): bool {
            return $job->incidentId === (int) $create->json('data.id');
        });

        $this->withHeaders([
            'Authorization' => 'Bearer '.$ctx['token'],
            'X-Company-Id' => (string) $ctx['companyId'],
        ])->postJson('/v1/admin/security-incidents/'.$incidentUuid.'/resolve')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'resolved');
    }

    public function test_show_returns_not_found_for_unknown_incident_uuid(): void
    {
        $ctx = $this->adminContext();

        $this->withHeaders([
            'Authorization' => 'Bearer '.$ctx['token'],
            'X-Company-Id' => (string) $ctx['companyId'],
        ])->getJson('/v1/admin/security-incidents/'.(string) Str::uuid())
            ->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'NOT_FOUND');
    }
}
