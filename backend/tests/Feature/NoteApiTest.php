<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use Tests\TestCase;

#[IgnoreDeprecations]
class NoteApiTest extends TestCase
{
    use RefreshDatabase;

    private function auth(): array
    {
        $result = $this->createHcmAdminWithCompany();
        return [
            'token'      => $result['token'],
            'company_id' => $result['company_id'],
        ];
    }

    private function headers(string $token, int $companyId): array
    {
        return [
            'Authorization' => "Bearer {$token}",
            'X-Company-Id'  => $companyId,
        ];
    }

    public function test_user_can_create_and_list_notes(): void
    {
        ['token' => $token, 'company_id' => $companyId] = $this->auth();
        $headers = $this->headers($token, $companyId);

        // Create a note
        $response = $this->postJson('/v1/hcm/notes', [
            'title'    => 'Test Note',
            'content'  => 'Some content',
            'tag'      => 'work',
            'priority' => 'high',
        ], $headers);

        $response->assertStatus(201)
                 ->assertJsonPath('success', true)
                 ->assertJsonPath('data.title', 'Test Note')
                 ->assertJsonPath('data.tag', 'work')
                 ->assertJsonPath('data.priority', 'high');

        // List notes
        $list = $this->getJson('/v1/hcm/notes', $headers);
        $list->assertOk()
             ->assertJsonPath('success', true)
             ->assertJsonCount(1, 'data');
    }

    public function test_user_can_update_note(): void
    {
        ['token' => $token, 'company_id' => $companyId] = $this->auth();
        $headers = $this->headers($token, $companyId);

        $created = $this->postJson('/v1/hcm/notes', [
            'title' => 'Original',
        ], $headers)->assertStatus(201)->json('data');

        $updated = $this->putJson("/v1/hcm/notes/{$created['id']}", [
            'title'        => 'Updated Title',
            'is_important' => true,
        ], $headers);

        $updated->assertOk()
                ->assertJsonPath('data.title', 'Updated Title')
                ->assertJsonPath('data.isImportant', true);
    }

    public function test_user_can_delete_note(): void
    {
        ['token' => $token, 'company_id' => $companyId] = $this->auth();
        $headers = $this->headers($token, $companyId);

        $created = $this->postJson('/v1/hcm/notes', [
            'title' => 'To Delete',
        ], $headers)->assertStatus(201)->json('data');

        $this->deleteJson("/v1/hcm/notes/{$created['id']}", [], $headers)
             ->assertOk()
             ->assertJsonPath('success', true);

        // Confirm gone
        $list = $this->getJson('/v1/hcm/notes', $headers)->json('data');
        $this->assertEmpty($list);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/v1/hcm/notes')
             ->assertUnauthorized();
    }

    public function test_user_cannot_access_another_users_note(): void
    {
        ['token' => $token1, 'company_id' => $companyId1] = $this->auth();
        $headers1 = $this->headers($token1, $companyId1);

        $created = $this->postJson('/v1/hcm/notes', [
            'title' => 'Private Note',
        ], $headers1)->assertStatus(201)->json('data');

        // Second user in a different company
        $result2  = $this->createHcmAdminWithCompany(['email' => 'note-user2-' . time() . '@example.com']);
        $headers2 = $this->headers($result2['token'], $result2['company_id']);

        $this->putJson("/v1/hcm/notes/{$created['id']}", ['title' => 'Hijack'], $headers2)
             ->assertNotFound();

        $this->deleteJson("/v1/hcm/notes/{$created['id']}", [], $headers2)
             ->assertNotFound();
    }
}
