<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Locks the contract that:
 *  - hcm.admin_email is the *global super-admin* signal.
 *  - hcm.secondary_admin_email is intentionally tenant-admin only and
 *    MUST NOT be promoted to global super-admin (would regress sidebar
 *    super-admin menus + SaaS dashboard guards).
 * See SidebarAssetMenuVisibilityTest::test_secondary_hcm_admin_… for
 * the downstream UI contract that depends on this.
 */
class UserGlobalAdminEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_primary_admin_email_is_recognized_as_global_hcm_admin(): void
    {
        config()->set('hcm.admin_email', 'qa.login@example.com');
        config()->set('hcm.secondary_admin_email', 'qa.hcm@example.com');

        $user = User::query()->create([
            'name' => 'Primary Admin',
            'email' => 'qa.login@example.com',
            'password' => bcrypt('StrongPass1'),
        ]);

        $this->assertTrue($user->isGlobalHcmAdmin());
    }

    public function test_secondary_admin_email_is_not_promoted_to_global_hcm_admin(): void
    {
        config()->set('hcm.admin_email', 'qa.login@example.com');
        config()->set('hcm.secondary_admin_email', 'qa.hcm@example.com');

        $user = User::query()->create([
            'name' => 'Secondary Admin',
            'email' => 'qa.hcm@example.com',
            'password' => bcrypt('StrongPass1'),
        ]);

        $this->assertFalse(
            $user->isGlobalHcmAdmin(),
            'Secondary admin email is a tenant-admin seed, not a global super-admin.'
        );
    }

    public function test_unrelated_email_is_not_global_hcm_admin(): void
    {
        config()->set('hcm.admin_email', 'qa.login@example.com');
        config()->set('hcm.secondary_admin_email', 'qa.hcm@example.com');

        $user = User::query()->create([
            'name' => 'Random User',
            'email' => 'random.user@example.com',
            'password' => bcrypt('StrongPass1'),
        ]);

        $this->assertFalse($user->isGlobalHcmAdmin());
    }
}
