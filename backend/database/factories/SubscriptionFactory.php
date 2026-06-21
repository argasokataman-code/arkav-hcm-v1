<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Package;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        $package = Package::firstOrCreate(
            ['code' => 'default'],
            [
                'name' => 'Default Package',
                'monthly_price' => 100000,
                'yearly_price' => 1000000,
                'billing_unit' => 'company',
                'status' => 'active',
            ]
        );

        return [
            'company_id' => Company::factory(),
            'package_uuid' => $package->uuid,
            'plan_code' => $package->code,
            'status' => $this->faker->randomElement(['active', 'trial', 'paused', 'cancelled']),
            'starts_at' => now(),
            'ends_at' => now()->addYear(),
            'trial_ends_at' => now()->addDays(14),
            'auto_renew' => true,
            'billing_cycle' => 'monthly',
            'amount' => $package->monthly_price,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
