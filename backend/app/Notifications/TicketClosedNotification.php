<?php

namespace App\Notifications;

use App\Models\Ticket;
use App\Support\Hcm\NotificationPayloadFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TicketClosedNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly Ticket $ticket) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return NotificationPayloadFactory::make('ticket.closed', [
            'companyUuid' => (string) ($this->ticket->company_uuid ?? ''),
            'entityType' => 'ticket',
            'entityUuid' => (string) ($this->ticket->uuid ?? ''),
            'title' => 'Ticket closed',
            'message' => (string) ($this->ticket->title ?? ''),
            'occurredAt' => now(),
        ], [
            'event' => 'ticket.closed',
            'ticketId' => (int) $this->ticket->id,
            'ticketNumber' => (string) ($this->ticket->ticket_number ?? ''),
            'title' => (string) ($this->ticket->title ?? ''),
            'status' => 'closed',
        ]);
    }
}
