<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use Tests\TestCase;

#[IgnoreDeprecations]
class CalendarEventApiTest extends TestCase
{
    use RefreshDatabase;

    private function auth(): array
    {
        $result = $this->createHcmAdminWithCompany();

        return [
            'token' => $result['token'],
            'company_id' => $result['company_id'],
        ];
    }

    private function headers(string $token, int $companyId): array
    {
        return [
            'Authorization' => 'Bearer ' . $token,
            'X-Company-Id' => (string) $companyId,
        ];
    }

    public function test_user_can_create_list_update_and_delete_calendar_event(): void
    {
        ['token' => $token, 'company_id' => $companyId] = $this->auth();
        $headers = $this->headers($token, $companyId);

        $created = $this->postJson('/v1/hcm/calendar/events', [
            'title' => 'Team Sync',
            'location' => 'Meeting Room A',
            'description' => 'Sprint planning',
            'startAt' => '2026-05-03 09:00:00',
            'endAt' => '2026-05-03 10:00:00',
            'allDay' => false,
        ], $headers)
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'Team Sync')
            ->json('data');

        $this->getJson('/v1/hcm/calendar/events', $headers)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data');

        $this->putJson('/v1/hcm/calendar/events/' . $created['id'], [
            'title' => 'Team Sync Updated',
            'location' => 'Meeting Room B',
        ], $headers)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'Team Sync Updated')
            ->assertJsonPath('data.location', 'Meeting Room B');

        $this->deleteJson('/v1/hcm/calendar/events/' . $created['id'], [], $headers)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->getJson('/v1/hcm/calendar/events', $headers)
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_user_cannot_access_other_user_calendar_event(): void
    {
        ['token' => $token1, 'company_id' => $companyId1] = $this->auth();
        $headers1 = $this->headers($token1, $companyId1);

        $created = $this->postJson('/v1/hcm/calendar/events', [
            'title' => 'Private Event',
            'startAt' => '2026-05-04 08:00:00',
        ], $headers1)
            ->assertStatus(201)
            ->json('data');

        $result2 = $this->createHcmAdminWithCompany(['email' => 'calendar-user2-' . time() . '@example.com']);
        $headers2 = $this->headers($result2['token'], $result2['company_id']);

        $this->putJson('/v1/hcm/calendar/events/' . $created['id'], ['title' => 'Hijacked'], $headers2)
            ->assertNotFound();

        $this->deleteJson('/v1/hcm/calendar/events/' . $created['id'], [], $headers2)
            ->assertNotFound();
    }
}
