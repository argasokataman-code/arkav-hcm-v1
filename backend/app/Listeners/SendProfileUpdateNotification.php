<?php

namespace App\Listeners;

use App\Events\EmployeeProfileUpdated;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

class SendProfileUpdateNotification implements ShouldQueue
{
    /**
     * Fields that trigger a notification when changed (PDP-relevant fields only).
     */
    private const NOTIFIABLE_FIELDS = [
        'nik',
        'date_of_birth',
        'place_of_birth',
        'bank_name',
        'bank_account_no',
        'bank_ifsc_code',
        'bank_branch',
        'phone',
        'address',
        'emergency_contacts',
        'base_salary',
        'fixed_allowance',
        'gender',
        'marital_status',
        'religion',
        'nationality',
    ];

    public function handle(EmployeeProfileUpdated $event): void
    {
        $sensitiveChanged = array_intersect($event->changedFields, self::NOTIFIABLE_FIELDS);
        if (empty($sensitiveChanged)) {
            return;
        }

        /** @var User|null $user */
        $user = User::query()->find($event->profile->user_id);
        if (! $user || ! $user->email) {
            return;
        }

        try {
            Mail::to($user->email)->send(new \App\Mail\ProfileUpdatedNotification(
                $user,
                $sensitiveChanged,
            ));
        } catch (\Throwable) {
            // Non-fatal: notification failure should not break the update flow.
        }
    }
}
