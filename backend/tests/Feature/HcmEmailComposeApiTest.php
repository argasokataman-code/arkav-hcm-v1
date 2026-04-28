<?php

namespace Tests\Feature;

use App\Mail\AdminComposeMailable;
use App\Models\NotificationDelivery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class HcmEmailComposeApiTest extends TestCase
{
    use RefreshDatabase;

    private function nonAdminToken(): string
    {
        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Compose Non Admin',
            'email' => 'compose-non-admin@example.com',
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => 'compose-non-admin@example.com',
            'password' => 'StrongPass1',
        ])->assertOk();

        return (string) $login->json('data.accessToken');
    }

    private function globalAdminToken(): string
    {
        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Compose Admin',
            'email' => 'compose-admin@example.com',
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $user = User::query()->where('email', 'compose-admin@example.com')->firstOrFail();
        $user->forceFill(['is_super_admin' => true])->save();

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => 'compose-admin@example.com',
            'password' => 'StrongPass1',
        ])->assertOk();

        return (string) $login->json('data.accessToken');
    }

    public function test_global_admin_can_send_runtime_compose_email_via_api(): void
    {
        Mail::fake();
        $token = $this->globalAdminToken();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->postJson('/v1/hcm/email-settings/compose', [
            'to' => 'argasokataman@gmail.com',
            'subject' => 'API Compose Runtime Test',
            'message' => 'Payload from compose API test.',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.to', 'argasokataman@gmail.com')
            ->assertJsonPath('data.subject', 'API Compose Runtime Test');

        $this->assertDatabaseHas('notification_deliveries', [
            'event_key' => 'email.compose.sent',
            'channel' => 'mail',
            'status' => 'sent',
            'recipient' => 'argasokataman@gmail.com',
        ]);

        $delivery = NotificationDelivery::query()
            ->where('event_key', 'email.compose.sent')
            ->latest('id')
            ->first();
        $this->assertNotNull($delivery);
        $this->assertNotNull($delivery->notification_uuid);
        $this->assertSame('API Compose Runtime Test', $delivery->metadata['subject'] ?? null);
        $this->assertTrue((bool) ($delivery->metadata['transportAccepted'] ?? false));
        $this->assertNotSame('', (string) ($delivery->metadata['mailDefaultDriver'] ?? ''));

        Mail::assertSent(AdminComposeMailable::class, function (AdminComposeMailable $mail): bool {
            return $mail->hasTo('argasokataman@gmail.com')
                && $mail->subjectLine === 'API Compose Runtime Test'
                && $mail->messageBody === 'Payload from compose API test.';
        });
    }

    public function test_compose_api_validates_required_fields(): void
    {
        Mail::fake();
        $token = $this->globalAdminToken();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->postJson('/v1/hcm/email-settings/compose', [
            'to' => 'not-an-email',
            'subject' => '',
            'message' => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');

        Mail::assertNothingSent();
    }

    public function test_non_global_admin_cannot_access_email_settings_endpoints(): void
    {
        $token = $this->nonAdminToken();

        $headers = [
            'Authorization' => 'Bearer '.$token,
        ];

        $this->withHeaders($headers)
            ->getJson('/v1/hcm/email-settings')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'ADMIN_REQUIRED');

        $this->withHeaders($headers)
            ->putJson('/v1/hcm/email-settings', [
                'provider' => 'smtp',
                'smtp' => ['host' => 'smtp.example.com', 'username' => 'tester'],
            ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'ADMIN_REQUIRED');

        $this->withHeaders($headers)
            ->getJson('/v1/hcm/email-settings/mailtrap-status')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'ADMIN_REQUIRED');

        $this->withHeaders($headers)
            ->postJson('/v1/hcm/email-settings/test-connection', [
                'provider' => 'smtp',
                'smtp' => [
                    'host' => 'smtp.example.com',
                    'username' => 'tester',
                    'password' => 'secret',
                ],
            ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'ADMIN_REQUIRED');

        $this->withHeaders($headers)
            ->postJson('/v1/hcm/email-settings/compose', [
                'to' => 'someone@example.com',
                'subject' => 'forbidden',
                'message' => 'forbidden',
            ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'ADMIN_REQUIRED');
    }
}