<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\HcmBillingTaxPolicy;
use Illuminate\Database\Eloquent\Factories\Factory;

class HcmBillingTaxPolicyFactory extends Factory
{
    protected $model = HcmBillingTaxPolicy::class;

    public function definition(): array
    {
        return [
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'company_id' => Company::factory(),
            'billing_month' => now()->format('Y-m'),
            'billing_cycle_type' => $this->faker->randomElement(['monthly', 'yearly', 'custom']),
            'tax_rate_percentage' => $this->faker->randomFloat(2, 0, 20),
            'base_calculation_method' => 'invoice_amount_due',
            'effective_from' => now()->toDateString(),
            'effective_to' => null,
            'status' => 'active',
            'notes' => null,
            'created_by_user_id' => null,
            'updated_by_user_id' => null,
        ];
    }
}
