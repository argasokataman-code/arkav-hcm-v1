<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\HcmTaxGovernancePolicy;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class HcmTaxGovernancePolicyFactory extends Factory
{
    protected $model = HcmTaxGovernancePolicy::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'uuid' => Str::uuid(),
            'policy_code' => $this->faker->unique()->bothify('POL-####-????'),
            'name' => $this->faker->sentence(),
            'status' => 'draft',
            'version' => 1,
            'effective_start_date' => now(),
            'effective_end_date' => null,
            'rules' => [],
            'rate_schedules' => [],
            'created_by_user_id' => null,
            'submitted_by_user_id' => null,
            'submitted_at' => null,
            'approved_by_user_id' => null,
            'approved_at' => null,
            'published_by_user_id' => null,
            'published_at' => null,
        ];
    }
}
