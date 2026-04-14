<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserHcmAdminGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_qa_login_email_is_treated_as_hcm_admin_even_with_case_and_spaces(): void
    {
        $user = User::factory()->create([
            'email' => ' QA.LOGIN@EXAMPLE.COM ',
        ]);

        $this->assertTrue($user->isHcmAdmin());
    }

    public function test_non_admin_email_is_not_treated_as_hcm_admin(): void
    {
        $user = User::factory()->create([
            'email' => 'employee@example.com',
        ]);

        $this->assertFalse($user->isHcmAdmin());
    }
}
