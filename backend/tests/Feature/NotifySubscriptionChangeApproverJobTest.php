<?php

namespace Tests\Feature;

use App\Jobs\NotifySubscriptionChangeApproverJob;
use App\Models\Company;
use App\Models\HcmSubscriptionChangeRequest;
use App\Models\User;
use App\Notifications\SubscriptionChangeApprovalNeededNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotifySubscriptionChangeApproverJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_notify_job_sends_only_to_primary_super_admin_code_one(): void
    {
        config(['hcm.admin_email' => 'qa.login@example.com']);

        $company = Company::query()->create([
            'code' => 'NTF' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6)),
            'name' => 'Notify Scope Co',
            'legal_name' => 'Notify Scope Co Ltd',
            'status' => 'active',
            'timezone' => 'UTC',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        $requester = User::query()->create([
            'name' => 'Tenant Requester',
            'email' => 'tenant.requester@example.com',
            'password' => bcrypt('StrongPass1'),
        ]);

        $primaryAdmin = User::query()->create([
            'name' => 'Primary Admin',
            'email' => 'qa.login@example.com',
            'password' => bcrypt('StrongPass1'),
            'is_super_admin' => true,
        ]);

        $secondaryAdmin = User::query()->create([
            'name' => 'Secondary Admin',
            'email' => 'qa.hcm@example.com',
            'password' => bcrypt('StrongPass1'),
            'is_super_admin' => true,
        ]);

        $record = HcmSubscriptionChangeRequest::query()->create([
            'id' => (string) Str::uuid(),
            'company_uuid' => $company->uuid,
            'user_uuid' => $requester->uuid,
            'current_subscription_uuid' => null,
            'from_package_uuid' => null,
            'to_package_uuid' => null,
            'action' => HcmSubscriptionChangeRequest::ACTION_UPGRADE,
            'status' => HcmSubscriptionChangeRequest::STATUS_PENDING,
            'preview' => [
                'action' => 'upgrade',
                'price_delta' => 120000,
            ],
            'notes' => 'Need upgrade access',
            'effective_at' => now(),
        ]);

        Notification::fake();

        (new NotifySubscriptionChangeApproverJob($record->id))->handle();

        Notification::assertSentTo($primaryAdmin, SubscriptionChangeApprovalNeededNotification::class, function ($notification) use ($primaryAdmin): bool {
            $data = $notification->toDatabase($primaryAdmin);

            return ($data['event'] ?? null) === 'subscription_change_approval_needed'
                && ($data['eventKey'] ?? null) === 'subscription.change_approval_needed'
                && ($data['severity'] ?? null) === 'critical';
        });
        Notification::assertNotSentTo($secondaryAdmin, SubscriptionChangeApprovalNeededNotification::class);
    }
}
