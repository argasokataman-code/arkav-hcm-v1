<?php

namespace Tests\Feature;

use App\Mail\AdminComposeMailable;
use App\Models\NotificationDelivery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailComposeWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_page_can_send_runtime_mail_from_web_form(): void
    {
        Mail::fake();

        $admin = User::factory()->create([
            'email' => 'qa.login@example.com',
        ]);

        $csrfToken = 'email-compose-token';

        $response = $this->actingAs($admin)
            ->withSession(['_token' => $csrfToken])
            ->post('/email', [
                '_token' => $csrfToken,
                'Label' => 'Angela Thomas',
                'to' => 'argasokataman@gmail.com',
                'subject' => 'UI Send Test',
                'message' => 'Halo dari compose runtime.',
            ]);

        $response
            ->assertRedirect(route('email', ['Label' => 'Angela Thomas']))
            ->assertSessionHas('status', 'Email berhasil dikirim ke argasokataman@gmail.com.');

        Mail::assertSent(AdminComposeMailable::class, function (AdminComposeMailable $mail): bool {
            return $mail->hasTo('argasokataman@gmail.com')
                && $mail->subjectLine === 'UI Send Test'
                && $mail->messageBody === 'Halo dari compose runtime.';
        });
    }

    public function test_email_page_validates_required_compose_fields(): void
    {
        Mail::fake();

        $admin = User::factory()->create([
            'email' => 'qa.login@example.com',
        ]);

        $csrfToken = 'email-compose-invalid-token';

        $response = $this->actingAs($admin)
            ->withSession(['_token' => $csrfToken])
            ->from(route('email'))
            ->post('/email', [
                '_token' => $csrfToken,
                'to' => 'invalid-recipient',
                'subject' => '',
                'message' => '',
            ]);

        $response
            ->assertRedirect(route('email'))
            ->assertSessionHasErrors(['to', 'subject', 'message']);

        Mail::assertNothingSent();
    }

    public function test_email_page_renders_sent_items_from_runtime_logs(): void
    {
        $admin = User::factory()->create([
            'email' => 'qa.runtime@example.com',
        ]);

        NotificationDelivery::query()->create([
            'event_key' => 'email.compose.sent',
            'channel' => 'mail',
            'status' => 'sent',
            'recipient' => 'recipient.one@example.com',
            'attempt_count' => 1,
            'metadata' => [
                'subject' => 'Runtime Sent Subject',
                'messagePreview' => 'Runtime sent preview body.',
                'senderUserId' => $admin->id,
                'senderEmail' => $admin->email,
            ],
            'sent_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('email'));

        $response
            ->assertOk()
            ->assertSeeText('Runtime Sent Subject')
            ->assertSeeText('recipient.one@example.com')
            ->assertSeeText('Runtime sent preview body.');
    }
}