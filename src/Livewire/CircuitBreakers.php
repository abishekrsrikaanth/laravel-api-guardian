<?php

declare(strict_types=1);

namespace WorkDoneRight\ApiGuardian\Livewire;

use Exception;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use WorkDoneRight\ApiGuardian\Services\CircuitBreakerService;

#[Title('Circuit Breakers')]
final class CircuitBreakers extends Component
{
    #[Url]
    public string $stateFilter = 'all';

    #[Url]
    public string $searchService = '';

    // Polling interval (15 seconds)
    public int $pollInterval = 15000;

    protected CircuitBreakerService $circuitBreakerService;

    public function mount(CircuitBreakerService $circuitBreakerService): void
    {
        $this->circuitBreakerService = $circuitBreakerService;
    }

    /**
     * Get circuit breakers with filters.
     */
    public function getCircuitBreakersProperty()
    {
        $filters = [
            'state' => $this->stateFilter !== 'all' ? $this->stateFilter : null,
            'service' => $this->searchService ?: null,
        ];

        return $this->circuitBreakerService->getCircuitBreakers(array_filter($filters));
    }

    /**
     * Get circuit breaker statistics.
     */
    public function getStatsProperty(): array
    {
        return $this->circuitBreakerService->getStats();
    }

    /**
     * Clear filters.
     */
    public function clearFilters(): void
    {
        $this->reset(['stateFilter', 'searchService']);
    }

    /**
     * Reset a circuit breaker.
     */
    public function reset(string $id): void
    {
        try {
            $this->circuitBreakerService->resetCircuitBreaker($id);
            $this->dispatch('circuit-breaker-reset', id: $id);
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Circuit breaker reset successfully',
            ]);
        } catch (Exception $exception) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Test circuit breaker with success.
     */
    public function testSuccess(string $id): void
    {
        try {
            $this->circuitBreakerService->testCircuitBreaker($id, 'success');
            $this->dispatch('circuit-breaker-tested', id: $id, action: 'success');
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Success recorded for circuit breaker',
            ]);
        } catch (Exception $exception) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Test circuit breaker with failure.
     */
    public function testFailure(string $id): void
    {
        try {
            $this->circuitBreakerService->testCircuitBreaker($id, 'failure');
            $this->dispatch('circuit-breaker-tested', id: $id, action: 'failure');
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Failure recorded for circuit breaker',
            ]);
        } catch (Exception $exception) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Get state badge color.
     */
    public function getStateBadgeColor(string $state): string
    {
        return match ($state) {
            'open' => 'red',
            'half_open' => 'yellow',
            'closed' => 'green',
            default => 'gray',
        };
    }

    public function render()
    {
        return view('api-guardian::livewire.circuit-breakers');
    }
}
