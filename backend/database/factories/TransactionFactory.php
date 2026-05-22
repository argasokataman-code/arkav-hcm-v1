<?php

namespace Database\Factories;

use App\Models\Transaction;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition(): array
    {
        return [
            'subscription_id' => Subscription::factory(),
            'invoice_number' => 'INV-' . $this->faker->unique()->bothify('########'),
            'amount' => $this->faker->numberBetween(50000, 5000000),
            'status' => $this->faker->randomElement(['pending', 'completed', 'failed', 'refunded']),
            'payment_method' => $this->faker->randomElement(['credit_card', 'bank_transfer', 'e_wallet', 'other']),
            'payment_gateway' => $this->faker->optional()->randomElement(['midtrans', 'stripe']),
            'transaction_id' => $this->faker->optional()->sha256(),
            'notes' => $this->faker->optional()->sentence(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
