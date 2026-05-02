<?php

namespace Tests\Feature;

use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\Ai\AiIntentResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HcmAiChatLeaveHistoryIntentTest extends TestCase
{
    use RefreshDatabase;

    private function headers(string $token, int $companyId): array
    {
        return $this->withCompanyContext([
            'Authorization' => 'Bearer '.$token,
        ], $companyId);
    }

    public function test_typo_question_is_classified_as_leave_history_other(): void
    {
        $auth = $this->createHcmAdminWithCompany([
            'email' => 'ai-leave-typo-'.time().'@example.com',
        ]);

        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [[
                    'message' => ['content' => 'Berikut daftar pengajuan cuti periode kemarin.'],
                ]],
            ], 200),
        ]);

        $response = $this->postJson('/v1/hcm/ai/chat', [
            'message' => 'siapa karywan yg pernah ajukan cuti di peridoe kmaren?',
        ], $this->headers($auth['token'], $auth['company_id']));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.intent', 'leave.history.other')
            ->assertJsonPath('data.allowed', true);
    }

    public function test_leave_history_other_contains_previous_period_applicants_with_names(): void
    {
        $adminEmail = 'ai-leave-period-'.time().'@example.com';
        $auth = $this->createHcmAdminWithCompany([
            'email' => $adminEmail,
        ]);

        $admin = User::query()->where('email', $adminEmail)->firstOrFail();

        $employeeA = User::factory()->create([
            'email' => 'leave-employee-a-'.time().'@example.com',
            'name' => 'Employee Alpha',
        ]);

        $employeeB = User::factory()->create([
            'email' => 'leave-employee-b-'.time().'@example.com',
            'name' => 'Employee Beta',
        ]);

        $previousMonthStart = now()->subMonthNoOverflow()->startOfMonth();
        $previousMonthDateA = $previousMonthStart->copy()->addDays(3);
        $previousMonthDateB = $previousMonthStart->copy()->addDays(10);

        LeaveRequest::query()->create([
            'company_id' => $auth['company_id'],
            'user_id' => $employeeA->id,
            'leave_type' => 'annual',
            'date_from' => $previousMonthDateA->toDateString(),
            'date_to' => $previousMonthDateA->copy()->addDay()->toDateString(),
            'days' => 2,
            'status' => 'approved',
            'notes' => 'Approved annual leave',
        ]);

        LeaveRequest::query()->create([
            'company_id' => $auth['company_id'],
            'user_id' => $employeeB->id,
            'leave_type' => 'sick',
            'date_from' => $previousMonthDateB->toDateString(),
            'date_to' => $previousMonthDateB->toDateString(),
            'days' => 1,
            'status' => 'pending',
            'notes' => 'Pending sick leave',
        ]);

        // Current-month data should not be mixed into "periode kemarin" applicants.
        LeaveRequest::query()->create([
            'company_id' => $auth['company_id'],
            'user_id' => $employeeA->id,
            'leave_type' => 'annual',
            'date_from' => now()->startOfMonth()->toDateString(),
            'date_to' => now()->startOfMonth()->copy()->addDay()->toDateString(),
            'days' => 2,
            'status' => 'approved',
            'notes' => 'Current month leave',
        ]);

        $result = app(AiIntentResolver::class)->resolve('leave.history.other', $admin, (int) $auth['company_id'], '');

        $this->assertNotNull($result);
        $this->assertSame('Company Leave History', $result['source']['label'] ?? null);

        $applicantNames = collect($result['data']['previous_period']['applicants'] ?? [])->pluck('user_name')->all();

        $this->assertContains('Employee Alpha', $applicantNames);
        $this->assertContains('Employee Beta', $applicantNames);
        $this->assertSame(2, (int) ($result['data']['previous_period']['total_requests'] ?? 0));
    }
}
