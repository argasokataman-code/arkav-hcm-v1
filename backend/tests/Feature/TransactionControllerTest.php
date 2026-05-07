<?php

namespace Tests\Feature;

use App\Models\AuthToken;
use App\Models\Transaction;
use App\Models\Subscription;
use App\Models\Package;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Package $package;
    private Company $company;
    private Subscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create([
            'name' => 'QA Admin',
            'email' => 'qa.login@example.com',
        ]);
        $this->company = Company::factory()->create();
        $this->package = Package::factory()->create();
        $this->subscription = Subscription::factory()
            ->for($this->company)
            ->for($this->package)
            ->create();
    }

    private function adminCookieHeader(): string
    {
        $rawToken = bin2hex(random_bytes(32));

        AuthToken::query()->create([
            'user_id' => $this->admin->id,
            'token_hash' => hash('sha256', $rawToken),
            'expires_at' => now()->addDay(),
        ]);

        return (string) config('auth.api_token_cookie.name', 'arcav_access_token').'='.$rawToken;
    }

    public function test_list_transactions_requires_admin()
    {
        $response = $this->getJson('/v1/saas/transactions');
        $response->assertStatus(401);
    }

    public function test_list_transactions_as_admin()
    {
        Transaction::factory()
            ->for($this->subscription)
            ->count(5)
            ->create();

        $response = $this->withHeader('Cookie', $this->adminCookieHeader())
            ->getJson('/v1/saas/transactions');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => ['*' => ['id', 'invoiceNumber', 'amount', 'status', 'paymentMethod']],
                'pagination',
            ])
            ->assertJsonCount(5, 'data');
    }

    public function test_filter_transactions_by_status()
    {
        Transaction::factory()->for($this->subscription)->create(['status' => 'completed']);
        Transaction::factory()->for($this->subscription)->create(['status' => 'pending']);
        Transaction::factory()->for($this->subscription)->create(['status' => 'pending']);

        $response = $this->withHeader('Cookie', $this->adminCookieHeader())
            ->getJson('/v1/saas/transactions?status=pending');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_create_transaction_requires_admin()
    {
        $response = $this->postJson('/v1/saas/transactions', [
            'subscription_id' => $this->subscription->uuid,
            'invoice_number' => 'INV-001',
            'amount' => 100000,
            'status' => 'completed',
            'payment_method' => 'credit_card',
        ]);
        $response->assertStatus(401);
    }

    public function test_create_transaction_as_admin()
    {
        $response = $this->withHeader('Cookie', $this->adminCookieHeader())
            ->postJson('/v1/saas/transactions', [
                'subscription_id' => $this->subscription->uuid,
                'invoice_number' => 'INV-TEST-001',
                'amount' => 150000,
                'status' => 'completed',
                'payment_method' => 'bank_transfer',
                'payment_gateway' => 'midtrans',
            ]);

        $response->assertCreated()
            ->assertJsonStructure(['success', 'data' => ['id', 'invoiceNumber', 'amount', 'status']])
            ->assertJsonFragment(['invoiceNumber' => 'INV-TEST-001', 'amount' => 150000]);

        $this->assertDatabaseHas('transactions', ['invoice_number' => 'INV-TEST-001']);
    }

    public function test_update_transaction_status()
    {
        $transaction = Transaction::factory()
            ->for($this->subscription)
            ->create(['status' => 'pending']);

        $response = $this->withHeader('Cookie', $this->adminCookieHeader())
            ->putJson('/v1/saas/transactions/' . $transaction->id, [
                'status' => 'completed',
            ]);

        $response->assertOk()
            ->assertJsonFragment(['status' => 'completed']);

        $transaction->refresh();
        $this->assertEquals('completed', $transaction->status);
    }

    public function test_export_transactions()
    {
        Transaction::factory()
            ->for($this->subscription)
            ->count(2)
            ->create();

        $xlsxResponse = $this->withHeader('Cookie', $this->adminCookieHeader())
            ->getJson('/v1/saas/transactions/export');

        $xlsxResponse->assertOk();
        $this->assertStringContainsString(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            (string) $xlsxResponse->headers->get('Content-Type')
        );

        $csvResponse = $this->withHeader('Cookie', $this->adminCookieHeader())
            ->getJson('/v1/saas/transactions/export?format=csv');

        $csvResponse->assertOk();
        $this->assertStringContainsString('text/csv', (string) $csvResponse->headers->get('Content-Type'));
    }
}
