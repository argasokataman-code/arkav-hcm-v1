<?php

namespace Tests\Feature;

use App\Events\EmployeeProfileUpdated;
use App\Listeners\SendProfileUpdateNotification;
use App\Mail\ProfileUpdatedNotification;
use App\Models\Company;
use App\Models\EmployeeProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProfileUpdateNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function createEmployeeWithEmail(string $email = 'notify.employee@example.com'): array
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['email' => $email]);

        $profile = EmployeeProfile::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'company_uuid' => $company->uuid,
            'user_id' => $user->id,
            'user_uuid' => $user->uuid,
            'nik' => '3201234567890001',
            'phone' => '081234567890',
        ]);

        return [
            'user' => $user,
            'profile' => $profile,
            'company' => $company,
        ];
    }

    public function test_notification_sent_when_sensitive_field_changed(): void
    {
        Mail::fake();

        $ctx = $this->createEmployeeWithEmail();

        $event = new EmployeeProfileUpdated(
            profile: $ctx['profile'],
            changedFields: ['nik', 'phone'],
        );

        $listener = new SendProfileUpdateNotification;
        $listener->handle($event);

        Mail::assertSent(ProfileUpdatedNotification::class, function ($mail) use ($ctx): bool {
            return $mail->hasTo($ctx['user']->email)
                && in_array('nik', $mail->changedFields)
                && in_array('phone', $mail->changedFields);
        });
    }

    public function test_notification_sent_for_bank_fields(): void
    {
        Mail::fake();

        $ctx = $this->createEmployeeWithEmail();

        $event = new EmployeeProfileUpdated(
            profile: $ctx['profile'],
            changedFields: ['bank_account_no', 'bank_name'],
        );

        $listener = new SendProfileUpdateNotification;
        $listener->handle($event);

        Mail::assertSent(ProfileUpdatedNotification::class, function ($mail): bool {
            return in_array('bank_account_no', $mail->changedFields)
                && in_array('bank_name', $mail->changedFields);
        });
    }

    public function test_notification_sent_for_salary_fields(): void
    {
        Mail::fake();

        $ctx = $this->createEmployeeWithEmail();

        $event = new EmployeeProfileUpdated(
            profile: $ctx['profile'],
            changedFields: ['base_salary', 'fixed_allowance'],
        );

        $listener = new SendProfileUpdateNotification;
        $listener->handle($event);

        Mail::assertSent(ProfileUpdatedNotification::class, function ($mail): bool {
            return in_array('base_salary', $mail->changedFields)
                && in_array('fixed_allowance', $mail->changedFields);
        });
    }

    public function test_no_notification_for_non_sensitive_field(): void
    {
        Mail::fake();

        $ctx = $this->createEmployeeWithEmail();

        // 'bio' is not in the NOTIFIABLE_FIELDS list — but let's check
        // Actually let me check: 'bio' IS in the NOTIFIABLE_FIELDS based on the listener
        // Let me use a truly non-sensitive field
        $event = new EmployeeProfileUpdated(
            profile: $ctx['profile'],
            changedFields: ['updated_at'],
        );

        $listener = new SendProfileUpdateNotification;
        $listener->handle($event);

        Mail::assertNotSent(ProfileUpdatedNotification::class);
    }

    public function test_no_notification_when_changed_fields_empty(): void
    {
        Mail::fake();

        $ctx = $this->createEmployeeWithEmail();

        $event = new EmployeeProfileUpdated(
            profile: $ctx['profile'],
            changedFields: [],
        );

        $listener = new SendProfileUpdateNotification;
        $listener->handle($event);

        Mail::assertNotSent(ProfileUpdatedNotification::class);
    }

    public function test_no_notification_when_user_has_empty_email(): void
    {
        Mail::fake();

        $company = Company::factory()->create();
        $user = User::factory()->create(['email' => 'temp@example.com']);

        $profile = EmployeeProfile::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'company_uuid' => $company->uuid,
            'user_id' => $user->id,
            'user_uuid' => $user->uuid,
        ]);

        // Clear email after creation (simulates user without email)
        $user->forceFill(['email' => ''])->save();
        $user->refresh();

        $event = new EmployeeProfileUpdated(
            profile: $profile,
            changedFields: ['nik'],
        );

        $listener = new SendProfileUpdateNotification;
        $listener->handle($event);

        Mail::assertNotSent(ProfileUpdatedNotification::class);
    }

    public function test_notification_includes_only_sensitive_subset_from_mixed_changes(): void
    {
        Mail::fake();

        $ctx = $this->createEmployeeWithEmail();

        // Mix of sensitive and non-sensitive fields
        $event = new EmployeeProfileUpdated(
            profile: $ctx['profile'],
            changedFields: ['updated_at', 'nik', 'created_at', 'phone'],
        );

        $listener = new SendProfileUpdateNotification;
        $listener->handle($event);

        Mail::assertSent(ProfileUpdatedNotification::class, function ($mail): bool {
            // Should only contain nik and phone, NOT updated_at/created_at
            return in_array('nik', $mail->changedFields)
                && in_array('phone', $mail->changedFields)
                && ! in_array('updated_at', $mail->changedFields)
                && ! in_array('created_at', $mail->changedFields);
        });
    }
}
