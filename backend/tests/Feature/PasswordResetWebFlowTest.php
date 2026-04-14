<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetWebFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_reset_password_from_web_flow(): void
    {
        $user = User::query()->create([
            'name' => 'Reset User',
            'email' => 'reset.user@example.com',
            'password' => Hash::make('OldPass123!'),
        ]);

        $token = Password::broker()->createToken($user);

        $response = $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewPass123!',
            'password_confirmation' => 'NewPass123!',
        ]);

        $response->assertRedirect(route('login'));

        $user->refresh();
        $this->assertTrue(Hash::check('NewPass123!', $user->password));
    }
}
