<?php

declare(strict_types=1);

namespace WorkDoneRight\ApiGuardian\Livewire;

use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use WorkDoneRight\ApiGuardian\Services\ErrorCollector;

#[Title('Analytics')]
final class Analytics extends Component
{
    #[Url]
    public int $days = 7;

    public string $groupBy = 'day';

    public int $topErrorsLimit = 10;

    protected ErrorCollector $errorCollector;

    public function mount(ErrorCollector $errorCollector): void
    {
        $this->errorCollector = $errorCollector;
    }

    /**
     * Get analytics data.
     */
    public function getAnalyticsProperty(): array
    {
        return $this->errorCollector->getAnalytics($this->days);
    }

    /**
     * Get trend data for charts.
     */
    public function getTrendsProperty(): array
    {
        return $this->errorCollector->getTrendData($this->days, $this->groupBy);
    }

    /**
     * Get top errors.
     */
    public function getTopErrorsProperty(): array
    {
        return $this->errorCollector->getTopErrors($this->topErrorsLimit, $this->days);
    }

    /**
     * Get status code distribution.
     */
    public function getDistributionProperty(): array
    {
        return $this->errorCollector->getStatusCodeDistribution($this->days);
    }

    /**
     * Get error rate.
     */
    public function getErrorRateProperty(): array
    {
        $interval = $this->days <= 1 ? 'hour' : 'day';

        return $this->errorCollector->getErrorRate($this->days, $interval);
    }

    /**
     * Set time period.
     */
    public function setPeriod(int $days): void
    {
        $this->days = $days;

        // Adjust groupBy based on days
        if ($days <= 1) {
            $this->groupBy = 'hour';
        } elseif ($days <= 7) {
            $this->groupBy = 'day';
        } elseif ($days <= 30) {
            $this->groupBy = 'day';
        } else {
            $this->groupBy = 'week';
        }

        $this->dispatch('period-changed', days: $days);
    }

    /**
     * Export data.
     */
    public function export(string $format = 'json', string $type = 'errors'): void
    {
        $url = route('api-guardian.api.analytics.export', [
            'format' => $format,
            'type' => $type,
            'days' => $this->days,
        ]);

        $this->dispatch('download-file', url: $url);
    }

    public function render()
    {
        return view('api-guardian::livewire.analytics');
    }
}
