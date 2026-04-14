<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

class CompanyFactory extends Factory
{
    protected $model = Company::class;

    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->lexify('COMP_????'),
            'name' => $this->faker->company(),
            'status' => $this->faker->randomElement(['active', 'inactive']),
        ];
    }
}
