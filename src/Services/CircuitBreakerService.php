<?php

declare(strict_types=1);

namespace WorkDoneRight\ApiGuardian\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use WorkDoneRight\ApiGuardian\Models\CircuitBreaker;

final class CircuitBreakerService
{
    /**
     * Get all circuit breakers with optional filters.
     */
    public function getCircuitBreakers(array $filters = []): Collection
    {
        return CircuitBreaker::query()
            ->when(
                Arr::has($filters, 'state'),
                fn ($query) => $query->where('state', Arr::get($filters, 'state'))
            )
            ->when(
                Arr::has($filters, 'service'),
                fn ($query) => $query->where('service', 'like', '%'.Arr::get($filters, 'service').'%')
            )
            ->latest('updated_at')
            ->get();
    }

    /**
     * Find a circuit breaker by ID.
     */
    public function findCircuitBreaker(string $id): CircuitBreaker
    {
        return CircuitBreaker::findOrFail($id);
    }

    /**
     * Reset a circuit breaker to closed state.
     */
    public function resetCircuitBreaker(string $id): CircuitBreaker
    {
        $breaker = CircuitBreaker::findOrFail($id);

        $breaker->failure_count = 0;
        $breaker->state = 'closed';
        $breaker->next_attempt_at = null;
        $breaker->save();

        $breaker->clearCache();

        return $breaker;
    }

    /**
     * Test a circuit breaker (manual trigger).
     */
    public function testCircuitBreaker(string $id, string $action = 'success'): CircuitBreaker
    {
        // Validate action
        if (! in_array($action, ['success', 'failure'])) {
            throw new InvalidArgumentException('Action must be either "success" or "failure"');
        }

        $breaker = CircuitBreaker::findOrFail($id);

        if ($action === 'success') {
            $breaker->recordSuccess();
        } else {
            $breaker->recordFailure();
        }

        $breaker->refresh();

        return $breaker;
    }

    /**
     * Get circuit breaker statistics.
     */
    public function getStats(): array
    {
        return [
            'total' => CircuitBreaker::count(),
            'open' => CircuitBreaker::where('state', 'open')->count(),
            'half_open' => CircuitBreaker::where('state', 'half_open')->count(),
            'closed' => CircuitBreaker::where('state', 'closed')->count(),
            'recent_failures' => CircuitBreaker::where('last_failure_at', '>=', now()->subHour())->count(),
        ];
    }

    /**
     * Get circuit breaker history/timeline.
     *
     * Note: This is a simplified version. A full implementation would require
     * a separate state_changes table to track historical state transitions.
     */
    public function getHistory(string $id): array
    {
        $breaker = CircuitBreaker::findOrFail($id);

        // Return the current state with timestamps
        // In a full implementation, this would query a state_changes table
        return [
            [
                'state' => $breaker->state,
                'timestamp' => $breaker->updated_at,
                'failure_count' => $breaker->failure_count,
            ],
        ];
    }
}
