<?php

namespace App\Notifications;

use App\Models\Ticket;
use App\Support\Hcm\NotificationPayloadFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TicketAssignedNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly Ticket $ticket) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return NotificationPayloadFactory::make('ticket.assigned', [
            'companyUuid' => (string) ($this->ticket->company_uuid ?? ''),
            'entityType' => 'ticket',
            'entityUuid' => (string) ($this->ticket->uuid ?? ''),
            'title' => 'Ticket assigned',
            'message' => (string) ($this->ticket->title ?? ''),
            'occurredAt' => now(),
        ], [
            'event' => 'ticket.assigned',
            'ticketId' => (int) $this->ticket->id,
            'ticketNumber' => (string) ($this->ticket->ticket_number ?? ''),
            'title' => (string) ($this->ticket->title ?? ''),
            'assigneeId' => (int) ($this->ticket->assignee_id ?? 0),
            'status' => (string) ($this->ticket->status ?? ''),
        ]);
    }
}
