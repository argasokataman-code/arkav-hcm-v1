<?php

namespace App\Notifications;

use App\Support\Hcm\NotificationPayloadFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordResetLinkNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly string $resetUrl)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Reset Password')
            ->greeting('Hello!')
            ->line('We received a request to reset your account password.')
            ->action('Reset Password', $this->resetUrl)
            ->line('If the button above does not work, copy and open this URL:')
            ->line($this->resetUrl)
            ->line('This link will expire in 10 minutes.')
            ->line('If you did not request a password reset, no further action is required.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return NotificationPayloadFactory::make('auth.password_reset_link_requested', [
            'severity' => 'critical',
            'entityType' => 'user',
            'entityUuid' => (string) ($notifiable->uuid ?? ''),
            'title' => 'Password reset requested',
            'message' => 'A password reset link was generated for this account.',
        ], [
            'event' => 'auth.password_reset_link_requested',
            'email' => (string) ($notifiable->email ?? ''),
        ]);
    }
}
