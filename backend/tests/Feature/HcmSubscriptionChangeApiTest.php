<?php

namespace Tests\Feature;

use App\Models\AuthToken;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\HcmPermission;
use App\Models\HcmRole;
use App\Models\HcmSubscriptionChangeRequest;
use App\Models\HcmUserRole;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\PackageFeature;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * F4 — Tenant-initiated subscription plan change flow.
 *
 *  - Preview endpoint menghitung selisih harga tanpa menulis ke DB.
 *  - Change-plan membuat request pending dan menolak request kedua selagi
 *    ada yang masih pending.
 *  - Cancel-change hanya berlaku untuk request milik tenant & status pending.
 *  - Super-admin approve untuk upgrade menandai approved (tanpa auto-apply),
 *    sedangkan apply paket mengikuti alur checkout + invoice paid.
 *  - Middleware /upgrade redirect dibuktikan via WebFeatureGateTest.
 */
class HcmSubscriptionChangeApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{user: User, company: Company, token: string, packageBasic: Package, packagePro: Package}
     */
    private function bootstrapTenant(string $suffix): array
    {
        $code = 'TST'.strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        $company = Company::query()->create([
            'code' => $code,
            'name' => 'F4 '.$suffix,
            'legal_name' => 'F4 '.$suffix.' Ltd',
            'status' => 'active',
            'timezone' => 'UTC',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        $basic = Package::query()->create([
            'code' => 'f4b-'.strtolower($suffix),
            'name' => 'F4 Basic '.$suffix,
            'monthly_price' => 99000,
            'yearly_price' => 990000,
            'billing_unit' => 'company',
            'status' => 'active',
        ]);
        PackageFeature::query()->create([
            'package_uuid' => $basic->uuid,
            'feature_code' => 'employee_management',
            'feature_name' => 'Employee Management',
            'limit' => 10,
        ]);

        $pro = Package::query()->create([
            'code' => 'f4p-'.strtolower($suffix),
            'name' => 'F4 Pro '.$suffix,
            'monthly_price' => 299000,
            'yearly_price' => 2990000,
            'billing_unit' => 'company',
            'status' => 'active',
        ]);
        PackageFeature::query()->create([
            'package_uuid' => $pro->uuid,
            'feature_code' => 'employee_management',
            'feature_name' => 'Employee Management',
            'limit' => 100,
        ]);
        PackageFeature::query()->create([
            'package_uuid' => $pro->uuid,
            'feature_code' => 'tickets',
            'feature_name' => 'Tickets',
            'limit' => 1,
        ]);

        Subscription::query()->create([
            'company_id' => $company->id,
            'package_uuid' => $basic->uuid,
            'plan_code' => $basic->code,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'billing_cycle' => 'monthly',
            'amount' => 99000,
        ]);

        $user = User::query()->create([
            'name' => 'F4 Owner '.$suffix,
            'email' => 'f4.'.strtolower($suffix).'@example.com',
            'password' => bcrypt('StrongPass1'),
        ]);

        CompanyUser::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'status' => 'active',
            'joined_at' => now()->subDay(),
            'invited_by_user_id' => null,
        ]);

        $role = HcmRole::query()->create([
            'company_id' => $company->id,
            'code' => 'HCM_ADMIN',
            'name' => 'HCM Admin',
            'status' => 'active',
            'is_system' => true,
        ]);
        $permission = HcmPermission::query()->updateOrCreate(
            ['code' => 'hcm.admin'],
            [
                'module' => 'hcm',
                'resource' => 'system',
                'action' => 'admin',
                'name' => 'HCM Admin',
                'is_active' => true,
            ]
        );
        DB::table('hcm_role_permissions')->insert([
            'role_id' => $role->id,
            'permission_id' => $permission->id,
            'company_id' => $company->id,
            'company_uuid' => $company->uuid,
            'uuid' => (string) Str::uuid(),
        ]);
        HcmUserRole::query()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        $rawToken = 'tst-f4-'.strtolower($suffix);
        AuthToken::query()->create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $rawToken),
            'expires_at' => now()->addHour(),
        ]);

        return [
            'user' => $user,
            'company' => $company,
            'token' => $rawToken,
            'packageBasic' => $basic,
            'packagePro' => $pro,
        ];
    }

    private function tenantHeaders(array $ctx): array
    {
        return [
            'Authorization' => 'Bearer '.$ctx['token'],
            'X-Company-Id' => (string) $ctx['company']->id,
        ];
    }

    public function test_preview_change_returns_price_delta_without_persisting(): void
    {
        $ctx = $this->bootstrapTenant('Prev');

        $resp = $this->withHeaders($this->tenantHeaders($ctx))
            ->postJson('/v1/hcm/subscriptions/preview-change', [
                'action' => 'upgrade',
                'to_package_uuid' => $ctx['packagePro']->uuid,
            ])->assertOk();

        $this->assertSame('upgrade', $resp->json('data.preview.action'));
        $this->assertSame($ctx['packageBasic']->uuid, $resp->json('data.preview.from_package.uuid'));
        $this->assertSame($ctx['packagePro']->uuid, $resp->json('data.preview.to_package.uuid'));
        $this->assertSame(299000 - 99000, (int) $resp->json('data.preview.price_delta'));
        $this->assertTrue($resp->json('data.has_active_subscription'));
        $this->assertSame(0, HcmSubscriptionChangeRequest::query()->count());
    }

    public function test_change_plan_creates_pending_request_and_blocks_duplicate(): void
    {
        $ctx = $this->bootstrapTenant('Chg');

        $first = $this->withHeaders($this->tenantHeaders($ctx))
            ->postJson('/v1/hcm/subscriptions/change-plan', [
                'action' => 'upgrade',
                'to_package_uuid' => $ctx['packagePro']->uuid,
                'notes' => 'Need tickets',
            ])->assertStatus(201);

        $this->assertSame('pending', $first->json('data.status'));
        $this->assertSame(1, HcmSubscriptionChangeRequest::query()->count());

        $dup = $this->withHeaders($this->tenantHeaders($ctx))
            ->postJson('/v1/hcm/subscriptions/change-plan', [
                'action' => 'upgrade',
                'to_package_uuid' => $ctx['packagePro']->uuid,
            ])->assertStatus(409);

        $this->assertSame('CHANGE_REQUEST_PENDING', $dup->json('error.code'));
    }

    public function test_cancel_change_transitions_pending_to_cancelled(): void
    {
        $ctx = $this->bootstrapTenant('Can');

        $created = $this->withHeaders($this->tenantHeaders($ctx))
            ->postJson('/v1/hcm/subscriptions/change-plan', [
                'action' => 'upgrade',
                'to_package_uuid' => $ctx['packagePro']->uuid,
            ])->assertStatus(201);

        $requestId = (string) $created->json('data.id');

        $resp = $this->withHeaders($this->tenantHeaders($ctx))
            ->postJson('/v1/hcm/subscriptions/cancel-change', [
                'id' => $requestId,
            ])->assertOk();

        $this->assertSame('cancelled', $resp->json('data.status'));
    }

    public function test_super_admin_approve_upgrade_marks_request_approved_without_auto_applying_package(): void
    {
        $ctx = $this->bootstrapTenant('App');

        $this->withHeaders($this->tenantHeaders($ctx))
            ->postJson('/v1/hcm/subscriptions/change-plan', [
                'action' => 'upgrade',
                'to_package_uuid' => $ctx['packagePro']->uuid,
            ])->assertStatus(201);

        $pending = HcmSubscriptionChangeRequest::query()->firstOrFail();

        $admin = User::query()->create([
            'name' => 'F4 Platform Admin',
            'email' => 'qa.login@example.com',
            'password' => bcrypt('StrongPass1'),
            'is_super_admin' => true,
        ]);
        $adminToken = 'tst-f4-admin';
        AuthToken::query()->create([
            'user_id' => $admin->id,
            'token_hash' => hash('sha256', $adminToken),
            'expires_at' => now()->addHour(),
        ]);

        $resp = $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->postJson('/v1/saas/subscription-change-requests/'.$pending->id.'/approve')
            ->assertOk();

        $this->assertSame('approved', $resp->json('data.status'));

        $subscription = Subscription::query()->where('company_id', $ctx['company']->id)->firstOrFail();
        $this->assertSame('active', $subscription->status);
        $this->assertSame($ctx['packageBasic']->uuid, $subscription->package_uuid);
        $this->assertSame($ctx['packageBasic']->code, $subscription->plan_code);
        $this->assertEqualsWithDelta(99000, (float) $subscription->amount, 0.01);
    }

    public function test_inactive_target_package_is_rejected_for_preview_and_change_plan(): void
    {
        $ctx = $this->bootstrapTenant('Inactive');

        $ctx['packagePro']->forceFill(['status' => 'inactive'])->save();

        $this->withHeaders($this->tenantHeaders($ctx))
            ->postJson('/v1/hcm/subscriptions/preview-change', [
                'action' => 'upgrade',
                'to_package_uuid' => $ctx['packagePro']->uuid,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'PACKAGE_NOT_ACTIVE');

        $this->withHeaders($this->tenantHeaders($ctx))
            ->postJson('/v1/hcm/subscriptions/change-plan', [
                'action' => 'upgrade',
                'to_package_uuid' => $ctx['packagePro']->uuid,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'PACKAGE_NOT_ACTIVE');
    }

    public function test_non_admin_tenant_user_cannot_submit_change_request(): void
    {
        $ctx = $this->bootstrapTenant('NoAdm');

        // Create a plain employee user (no admin role) on the same company.
        $employee = User::query()->create([
            'name' => 'Plain Employee',
            'email' => 'plain.'.strtolower('NoAdm').'@example.com',
            'password' => bcrypt('StrongPass1'),
        ]);
        CompanyUser::query()->create([
            'company_id' => $ctx['company']->id,
            'user_id' => $employee->id,
            'role' => 'member',
            'status' => 'active',
            'joined_at' => now(),
            'invited_by_user_id' => null,
        ]);

        $token = 'tst-f4-plain';
        AuthToken::query()->create([
            'user_id' => $employee->id,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addHour(),
        ]);

        $resp = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $ctx['company']->id,
        ])->postJson('/v1/hcm/subscriptions/change-plan', [
            'action' => 'upgrade',
            'to_package_uuid' => $ctx['packagePro']->uuid,
        ])->assertStatus(403);

        $this->assertSame('FORBIDDEN', $resp->json('error.code'));
        $this->assertSame(0, HcmSubscriptionChangeRequest::query()->count());
    }

    public function test_non_primary_super_admin_cannot_access_global_change_request_queue(): void
    {
        config(['hcm.admin_email' => 'qa.login@example.com']);

        $ctx = $this->bootstrapTenant('QueueGuard');

        $this->withHeaders($this->tenantHeaders($ctx))
            ->postJson('/v1/hcm/subscriptions/change-plan', [
                'action' => 'upgrade',
                'to_package_uuid' => $ctx['packagePro']->uuid,
            ])->assertStatus(201);

        $secondaryAdmin = User::query()->create([
            'name' => 'Secondary Global Admin',
            'email' => 'qa.hcm@example.com',
            'password' => bcrypt('StrongPass1'),
            'is_super_admin' => true,
        ]);

        $secondaryToken = 'tst-f4-secondary-admin';
        AuthToken::query()->create([
            'user_id' => $secondaryAdmin->id,
            'token_hash' => hash('sha256', $secondaryToken),
            'expires_at' => now()->addHour(),
        ]);

        $this->withHeaders(['Authorization' => 'Bearer '.$secondaryToken])
            ->getJson('/v1/saas/subscription-change-requests')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'PRIMARY_SUPER_ADMIN_REQUIRED');

        $requestId = (string) HcmSubscriptionChangeRequest::query()->value('id');

        $this->withHeaders(['Authorization' => 'Bearer '.$secondaryToken])
            ->postJson('/v1/saas/subscription-change-requests/'.$requestId.'/approve')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'PRIMARY_SUPER_ADMIN_REQUIRED');
    }

    public function test_trial_package_is_rejected_as_change_target(): void
    {
        $ctx = $this->bootstrapTenant('Trial');

        $trialPackage = Package::query()->create([
            'code' => 'trial',
            'name' => 'Trial',
            'monthly_price' => 0,
            'yearly_price' => 0,
            'billing_unit' => 'company',
            'status' => 'active',
        ]);

        $this->withHeaders($this->tenantHeaders($ctx))
            ->postJson('/v1/hcm/subscriptions/preview-change', [
                'action' => 'downgrade',
                'to_package_uuid' => $trialPackage->uuid,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'TRIAL_PACKAGE_NOT_ALLOWED');

        $this->withHeaders($this->tenantHeaders($ctx))
            ->postJson('/v1/hcm/subscriptions/change-plan', [
                'action' => 'downgrade',
                'to_package_uuid' => $trialPackage->uuid,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'TRIAL_PACKAGE_NOT_ALLOWED');

        $this->assertSame(0, HcmSubscriptionChangeRequest::query()->count());
    }

    public function test_downgrade_to_cheaper_package_creates_downgrade_action_with_deferred_effective_date(): void
    {
        $ctx = $this->bootstrapTenant('Dwn');

        // Current active package is 'basic' (99 000). Downgrade to a cheaper one (50 000).
        $cheap = Package::query()->create([
            'code' => 'f4cheap-dwn',
            'name' => 'F4 Cheap Dwn',
            'monthly_price' => 50000,
            'yearly_price' => 500000,
            'billing_unit' => 'company',
            'status' => 'active',
        ]);

        $resp = $this->withHeaders($this->tenantHeaders($ctx))
            ->postJson('/v1/hcm/subscriptions/change-plan', [
                'action' => 'downgrade',
                'to_package_uuid' => $cheap->uuid,
                'notes' => 'Reducing cost',
            ])->assertStatus(201);

        $this->assertSame('downgrade', $resp->json('data.action'));
        $this->assertSame('pending', $resp->json('data.status'));

        // Effective date for downgrade must NOT be now — it should be at the end of the current period.
        $effectiveAt = $resp->json('data.preview.effective_at');
        $this->assertNotNull($effectiveAt);
        $this->assertTrue(
            Carbon::parse($effectiveAt)->isAfter(now()),
            'Downgrade effective_at must be in the future (end of current billing period)'
        );
    }

    public function test_action_auto_detected_by_price_regardless_of_requested_action_label(): void
    {
        $ctx = $this->bootstrapTenant('AutoAct');

        // Current subscription is on 'basic' (99 000). Request action 'upgrade' but target is cheaper.
        $cheap = Package::query()->create([
            'code' => 'f4cheap-autoact',
            'name' => 'F4 Cheap AutoAct',
            'monthly_price' => 50000,
            'yearly_price' => 500000,
            'billing_unit' => 'company',
            'status' => 'active',
        ]);

        $resp = $this->withHeaders($this->tenantHeaders($ctx))
            ->postJson('/v1/hcm/subscriptions/preview-change', [
                'action' => 'upgrade',          // user sends 'upgrade' but target is cheaper
                'to_package_uuid' => $cheap->uuid,
            ])->assertOk();

        // Controller must auto-correct to 'downgrade' based on price comparison.
        $this->assertSame('downgrade', $resp->json('data.preview.action'));
        $this->assertSame(-49000, (int) $resp->json('data.preview.price_delta'));
    }

    public function test_downgrade_remains_allowed_when_latest_subscription_is_not_active(): void
    {
        $ctx = $this->bootstrapTenant('NonActive');

        $latest = Subscription::query()->where('company_id', $ctx['company']->id)->latest('id')->firstOrFail();
        $latest->update([
            'status' => 'suspended',
            'ends_at' => now()->subDay(),
        ]);

        $cheaper = Package::query()->create([
            'code' => 'f4-cheap-nonactive',
            'name' => 'F4 Cheap NonActive',
            'monthly_price' => 49000,
            'yearly_price' => 490000,
            'billing_unit' => 'company',
            'status' => 'active',
        ]);

        $preview = $this->withHeaders($this->tenantHeaders($ctx))
            ->postJson('/v1/hcm/subscriptions/preview-change', [
                'action' => 'downgrade',
                'to_package_uuid' => $cheaper->uuid,
            ])->assertOk();

        $this->assertFalse($preview->json('data.has_active_subscription'));
        $this->assertSame('downgrade', $preview->json('data.preview.action'));
        $this->assertContains('SUBSCRIPTION_NOT_ACTIVE', (array) $preview->json('data.preview.anomaly_flags'));

        $this->withHeaders($this->tenantHeaders($ctx))
            ->postJson('/v1/hcm/subscriptions/change-plan', [
                'action' => 'downgrade',
                'to_package_uuid' => $cheaper->uuid,
            ])->assertStatus(201)
            ->assertJsonPath('data.action', 'downgrade');
    }

    public function test_approved_downgrade_generates_new_invoice_and_requires_paying_new_invoice_only(): void
    {
        $ctx = $this->bootstrapTenant('DowngradePayNew');

        $subscription = Subscription::query()->where('company_id', $ctx['company']->id)->latest('id')->firstOrFail();
        $subscription->update([
            'status' => 'suspended',
            'ends_at' => now()->subDay(),
        ]);

        // Legacy overdue invoice (old package) should not be the one required for re-activation.
        $legacyInvoice = Invoice::query()->create([
            'company_id' => $ctx['company']->id,
            'subscription_id' => $subscription->id,
            'issue_date' => now()->subDays(10)->toDateString(),
            'due_date' => now()->subDays(5)->toDateString(),
            'amount_due' => 3000000,
            'status' => 'sent',
            'is_paid' => false,
            'notes' => 'Legacy overdue invoice from old package',
        ]);

        $cheap = Package::query()->create([
            'code' => 'f4-cheap-paynew',
            'name' => 'F4 Cheap PayNew',
            'monthly_price' => 50000,
            'yearly_price' => 500000,
            'billing_unit' => 'company',
            'status' => 'active',
        ]);

        $created = $this->withHeaders($this->tenantHeaders($ctx))
            ->postJson('/v1/hcm/subscriptions/change-plan', [
                'action' => 'downgrade',
                'to_package_uuid' => $cheap->uuid,
            ])->assertStatus(201);

        $requestId = (string) $created->json('data.id');

        $admin = User::query()->create([
            'name' => 'Primary Super Admin',
            'email' => 'qa.login@example.com',
            'password' => bcrypt('StrongPass1'),
            'is_super_admin' => true,
        ]);
        $adminToken = 'tst-f4-admin-paynew';
        AuthToken::query()->create([
            'user_id' => $admin->id,
            'token_hash' => hash('sha256', $adminToken),
            'expires_at' => now()->addHour(),
        ]);

        $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->postJson('/v1/saas/subscription-change-requests/'.$requestId.'/approve')
            ->assertOk()
            ->assertJsonPath('data.status', 'applied');

        $subscription->refresh();
        $this->assertSame('pending_payment', $subscription->status);
        $this->assertSame($cheap->uuid, $subscription->package_uuid);
        $this->assertSame($cheap->code, $subscription->plan_code);
        $this->assertEqualsWithDelta(50000, (float) $subscription->amount, 0.01);

        $newInvoice = Invoice::query()
            ->where('company_id', $ctx['company']->id)
            ->where('subscription_id', $subscription->id)
            ->where('is_paid', false)
            ->where('notes', 'like', '%[downgrade_request:'.$requestId.']%')
            ->latest('id')
            ->first();

        $this->assertNotNull($newInvoice);
        $this->assertEqualsWithDelta(50000, (float) $newInvoice->amount_due, 0.01);

        // Paying old invoice must NOT activate subscription.
        $legacyInvoice->markAsPaid();
        $subscription->refresh();
        $this->assertSame('pending_payment', $subscription->status);

        // Paying the new downgrade invoice activates subscription.
        $newInvoice->markAsPaid();
        $subscription->refresh();
        $this->assertSame('active', $subscription->status);
    }

    public function test_preview_includes_partial_and_overdue_billing_anomaly_flags(): void
    {
        $ctx = $this->bootstrapTenant('Anomaly');

        $subscription = Subscription::query()->where('company_id', $ctx['company']->id)->latest('id')->firstOrFail();
        $invoice = Invoice::query()->create([
            'company_id' => $ctx['company']->id,
            'subscription_id' => $subscription->id,
            'issue_date' => now()->subDays(7)->toDateString(),
            'due_date' => now()->subDays(2)->toDateString(),
            'amount_due' => 300000,
            'status' => 'sent',
            'is_paid' => false,
            'notes' => 'Anomaly simulation invoice',
        ]);

        Payment::query()->create([
            'company_id' => $ctx['company']->id,
            'subscription_id' => $subscription->id,
            'invoice_id' => $invoice->id,
            'amount' => 100000,
            'currency' => 'IDR',
            'status' => 'completed',
            'payment_method' => 'bank_transfer',
            'gateway' => 'mock',
            'gateway_reference' => 'partial-'.strtolower(Str::random(8)),
            'verified_at' => now()->subDay(),
        ]);

        $cheaper = Package::query()->create([
            'code' => 'f4-cheap-anomaly',
            'name' => 'F4 Cheap Anomaly',
            'monthly_price' => 59000,
            'yearly_price' => 590000,
            'billing_unit' => 'company',
            'status' => 'active',
        ]);

        $resp = $this->withHeaders($this->tenantHeaders($ctx))
            ->postJson('/v1/hcm/subscriptions/preview-change', [
                'action' => 'downgrade',
                'to_package_uuid' => $cheaper->uuid,
            ])->assertOk();

        $flags = (array) $resp->json('data.preview.anomaly_flags');
        $note = (string) $resp->json('data.preview.notes');

        $this->assertContains('BILLING_OVERDUE_INVOICE', $flags);
        $this->assertContains('BILLING_PARTIAL_PAYMENT', $flags);
        $this->assertStringContainsString('Catatan anomali', $note);
        $this->assertStringContainsString('pembayaran parsial', strtolower($note));
    }
}
