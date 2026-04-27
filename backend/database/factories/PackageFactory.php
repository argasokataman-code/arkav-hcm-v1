<?php

namespace Database\Factories;

use App\Models\Package;
use Illuminate\Database\Eloquent\Factories\Factory;

class PackageFactory extends Factory
{
    protected $model = Package::class;

    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->lexify('PKG_????'),
            'name' => $this->faker->word(),
            'description' => $this->faker->sentence(),
            'monthly_price' => $this->faker->numberBetween(50000, 500000),
            'yearly_price' => $this->faker->numberBetween(500000, 5000000),
            'billing_unit' => 'company',
            'status' => 'active',
            'is_global_admin_only' => false,
            'color' => $this->faker->hexcolor(),
            'sort_order' => $this->faker->numberBetween(0, 10),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
