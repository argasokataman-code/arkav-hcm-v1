<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DatabaseNotification extends \Illuminate\Notifications\DatabaseNotification
{
    protected static function booted(): void
    {
        static::saving(function (self $notification): void {
            $isUserNotification = (string) $notification->notifiable_type === User::class;
            if (! $isUserNotification) {
                return;
            }

            if (! $notification->user_uuid && $notification->notifiable_id) {
                $resolvedUserUuid = (string) (User::query()
                    ->where('id', $notification->notifiable_id)
                    ->value('uuid') ?? '');

                $notification->user_uuid = $resolvedUserUuid !== '' ? $resolvedUserUuid : null;
            }

            if (! $notification->company_uuid && $notification->user_uuid) {
                $resolvedCompanyUuid = (string) (CompanyUser::query()
                    ->where('user_uuid', $notification->user_uuid)
                    ->where('status', 'active')
                    ->orderByDesc('id')
                    ->value('company_uuid') ?? '');

                $notification->company_uuid = $resolvedCompanyUuid !== '' ? $resolvedCompanyUuid : null;
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_uuid', 'uuid');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_uuid', 'uuid');
    }
}
