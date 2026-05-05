<?php

namespace App\Jobs;

use App\Mail\DataBreachNotificationMail;
use App\Models\DataBreachIncident;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendBreachNotificationToSubjects implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public readonly int $incidentId) {}

    public function handle(): void
    {
        $incident = DataBreachIncident::query()->find($this->incidentId);
        if (! $incident) {
            return;
        }

        $uuids = collect((array) ($incident->affected_user_uuids ?? []))
            ->filter(fn ($value): bool => is_string($value) && trim($value) !== '')
            ->unique()
            ->values();

        if ($uuids->isEmpty()) {
            $incident->forceFill([
                'notifications_sent_at' => now(),
                'status' => DataBreachIncident::STATUS_NOTIFIED,
            ])->save();

            return;
        }

        $users = User::query()
            ->whereIn('uuid', $uuids->all())
            ->whereNotNull('email')
            ->get();

        foreach ($users as $user) {
            Mail::to($user->email)->queue(new DataBreachNotificationMail($user, $incident));
        }

        $incident->forceFill([
            'notifications_sent_at' => now(),
            'status' => DataBreachIncident::STATUS_NOTIFIED,
        ])->save();
    }
}
