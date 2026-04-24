<?php

namespace Database\Factories;

use App\Models\NotificationDelivery;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\NotificationDelivery>
 */
class NotificationDeliveryFactory extends Factory
{
    protected $model = NotificationDelivery::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_key' => $this->faker->word(),
            'channel' => $this->faker->randomElement(['database', 'mail', 'sms', 'webhook']),
            'status' => $this->faker->randomElement(['sent', 'failed', 'dropped']),
            'recipient' => $this->faker->email(),
            'company_uuid' => $this->faker->uuid(),
            'attempt_count' => $this->faker->numberBetween(0, 5),
            'last_error' => $this->faker->sentence(),
            'metadata' => [],
            'created_at' => $this->faker->dateTimeThisMonth(),
        ];
    }

    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'sent',
            'last_error' => null,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
        ]);
    }

    public function dropped(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'dropped',
        ]);
    }
}
