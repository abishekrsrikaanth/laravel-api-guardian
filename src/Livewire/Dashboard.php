<?php

declare(strict_types=1);

namespace WorkDoneRight\ApiGuardian\Livewire;

use Livewire\Attributes\Title;
use Livewire\Component;
use WorkDoneRight\ApiGuardian\Services\CircuitBreakerService;
use WorkDoneRight\ApiGuardian\Services\ErrorCollector;
use WorkDoneRight\ApiGuardian\Services\ErrorService;

#[Title('API Guardian Dashboard')]
final class Dashboard extends Component
{
    // Polling interval in milliseconds (5 seconds)
    public int $pollInterval = 5000;

    protected ErrorService $errorService;

    protected ErrorCollector $errorCollector;

    protected CircuitBreakerService $circuitBreakerService;

    public function mount(
        ErrorService $errorService,
        ErrorCollector $errorCollector,
        CircuitBreakerService $circuitBreakerService
    ): void {
        $this->errorService = $errorService;
        $this->errorCollector = $errorCollector;
        $this->circuitBreakerService = $circuitBreakerService;
    }

    /**
     * Get dashboard statistics.
     *
     * @return array<string, mixed[]>
     */
    public function getStatsProperty(): array
    {
        return [
            'errors' => $this->errorService->getStats(),
            'circuit_breakers' => $this->circuitBreakerService->getStats(),
            'analytics' => $this->errorCollector->getAnalytics(7),
        ];
    }

    /**
     * Get recent errors.
     */
    public function getRecentErrorsProperty()
    {
        return $this->errorService->getErrors([], 10);
    }

    /**
     * Get top errors.
     */
    public function getTopErrorsProperty(): array
    {
        return $this->errorCollector->getTopErrors(5, 7);
    }

    /**
     * Get trend data for chart.
     */
    public function getTrendsProperty(): array
    {
        return $this->errorCollector->getTrendData(7, 'day');
    }

    /**
     * Refresh dashboard data.
     */
    public function refresh(): void
    {
        // Livewire will automatically re-render
        $this->dispatch('dashboard-refreshed');
    }

    public function render()
    {
        return view('api-guardian::livewire.dashboard');
    }
}
