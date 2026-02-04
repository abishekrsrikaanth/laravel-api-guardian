<?php

declare(strict_types=1);

namespace WorkDoneRight\ApiGuardian\Commands;

use Illuminate\Console\Command;
use Throwable;
use WorkDoneRight\ApiGuardian\Models\CircuitBreaker;
use WorkDoneRight\ApiGuardian\Services\CircuitBreakerService;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\progress;
use function Laravel\Prompts\warning;

final class CircuitBreakerResetCommand extends Command
{
    protected $signature = 'circuit-breaker:reset
                            {--id= : Reset specific circuit breaker by ID}
                            {--service= : Reset all breakers for a service}
                            {--state= : Reset all breakers in a specific state (open, half_open)}
                            {--all : Reset all circuit breakers}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Reset circuit breaker(s) to closed state';

    public function __construct(
        private readonly CircuitBreakerService $circuitBreakerService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $id = $this->option('id');
        $service = $this->option('service');
        $state = $this->option('state');
        $all = $this->option('all');
        $force = $this->option('force');

        // Validate options
        if (! $id && ! $service && ! $state && ! $all) {
            error('Please specify --id, --service, --state, or --all');

            return self::FAILURE;
        }

        // Get breakers to reset
        $breakers = $this->getBreakersToReset($id, $service, $state);

        if ($breakers->isEmpty()) {
            warning('No circuit breakers found matching the criteria.');

            return self::SUCCESS;
        }

        $count = $breakers->count();
        info("Found {$count} circuit breaker(s) to reset.");

        // Display breakers
        $this->displayBreakers($breakers);

        // Confirm reset using Laravel Prompts
        if (! $force) {
            $confirmed = confirm(
                label: 'Do you want to reset these circuit breakers?',
                default: true,
                hint: 'This will reset failure/success counts and change state to closed.'
            );

            if (! $confirmed) {
                info('Operation cancelled.');

                return self::SUCCESS;
            }
        }

        // Reset breakers with progress bar
        info('Resetting circuit breakers...');

        $results = progress(
            label: 'Resetting circuit breakers',
            steps: $breakers,
            callback: function ($breaker): bool {
                try {
                    $this->circuitBreakerService->resetCircuitBreaker($breaker->id);

                    return true;
                } catch (Throwable $e) {
                    $this->newLine();
                    error("Failed to reset {$breaker->service}: {$e->getMessage()}");

                    return false;
                }
            }
        );

        $reset = collect($results)->filter()->count();

        $this->newLine();
        info("✅ Successfully reset {$reset} circuit breaker(s).");

        return self::SUCCESS;
    }

    private function getBreakersToReset($id, $service, $state)
    {
        if ($id) {
            $breaker = CircuitBreaker::find($id);

            return $breaker ? collect([$breaker]) : collect();
        }

        $query = CircuitBreaker::query();

        if ($service) {
            $query->where('service', $service);
        }

        if ($state) {
            $query->where('state', $state);
        }

        return $query->get();
    }

    private function displayBreakers($breakers): void
    {
        $this->newLine();

        foreach ($breakers as $breaker) {
            $stateColor = match ($breaker->state) {
                'closed' => 'green',
                'half_open' => 'yellow',
                'open' => 'red',
                default => 'gray',
            };

            $this->line(sprintf(
                '  • %s (<fg=%s>%s</>) - Failures: %d/%d',
                $breaker->service,
                $stateColor,
                mb_strtoupper((string) $breaker->state),
                $breaker->failure_count,
                $breaker->failure_threshold
            ));
        }

        $this->newLine();
    }
}
