<?php

namespace Tests\Feature;

use App\Http\Middleware\AuthenticateApiToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeatureClassificationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_crud_feature_classifications()
    {
        $this->withoutMiddleware(AuthenticateApiToken::class);

        $admin = User::factory()->create(['is_super_admin' => true]);

        $this->actingAs($admin);

        // Create
        $resp = $this->postJson('/v1/saas/feature-classifications', [
            'feature_code' => 'test_feature',
            'tier' => 'addon',
        ]);

        $resp->assertStatus(201)->assertJson(['success' => true]);

        $id = $resp->json('data.id');

        // Index — baseline has backfill rows; verify our new entry is present
        $list = $this->getJson('/v1/saas/feature-classifications');
        $list->assertStatus(200)->assertJson(['success' => true]);
        $data = $list->json('data');
        $this->assertTrue(count($data) >= 1, 'Expected at least one classification row');
        $this->assertTrue(
            collect($data)->contains(fn ($row) => $row['id'] === $id),
            'Newly created classification not found in index'
        );

        // Update
        $update = $this->putJson("/v1/saas/feature-classifications/{$id}", ['tier' => 'mvp']);
        $update->assertStatus(200)->assertJson(['success' => true]);

        // Delete
        $del = $this->deleteJson("/v1/saas/feature-classifications/{$id}");
        $del->assertStatus(200)->assertJson(['success' => true]);
    }
}
