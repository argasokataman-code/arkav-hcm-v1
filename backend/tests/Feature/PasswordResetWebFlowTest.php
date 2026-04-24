<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\PasswordResetLinkNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetWebFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_request_password_reset_link_from_web_flow(): void
    {
        Notification::fake();

        $csrfToken = 'password-reset-request-token';

        $user = User::query()->create([
            'name' => 'Reset Mail User',
            'email' => 'reset.mail.user@example.com',
            'password' => Hash::make('OldPass123!'),
        ]);

        $response = $this->withSession(['_token' => $csrfToken])
            ->from('/forgot-password')
            ->post('/forgot-password', [
                '_token' => $csrfToken,
                'email' => $user->email,
            ]);

        $response->assertRedirect('/forgot-password');
        $response->assertSessionHas('status');

        Notification::assertSentTo($user, PasswordResetLinkNotification::class, function (PasswordResetLinkNotification $notification) use ($user): bool {
            $payload = $notification->toArray($user);

            return ($payload['eventKey'] ?? null) === 'auth.password_reset_link_requested'
                && ($payload['severity'] ?? null) === 'critical'
                && ($payload['entityUuid'] ?? null) === $user->uuid;
        });
    }

    public function test_user_can_reset_password_from_web_flow(): void
    {
        $csrfToken = 'password-reset-submit-token';

        $user = User::query()->create([
            'name' => 'Reset User',
            'email' => 'reset.user@example.com',
            'password' => Hash::make('OldPass123!'),
        ]);

        $token = Password::broker()->createToken($user);

        $response = $this->withSession(['_token' => $csrfToken])
            ->post('/reset-password', [
                '_token' => $csrfToken,
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
