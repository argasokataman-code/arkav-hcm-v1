<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Domain;
use Illuminate\Database\Eloquent\Factories\Factory;

class DomainFactory extends Factory
{
    protected $model = Domain::class;

    public function definition(): array
    {
        return [
            'domain_name' => $this->faker->unique()->domainName(),
            'company_id' => Company::factory(),
            'verification_type' => $this->faker->randomElement(['dns', 'file']),
            'status' => $this->faker->randomElement(['pending', 'verified', 'failed']),
            'verification_token' => $this->faker->sha256(),
            'verification_data' => null,
            'verified_at' => $this->faker->optional()->dateTime(),
            'notes' => $this->faker->sentence(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
