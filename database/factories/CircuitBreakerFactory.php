<?php

declare(strict_types=1);

namespace WorkDoneRight\ApiGuardian\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use WorkDoneRight\ApiGuardian\Models\CircuitBreaker;

/**
 * @extends Factory<CircuitBreaker>
 */
final class CircuitBreakerFactory extends Factory
{
    protected $model = CircuitBreaker::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $states = ['closed', 'open', 'half_open'];
        $state = fake()->randomElement($states);

        return [
            'service' => fake()->randomElement([
                'api-service',
                'database',
                'payment-gateway',
                'email-service',
                'storage-service',
            ]),
            'operation' => fake()->optional(0.6)->randomElement([
                'create_user',
                'process_payment',
                'send_email',
                'upload_file',
                'fetch_data',
            ]),
            'state' => $state,
            'failure_count' => $state === 'closed' ? 0 : fake()->numberBetween(1, 10),
            'failure_threshold' => fake()->numberBetween(3, 7),
            'recovery_timeout' => fake()->numberBetween(30, 120),
            'success_threshold' => fake()->numberBetween(2, 5),
            'last_failure_at' => $state === 'closed' ? null : fake()->dateTimeBetween('-1 hour', 'now'),
            'opened_at' => $state === 'closed' ? null : fake()->dateTimeBetween('-1 hour', 'now'),
            'next_attempt_at' => $state === 'open' ? now()->addSeconds(fake()->numberBetween(30, 120)) : null,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'state' => 'closed',
            'failure_count' => 0,
            'last_failure_at' => null,
            'opened_at' => null,
            'next_attempt_at' => null,
        ]);
    }

    public function open(): static
    {
        return $this->state(fn (array $attributes): array => [
            'state' => 'open',
            'failure_count' => fake()->numberBetween(5, 10),
            'last_failure_at' => fake()->dateTimeBetween('-1 hour', 'now'),
            'opened_at' => fake()->dateTimeBetween('-1 hour', 'now'),
            'next_attempt_at' => now()->addSeconds(fake()->numberBetween(30, 120)),
        ]);
    }

    public function halfOpen(): static
    {
        return $this->state(fn (array $attributes): array => [
            'state' => 'half_open',
            'failure_count' => fake()->numberBetween(3, 6),
            'last_failure_at' => fake()->dateTimeBetween('-2 hours', '-1 hour'),
            'opened_at' => fake()->dateTimeBetween('-2 hours', '-1 hour'),
            'next_attempt_at' => null,
        ]);
    }

    public function forService(string $service, ?string $operation = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'service' => $service,
            'operation' => $operation,
        ]);
    }
}
