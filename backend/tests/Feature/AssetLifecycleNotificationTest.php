<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Company;
use App\Models\EmployeeProfile;
use App\Models\User;
use App\Notifications\AssetAssignedNotification;
use App\Notifications\AssetReturnedNotification;
use App\Services\AssetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use Tests\TestCase;

/**
 * M6 — Verify that AssetService dispatches in-app notifications when an asset
 * is assigned to or returned from an employee. The assignee receives the event
 * so the HCM inbox has an auditable trail of custody changes.
 */
#[IgnoreDeprecations]
class AssetLifecycleNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function setupAssetAndEmployee(): array
    {
        $company = Company::query()->create([
            'code' => 'asset_notif_co',
            'name' => 'Asset Notif Co',
            'legal_name' => 'Asset Notif Co Ltd',
            'status' => 'active',
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        $employeeUser = User::query()->create([
            'name' => 'Asset Holder',
            'email' => 'asset-holder@example.com',
            'password' => bcrypt('StrongPass1'),
        ]);

        $profile = EmployeeProfile::query()->create([
            'user_id' => $employeeUser->id,
            'company_id' => $company->id,
            'employment_status' => 'active',
            'base_salary' => 3_000_000,
            'fixed_allowance' => 0,
        ]);

        $asset = Asset::query()->create([
            'company_id' => $company->id,
            'asset_code' => 'LT-0001',
            'name' => 'Laptop X1',
            'status' => 'available',
            'condition' => 'good',
            'purchase_date' => '2027-01-01',
            'purchase_price' => 10_000_000,
        ]);

        $performedBy = User::query()->create([
            'name' => 'Asset Manager',
            'email' => 'asset-mgr@example.com',
            'password' => bcrypt('StrongPass1'),
        ]);

        return compact('company', 'employeeUser', 'profile', 'asset', 'performedBy');
    }

    public function test_assign_asset_sends_assigned_notification_to_employee(): void
    {
        Notification::fake();

        $ctx = $this->setupAssetAndEmployee();

        /** @var AssetService $service */
        $service = app(AssetService::class);
        $service->assignAsset($ctx['asset'], $ctx['profile'], [
            'assigned_date' => '2027-03-01',
            'condition_at_assign' => 'good',
            'notes' => 'Initial handover',
        ], $ctx['performedBy']->id);

        Notification::assertSentTo($ctx['employeeUser'], AssetAssignedNotification::class, function ($notification) use ($ctx) {
            $data = $notification->toDatabase($ctx['employeeUser']);
            return $data['event'] === 'asset.assigned'
                && (int) $data['assetId'] === (int) $ctx['asset']->id
                && $data['assetCode'] === 'LT-0001';
        });
    }

    public function test_return_asset_sends_returned_notification_to_employee(): void
    {
        Notification::fake();

        $ctx = $this->setupAssetAndEmployee();

        /** @var AssetService $service */
        $service = app(AssetService::class);
        $service->assignAsset($ctx['asset'], $ctx['profile'], [
            'assigned_date' => '2027-03-01',
            'condition_at_assign' => 'good',
        ], $ctx['performedBy']->id);

        $service->returnAsset($ctx['asset']->fresh(), [
            'returned_date' => '2027-03-20',
            'condition_at_return' => 'good',
            'notes' => 'Returned intact',
        ], $ctx['performedBy']->id);

        Notification::assertSentTo($ctx['employeeUser'], AssetAssignedNotification::class);
        Notification::assertSentTo($ctx['employeeUser'], AssetReturnedNotification::class, function ($notification) use ($ctx) {
            $data = $notification->toDatabase($ctx['employeeUser']);
            return $data['event'] === 'asset.returned'
                && (int) $data['assetId'] === (int) $ctx['asset']->id
                && $data['returnedDate'] === '2027-03-20';
        });
    }
}
