<?php

declare(strict_types=1);

namespace WorkDoneRight\ApiGuardian\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use WorkDoneRight\ApiGuardian\Services\CircuitBreakerService;

final class CircuitBreakerListCommand extends Command
{
    protected $signature = 'circuit-breaker:list
                            {--state= : Filter by state (open, half_open, closed)}
                            {--service= : Filter by service name}
                            {--format=table : Output format (table, json, compact)}';

    protected $description = 'List all circuit breakers and their states';

    public function __construct(
        private readonly CircuitBreakerService $circuitBreakerService
    )
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $filters = [];

        if ($state = $this->option('state')) {
            $filters['state'] = $state;
        }

        if ($service = $this->option('service')) {
            $filters['service'] = $service;
        }

        $breakers = $this->circuitBreakerService->getCircuitBreakers($filters);

        if ($breakers->isEmpty()) {
            $this->warn('No circuit breakers found matching the criteria.');

            return self::SUCCESS;
        }

        $format = $this->option('format');

        match ($format) {
            'json' => $this->displayJson($breakers),
            'compact' => $this->displayCompact($breakers),
            default => $this->displayTable($breakers),
        };

        $this->displaySummary($breakers);

        return self::SUCCESS;
    }

    private function displayJson(Collection $breakers): void
    {
        $data = $breakers->map(fn($breaker): array => [
            'id' => $breaker->id,
            'identifier' => $breaker->identifier,
            'service' => $breaker->service,
            'state' => $breaker->state,
            'failure_count' => $breaker->failure_count,
            'failure_threshold' => $breaker->failure_threshold,
            'success_count' => $breaker->success_count,
            'success_threshold' => $breaker->success_threshold,
            'last_failure_at' => $breaker->last_failure_at?->toIso8601String(),
            'next_attempt_at' => $breaker->next_attempt_at?->toIso8601String(),
        ]);

        $this->line(json_encode($data, JSON_PRETTY_PRINT));
    }

    private function displayCompact(Collection $breakers): void
    {
        foreach ($breakers as $breaker) {
            $stateIcon = match ($breaker->state) {
                'closed' => '<fg=green>✓</>',
                'half_open' => '<fg=yellow>◐</>',
                'open' => '<fg=red>✗</>',
                default => '<fg=gray>?</>',
            };

            $this->line(sprintf(
                '%s %s (%s) - F:%d/%d S:%d/%d',
                $stateIcon,
                $breaker->service,
                $breaker->state,
                $breaker->failure_count,
                $breaker->failure_threshold,
                $breaker->success_count,
                $breaker->success_threshold
            ));
        }
    }

    private function displayTable(Collection $breakers): void
    {
        $rows = [];
        
        foreach ($breakers as $breaker) {
            $stateColor = match ($breaker->state) {
                'closed' => 'green',
                'half_open' => 'yellow',
                'open' => 'red',
                default => 'gray',
            };

            $rows[] = [
                mb_substr((string)$breaker->identifier, 0, 20),
                $breaker->service,
                "<fg={$stateColor}>" . mb_strtoupper((string)$breaker->state) . '</>',
                "{$breaker->failure_count}/{$breaker->failure_threshold}",
                "{$breaker->success_count}/{$breaker->success_threshold}",
                $breaker->last_failure_at?->diffForHumans() ?? 'Never',
                $breaker->next_attempt_at?->diffForHumans() ?? 'N/A',
            ];
        }

        $this->table(
            ['Identifier', 'Service', 'State', 'Failures', 'Successes', 'Last Failure', 'Next Attempt'],
            $rows
        );
    }

    private function displaySummary(Collection $breakers): void
    {
        $total = $breakers->count();
        $closed = $breakers->where('state', 'closed')->count();
        $halfOpen = $breakers->where('state', 'half_open')->count();
        $open = $breakers->where('state', 'open')->count();

        $this->newLine();
        $this->info("Total: {$total} | Closed: {$closed} | Half-Open: {$halfOpen} | Open: {$open}");

        if ($open > 0) {
            $this->newLine();
            $this->warn("⚠️  {$open} circuit breaker(s) are currently OPEN!");
            $this->line('   Run `php artisan circuit-breaker:reset --state=open` to reset them.');
        }
    }
}
