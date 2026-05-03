<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SecuritySettingsChangePasswordTest extends TestCase
{
    use RefreshDatabase;

    private function registerAndLogin(string $email, string $password): array
    {
        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Test User',
            'email' => $email,
            'password' => $password,
            'confirmPassword' => $password,
        ])->assertCreated();

        $user = User::query()->where('email', $email)->firstOrFail();

        $token = $this->postJson('/v1/identity/auth/login', [
            'email' => $email,
            'password' => $password,
        ])->assertOk()->json('data.accessToken');

        $this->assertIsString($token);

        return [$user, $token];
    }

    public function test_authenticated_user_can_change_password(): void
    {
        [$user, $token] = $this->registerAndLogin('change.pw@example.com', 'OldPass123!');

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/v1/identity/auth/change-password', [
                'currentPassword' => 'OldPass123!',
                'newPassword' => 'NewPass456@',
                'confirmPassword' => 'NewPass456@',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $user->refresh();
        $this->assertTrue(Hash::check('NewPass456@', $user->password));
    }

    public function test_change_password_fails_with_wrong_current_password(): void
    {
        [, $token] = $this->registerAndLogin('wrong.pw@example.com', 'OldPass123!');

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/v1/identity/auth/change-password', [
                'currentPassword' => 'WrongPass999!',
                'newPassword' => 'NewPass456@',
                'confirmPassword' => 'NewPass456@',
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'AUTH_INVALID_CREDENTIALS');
    }

    public function test_change_password_fails_when_confirm_does_not_match(): void
    {
        [, $token] = $this->registerAndLogin('mismatch.pw@example.com', 'OldPass123!');

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/v1/identity/auth/change-password', [
                'currentPassword' => 'OldPass123!',
                'newPassword' => 'NewPass456@',
                'confirmPassword' => 'DifferentPass789!',
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_change_password_fails_with_weak_new_password(): void
    {
        [, $token] = $this->registerAndLogin('weak.pw@example.com', 'OldPass123!');

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/v1/identity/auth/change-password', [
                'currentPassword' => 'OldPass123!',
                'newPassword' => 'weak',
                'confirmPassword' => 'weak',
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_change_password_requires_authentication(): void
    {
        $this->postJson('/v1/identity/auth/change-password', [
            'currentPassword' => 'OldPass123!',
            'newPassword' => 'NewPass456@',
            'confirmPassword' => 'NewPass456@',
        ])->assertStatus(401);
    }
}
