<?php

namespace App\Notifications;

use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Support\Hcm\NotificationPayloadFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * M6 — Sent to the assigned employee when an asset is handed to them.
 *
 * Uses the `database` channel so it lands in the user's in-app notification
 * inbox. Intentionally does not default to `mail` to avoid surprising users
 * that have not opted in; operators can extend via `via()` override/config.
 */
class AssetAssignedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Asset $asset,
        public readonly AssetAssignment $assignment,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return NotificationPayloadFactory::make('asset.assigned', [
            'companyUuid' => (string) ($this->asset->company_uuid ?? $this->assignment->company_uuid ?? ''),
            'entityType' => 'asset',
            'entityUuid' => (string) ($this->asset->uuid ?? ''),
            'title' => 'Asset assigned',
            'message' => (string) ($this->asset->asset_code ?? ''),
            'occurredAt' => $this->assignment->created_at,
        ], [
            'event' => 'asset.assigned',
            'assetId' => (int) $this->asset->id,
            'assetCode' => (string) ($this->asset->asset_code ?? ''),
            'assetName' => (string) ($this->asset->name ?? ''),
            'assignmentId' => (int) $this->assignment->id,
            'assignedDate' => optional($this->assignment->assigned_date)->toDateString(),
            'conditionAtAssign' => (string) ($this->assignment->condition_at_assign ?? ''),
            'notes' => (string) ($this->assignment->notes ?? ''),
        ]);
    }
}
