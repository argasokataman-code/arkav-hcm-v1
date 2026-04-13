<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Package;
use App\Models\PurchaseTransaction;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseTransactionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected string $adminToken;
    protected string $userToken;
    protected Company $company;
    protected Subscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();

        // Admin user
        $this->postJson('/v1/identity/auth/register', [
            'name' => 'QA Admin',
            'email' => 'qa.login@example.com',
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $adminLogin = $this->postJson('/v1/identity/auth/login', [
            'email' => 'qa.login@example.com',
            'password' => 'StrongPass1',
        ])->assertStatus(200);

        $this->adminToken = $adminLogin->json('data.accessToken');

        // Regular user
        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Regular User',
            'email' => 'regular.user@example.com',
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $userLogin = $this->postJson('/v1/identity/auth/login', [
            'email' => 'regular.user@example.com',
            'password' => 'StrongPass1',
        ])->assertStatus(200);

        $this->userToken = $userLogin->json('data.accessToken');

        $package = Package::create([
            'code' => 'basic',
            'name' => 'Basic Plan',
            'monthly_price' => 99000,
            'yearly_price' => 990000,
            'billing_unit' => 'flat',
            'status' => 'active',
        ]);

        $this->company = Company::create([
            'code' => 'ptxn',
            'name' => 'Purchase Txn Co',
            'legal_name' => 'Purchase Txn Co LLC',
            'status' => 'active',
            'timezone' => 'UTC',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        $this->subscription = Subscription::create([
            'company_id' => $this->company->id,
            'package_id' => $package->id,
            'plan_code' => 'basic',
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'billing_cycle' => 'monthly',
            'amount' => 99000,
        ]);
    }

    private function adminRequest()
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->adminToken);
    }

    private function userRequest()
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->userToken);
    }

    public function test_list_transactions_with_status_filter(): void
    {
        PurchaseTransaction::create([
            'transaction_code' => PurchaseTransaction::generateCode(),
            'company_id' => $this->company->id,
            'subscription_id' => $this->subscription->id,
            'transaction_type' => 'subscription',
            'amount' => 100000,
            'tax_amount' => 10000,
            'discount_amount' => 0,
            'total_amount' => 110000,
            'status' => 'paid',
        ]);

        PurchaseTransaction::create([
            'transaction_code' => PurchaseTransaction::generateCode(),
            'company_id' => $this->company->id,
            'subscription_id' => $this->subscription->id,
            'transaction_type' => 'subscription',
            'amount' => 100000,
            'tax_amount' => 10000,
            'discount_amount' => 0,
            'total_amount' => 110000,
            'status' => 'draft',
        ]);

        $response = $this->adminRequest()->getJson('/v1/saas/transactions?status=paid');

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('paid', $response->json('data.0.status'));
    }

    public function test_create_transaction_as_admin(): void
    {
        $response = $this->adminRequest()->postJson('/v1/saas/transactions', [
            'company_id' => $this->company->id,
            'subscription_id' => $this->subscription->id,
            'transaction_type' => 'subscription',
            'description' => 'Monthly billing',
            'amount' => 100000,
            'tax_amount' => 10000,
            'discount_amount' => 5000,
            'status' => 'issued',
        ]);

        $response->assertStatus(201)->assertJson(['success' => true]);
        $this->assertEquals(105000.0, $response->json('data.totalAmount'));
        $this->assertNotEmpty($response->json('data.transactionCode'));
    }

    public function test_create_transaction_forbidden_for_non_admin(): void
    {
        $response = $this->userRequest()->postJson('/v1/saas/transactions', [
            'company_id' => $this->company->id,
            'subscription_id' => $this->subscription->id,
            'transaction_type' => 'subscription',
            'amount' => 100000,
            'status' => 'issued',
        ]);

        $response->assertStatus(403)->assertJson(['success' => false]);
    }

    public function test_update_transaction_status_to_paid(): void
    {
        $transaction = PurchaseTransaction::create([
            'transaction_code' => PurchaseTransaction::generateCode(),
            'company_id' => $this->company->id,
            'subscription_id' => $this->subscription->id,
            'transaction_type' => 'subscription',
            'amount' => 100000,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => 100000,
            'status' => 'issued',
        ]);

        $response = $this->adminRequest()->putJson('/v1/saas/transactions/' . $transaction->id, [
            'status' => 'paid',
            'paid_at' => now()->toIso8601String(),
            'payment_method' => 'bank_transfer',
            'payment_reference' => 'TRX-123',
        ]);

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertEquals('paid', $response->json('data.status'));
        $this->assertEquals('bank_transfer', $response->json('data.paymentMethod'));
    }

    public function test_show_transaction_detail_returns_formatted_payload(): void
    {
        $transaction = PurchaseTransaction::create([
            'transaction_code' => PurchaseTransaction::generateCode(),
            'company_id' => $this->company->id,
            'subscription_id' => $this->subscription->id,
            'transaction_type' => 'subscription',
            'amount' => 100000,
            'tax_amount' => 10000,
            'discount_amount' => 0,
            'total_amount' => 110000,
            'status' => 'issued',
        ]);

        $response = $this->adminRequest()->getJson('/v1/saas/transactions/' . $transaction->id);

        $response->assertOk()->assertJsonStructure([
            'success',
            'data' => [
                'id',
                'transactionCode',
                'companyId',
                'transactionType',
                'amount',
                'taxAmount',
                'discountAmount',
                'totalAmount',
                'status',
            ],
        ]);
    }

    public function test_generate_code_has_expected_prefix(): void
    {
        $code = PurchaseTransaction::generateCode();

        $this->assertStringStartsWith('TXN-', $code);
        $this->assertGreaterThan(10, strlen($code));
    }
}
