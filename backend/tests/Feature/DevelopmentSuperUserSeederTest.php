<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DevelopmentSuperUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DevelopmentSuperUserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_development_super_user_account_is_seeded(): void
    {
        config()->set('hcm.admin_email', 'qa.login@example.com');
        config()->set('hcm.admin_password', 'StrongPass1');

        $this->seed(DevelopmentSuperUserSeeder::class);

        $user = User::query()->where('email', 'qa.login@example.com')->first();

        $this->assertNotNull($user);
        $this->assertSame('Super User 1', $user->name);
        $this->assertTrue(Hash::check('StrongPass1', (string) $user->password));
        $this->assertTrue($user->isHcmAdmin());
    }
}
