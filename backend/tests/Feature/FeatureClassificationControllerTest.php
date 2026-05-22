<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Http\Middleware\AuthenticateApiToken;

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

        // Index
        $list = $this->getJson('/v1/saas/feature-classifications');
        $list->assertStatus(200)->assertJson(['success' => true]);
        $this->assertCount(1, $list->json('data'));

        // Update
        $update = $this->putJson("/v1/saas/feature-classifications/{$id}", ['tier' => 'mvp']);
        $update->assertStatus(200)->assertJson(['success' => true]);

        // Delete
        $del = $this->deleteJson("/v1/saas/feature-classifications/{$id}");
        $del->assertStatus(200)->assertJson(['success' => true]);
    }
}
