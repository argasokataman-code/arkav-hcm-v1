<?php

namespace Database\Factories;

use App\Models\HcmTaxGovernanceAnomaly;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

class HcmTaxGovernanceAnomalyFactory extends Factory
{
    protected $model = HcmTaxGovernanceAnomaly::class;

    public function definition(): array
    {
        $types = [
            'MISSING_TAX_PROFILE',
            'POLICY_DRAFT_STALE',
            'POLICY_SUPERSEDED_ACTIVE',
            'POLICY_VERSION_CONFLICT',
            'PUBLISH_FAILURE',
            'DRIFT_DETECTED',
        ];

        return [
            'id' => \Illuminate\Support\Str::uuid(),
            'company_id' => Company::factory(),
            'anomaly_type' => $this->faker->randomElement($types),
            'severity' => $this->faker->randomElement(['info', 'warning', 'critical']),
            'affected_policy_id' => null,
            'affected_employee_id' => null,
            'description' => $this->faker->sentence(),
            'evidence_snapshot' => [],
            'detected_at' => now()->subDays($this->faker->numberBetween(1, 30)),
            'resolved_at' => null,
            'resolution_note' => null,
            'acknowledged_by_user_id' => null,
            'acknowledged_at' => null,
        ];
    }
}
