<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\Company;
use App\Models\Subscription;
use App\Models\PurchaseTransaction;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'subscription_id' => Subscription::factory(),
            'purchase_transaction_id' => PurchaseTransaction::factory(),
            'invoice_id' => Invoice::factory(),
            'amount' => $this->faker->randomFloat(2, 100000, 5000000),
            'currency' => 'IDR',
            'status' => $this->faker->randomElement(['pending', 'completed', 'failed', 'disputed']),
            'payment_method' => $this->faker->randomElement(['bank_transfer', 'credit_card', 'e_wallet', 'cash', 'check']),
            'gateway' => $this->faker->optional()->randomElement(['stripe', 'xendit', 'midtrans']),
            'gateway_reference' => $this->faker->optional()->bothify('GW-####-####'),
            'paid_at' => $this->faker->optional()->dateTime(),
            'verified_at' => $this->faker->optional()->dateTime(),
            'notes' => $this->faker->optional()->paragraph(),
            'metadata' => [
                'ip_address' => $this->faker->ipv4(),
                'user_agent' => $this->faker->userAgent(),
            ],
        ];
    }
}
