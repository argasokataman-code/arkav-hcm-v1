<?php

namespace Database\Factories;

use App\Models\PurchaseTransaction;
use App\Models\Company;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

class PurchaseTransactionFactory extends Factory
{
    protected $model = PurchaseTransaction::class;

    public function definition(): array
    {
        return [
            'transaction_code' => PurchaseTransaction::generateCode(),
            'company_id' => Company::factory(),
            'subscription_id' => Subscription::factory(),
            'transaction_type' => $this->faker->randomElement(['subscription', 'addon', 'refund', 'credit', 'manual']),
            'description' => $this->faker->sentence(),
            'amount' => $this->faker->randomFloat(2, 100000, 5000000),
            'tax_amount' => $this->faker->randomFloat(2, 0, 1000000),
            'discount_amount' => $this->faker->randomFloat(2, 0, 500000),
            'total_amount' => $this->faker->randomFloat(2, 100000, 5000000),
            'billing_period_start' => now()->startOfMonth(),
            'billing_period_end' => now()->endOfMonth(),
            'due_date' => now()->addDays(30),
            'paid_at' => $this->faker->optional()->dateTime(),
            'payment_method' => $this->faker->randomElement(['bank_transfer', 'credit_card', 'e_wallet', 'cash']),
            'payment_reference' => $this->faker->optional()->bothify('REF-####'),
            'status' => $this->faker->randomElement(['draft', 'issued', 'sent', 'paid', 'overdue', 'cancelled']),
            'notes' => $this->faker->optional()->paragraph(),
        ];
    }
}
