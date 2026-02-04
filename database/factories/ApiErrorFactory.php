<?php

declare(strict_types=1);

namespace WorkDoneRight\ApiGuardian\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use WorkDoneRight\ApiGuardian\Models\ApiError;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\WorkDoneRight\ApiGuardian\Models\ApiError>
 */
final class ApiErrorFactory extends Factory
{
    protected $model = ApiError::class;

    public function definition(): array
    {
        return [
            'error_id' => 'err_'.uniqid().'_'.time(),
            'exception_class' => fake()->randomElement([
                \Illuminate\Validation\ValidationException::class,
                \Illuminate\Auth\AuthenticationException::class,
                \Illuminate\Auth\Access\AuthorizationException::class,
                \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
                \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException::class,
                'Exception',
            ]),
            'error_code' => fake()->optional(0.7)->randomElement([
                'VALIDATION_FAILED',
                'UNAUTHORIZED',
                'FORBIDDEN',
                'NOT_FOUND',
                'INTERNAL_ERROR',
                'RATE_LIMIT_EXCEEDED',
            ]),
            'message' => fake()->sentence(),
            'status_code' => fake()->randomElement([400, 401, 403, 404, 422, 429, 500, 502, 503]),
            'context' => fake()->optional(0.6)->randomElements([
                'file' => fake()->filePath(),
                'line' => fake()->numberBetween(1, 1000),
                'trace' => [],
            ]),
            'meta' => fake()->optional(0.4)->randomElements([
                'user_id' => fake()->numberBetween(1, 100),
                'request_id' => fake()->uuid(),
            ]),
            'request_method' => fake()->randomElement(['GET', 'POST', 'PUT', 'DELETE', 'PATCH']),
            'request_url' => fake()->url(),
            'request_headers' => fake()->optional(0.7)->randomElements([
                'Accept' => 'application/json',
                'User-Agent' => fake()->userAgent(),
            ]),
            'request_data' => fake()->optional(0.5)->randomElements([
                'param1' => fake()->word(),
                'param2' => fake()->numberBetween(1, 100),
            ]),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'user_id' => fake()->optional(0.6)->numberBetween(1, 100),
            'is_resolved' => fake()->boolean(20), // 20% chance of being resolved
            'occurrence_count' => fake()->numberBetween(1, 10),
            'first_occurred_at' => fake()->dateTimeBetween('-1 month', '-1 week'),
            'last_occurred_at' => fake()->dateTimeBetween('-1 week', 'now'),
        ];
    }

    public function resolved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_resolved' => true,
        ]);
    }

    public function unresolved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_resolved' => false,
        ]);
    }

    public function statusCode(int $code): static
    {
        return $this->state(fn (array $attributes): array => [
            'status_code' => $code,
        ]);
    }

    public function recent(): static
    {
        return $this->state(fn (array $attributes): array => [
            'created_at' => fake()->dateTimeBetween('-24 hours', 'now'),
            'first_occurred_at' => fake()->dateTimeBetween('-24 hours', '-1 hour'),
            'last_occurred_at' => fake()->dateTimeBetween('-1 hour', 'now'),
        ]);
    }

    public function frequent(): static
    {
        return $this->state(fn (array $attributes): array => [
            'occurrence_count' => fake()->numberBetween(5, 20),
        ]);
    }
}
