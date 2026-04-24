<?php

namespace App\Notifications;

use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Support\Hcm\NotificationPayloadFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * M6 — Sent to the employee (who previously held the asset) when the asset is
 * returned to the pool, so the user has audit evidence of the return action.
 */
class AssetReturnedNotification extends Notification
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
        return NotificationPayloadFactory::make('asset.returned', [
            'companyUuid' => (string) ($this->asset->company_uuid ?? $this->assignment->company_uuid ?? ''),
            'entityType' => 'asset',
            'entityUuid' => (string) ($this->asset->uuid ?? ''),
            'title' => 'Asset returned',
            'message' => (string) ($this->asset->asset_code ?? ''),
            'occurredAt' => $this->assignment->updated_at,
        ], [
            'event' => 'asset.returned',
            'assetId' => (int) $this->asset->id,
            'assetCode' => (string) ($this->asset->asset_code ?? ''),
            'assetName' => (string) ($this->asset->name ?? ''),
            'assignmentId' => (int) $this->assignment->id,
            'assignedDate' => optional($this->assignment->assigned_date)->toDateString(),
            'returnedDate' => optional($this->assignment->returned_date)->toDateString(),
            'conditionAtReturn' => (string) ($this->assignment->condition_at_return ?? ''),
            'notes' => (string) ($this->assignment->notes ?? ''),
        ]);
    }
}
