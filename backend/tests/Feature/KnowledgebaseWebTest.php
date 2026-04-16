<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KnowledgebaseWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_knowledgebase(): void
    {
        $this->get('/knowledgebase')->assertRedirect(url('lock-screen'));
    }

    public function test_authenticated_user_can_open_index_and_article(): void
    {
        $user = User::query()->create([
            'name' => 'KB Reader',
            'email' => 'kb.reader@example.com',
            'password' => bcrypt('StrongPass1'),
        ]);

        $this->actingAs($user)
            ->get('/knowledgebase')
            ->assertOk()
            ->assertSee('Knowledge Base', false)
            ->assertSee('Memulai dan akun', false);

        $this->actingAs($user)
            ->get('/knowledgebase/article/login-perusahaan-dan-token')
            ->assertOk()
            ->assertSee('cookie', false);

        $this->actingAs($user)
            ->get('/knowledgebase/article/tidak-ada')
            ->assertNotFound();
    }

    public function test_legacy_knowledgebase_details_redirects_when_article_known(): void
    {
        $user = User::query()->create([
            'name' => 'KB Reader 2',
            'email' => 'kb.reader2@example.com',
            'password' => bcrypt('StrongPass1'),
        ]);

        $this->actingAs($user)
            ->get('/knowledgebase-details?article=slip-gaji-mandiri')
            ->assertRedirect(route('knowledgebase.article', ['slug' => 'slip-gaji-mandiri']));
    }

    public function test_legacy_knowledgebase_view_redirects_when_category_known(): void
    {
        $user = User::query()->create([
            'name' => 'KB Reader 3',
            'email' => 'kb.reader3@example.com',
            'password' => bcrypt('StrongPass1'),
        ]);

        $this->actingAs($user)
            ->get('/knowledgebase-view?category=payroll')
            ->assertRedirect(route('knowledgebase.category', ['slug' => 'payroll']));
    }
}
