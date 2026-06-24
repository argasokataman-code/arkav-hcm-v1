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
            ->assertSee('Memulai dan Akun', false)
            ->assertDontSee('Super Admin (Operator Platform)', false)
            ->assertDontSee('Panduan lengkap: admin dari login sampai operasional harian', false);

        $this->actingAs($user)
            ->get('/knowledgebase/article/cara-login')
            ->assertOk()
            ->assertSee('Cara masuk ke Arcav HCM', false);

        $this->actingAs($user)
            ->get('/knowledgebase/article/menu-super-admin')
            ->assertNotFound();

        $this->actingAs($user)
            ->get('/knowledgebase/article/panduan-admin-harian')
            ->assertNotFound();

        $this->actingAs($user)
            ->get('/knowledgebase/article/tidak-ada')
            ->assertNotFound();
    }

    public function test_global_super_admin_can_view_global_knowledgebase_content(): void
    {
        $user = new User();
        $user->forceFill([
            'name' => 'Global KB Admin',
            'email' => 'global.kb.admin@example.com',
            'password' => bcrypt('StrongPass1'),
            'is_super_admin' => true,
        ]);
        $user->save();

        $this->actingAs($user)
            ->get('/knowledgebase')
            ->assertOk()
            ->assertSee('Super Admin (Operator Platform)', false);

        $this->actingAs($user)
            ->get('/knowledgebase/article/menu-super-admin')
            ->assertOk()
            ->assertSee('operator platform', false);

        $this->actingAs($user)
            ->get('/knowledgebase/article/panduan-admin-harian')
            ->assertOk()
            ->assertSee('Login dan pastikan perusahaan yang aktif sudah benar', false);
    }

    public function test_legacy_knowledgebase_details_redirects_when_article_known(): void
    {
        $user = User::query()->create([
            'name' => 'KB Reader 2',
            'email' => 'kb.reader2@example.com',
            'password' => bcrypt('StrongPass1'),
        ]);

        $this->actingAs($user)
            ->get('/knowledgebase-details?article=slip-gaji-karyawan')
            ->assertRedirect(route('knowledgebase.article', ['slug' => 'slip-gaji-karyawan']));
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

    public function test_search_with_no_results_shows_empty_state(): void
    {
        $user = User::query()->create([
            'name' => 'KB Reader 4',
            'email' => 'kb.reader4@example.com',
            'password' => bcrypt('StrongPass1'),
        ]);

        $this->actingAs($user)
            ->get('/knowledgebase?q=nonexistentsearchterm')
            ->assertOk()
            ->assertSee('Knowledge Base', false)
            ->assertSee('nonexistentsearchterm', false)
            ->assertDontSee('Memulai dan akun', false); // Should not show categories when no results
    }

    public function test_category_with_no_articles_shows_empty_state(): void
    {
        $user = User::query()->create([
            'name' => 'KB Reader 5',
            'email' => 'kb.reader5@example.com',
            'password' => bcrypt('StrongPass1'),
        ]);

        $this->actingAs($user)
            ->get('/knowledgebase/category/memulai')
            ->assertOk()
            ->assertSee('Memulai dan Akun', false);
    }

    public function test_invalid_category_returns_404(): void
    {
        $user = User::query()->create([
            'name' => 'KB Reader 6',
            'email' => 'kb.reader6@example.com',
            'password' => bcrypt('StrongPass1'),
        ]);

        $this->actingAs($user)
            ->get('/knowledgebase/category/nonexistent-category')
            ->assertNotFound();
    }
}
