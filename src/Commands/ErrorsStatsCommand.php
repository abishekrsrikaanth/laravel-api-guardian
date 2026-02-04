<?php

declare(strict_types=1);

namespace WorkDoneRight\ApiGuardian\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use WorkDoneRight\ApiGuardian\Services\CircuitBreakerService;
use WorkDoneRight\ApiGuardian\Services\ErrorCollector;

final class ErrorsStatsCommand extends Command
{
    protected $signature = 'errors:stats
                            {--days=7 : Number of days to analyze}
                            {--json : Output as JSON}';

    protected $description = 'Display quick error statistics';

    public function __construct(
        private readonly ErrorCollector $errorCollector,
        private readonly CircuitBreakerService $circuitBreakerService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $json = $this->option('json');

        $analytics = $this->errorCollector->getAnalytics($days);
        $breakerStats = $this->circuitBreakerService->getStats();

        if ($json) {
            $this->line(json_encode([
                'period_days' => $days,
                'errors' => $analytics,
                'circuit_breakers' => $breakerStats,
            ], JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->displayStats($days, $analytics, $breakerStats);

        return self::SUCCESS;
    }

    private function displayStats(int $days, array $analytics, array $breakerStats): void
    {
        $this->info("📊 API Guardian Statistics (Last {$days} Days)");
        $this->newLine();

        // Error Stats
        $this->line('<fg=yellow>╔═══════════════════════════════════════╗</>');
        $this->line('<fg=yellow>║</> <fg=white;options=bold>ERRORS</>                               <fg=yellow>║</>');
        $this->line('<fg=yellow>╠═══════════════════════════════════════╣</>');

        $total = Arr::get($analytics, 'total_errors', 0);
        $unique = Arr::get($analytics, 'unique_errors', 0);
        $unresolved = Arr::get($analytics, 'unresolved_count', 0);
        $resolved = Arr::get($analytics, 'resolved_count', 0);
        $resolutionRate = $total > 0 ? round(($resolved / $total) * 100, 1) : 0;

        $this->printStatLine('Total Errors', $total, 'cyan');
        $this->printStatLine('Unique Errors', $unique, 'blue');
        $this->printStatLine('Unresolved', $unresolved, $unresolved > 0 ? 'red' : 'green');
        $this->printStatLine('Resolved', $resolved, 'green');
        $this->printStatLine('Resolution Rate', "{$resolutionRate}%", $resolutionRate > 80 ? 'green' : 'yellow');

        if ($affectedUsers = Arr::get($analytics, 'affected_users')) {
            $this->printStatLine('Affected Users', $affectedUsers, 'magenta');
        }

        if ($avgResponseTime = Arr::get($analytics, 'avg_response_time')) {
            $this->printStatLine('Avg Response Time', round($avgResponseTime, 2).'ms', 'blue');
        }

        $this->line('<fg=yellow>╚═══════════════════════════════════════╝</>');
        $this->newLine();

        // Circuit Breaker Stats
        $this->line('<fg=yellow>╔═══════════════════════════════════════╗</>');
        $this->line('<fg=yellow>║</> <fg=white;options=bold>CIRCUIT BREAKERS</>                     <fg=yellow>║</>');
        $this->line('<fg=yellow>╠═══════════════════════════════════════╣</>');

        $totalBreakers = Arr::get($breakerStats, 'total', 0);
        $closed = Arr::get($breakerStats, 'closed', 0);
        $halfOpen = Arr::get($breakerStats, 'half_open', 0);
        $open = Arr::get($breakerStats, 'open', 0);

        $this->printStatLine('Total Breakers', $totalBreakers, 'cyan');
        $this->printStatLine('Closed (Healthy)', $closed, 'green');
        $this->printStatLine('Half Open', $halfOpen, 'yellow');
        $this->printStatLine('Open (Failing)', $open, $open > 0 ? 'red' : 'green');

        $this->line('<fg=yellow>╚═══════════════════════════════════════╝</>');
        $this->newLine();

        // Health Summary
        $this->displayHealthSummary($unresolved, $open);
    }

    private function printStatLine(string $label, $value, string $color = 'white'): void
    {
        $padding = 30 - mb_strlen($label);
        $this->line(sprintf(
            '<fg=yellow>║</> %s%s <fg=%s>%s</> <fg=yellow>║</>',
            $label,
            str_repeat(' ', $padding),
            $color,
            mb_str_pad((string) $value, 7, ' ', STR_PAD_LEFT)
        ));
    }

    private function displayHealthSummary(int $unresolved, int $openBreakers): void
    {
        if ($unresolved === 0 && $openBreakers === 0) {
            $this->info('✅ System Health: EXCELLENT');
            $this->line('   No unresolved errors or open circuit breakers.');
        } elseif ($unresolved <= 5 && $openBreakers === 0) {
            $this->line('<fg=yellow>⚠️  System Health: GOOD</>');
            $this->line("   {$unresolved} unresolved error(s) detected.");
        } elseif ($unresolved <= 10 || $openBreakers <= 2) {
            $this->line('<fg=yellow>⚠️  System Health: WARNING</>');
            $this->line("   {$unresolved} unresolved error(s) and {$openBreakers} open breaker(s).");
            $this->line('   Consider investigating these issues.');
        } else {
            $this->error('❌ System Health: CRITICAL');
            $this->line("   {$unresolved} unresolved error(s) and {$openBreakers} open breaker(s)!");
            $this->line('   Immediate attention required.');
        }

        $this->newLine();
        $this->line('<fg=gray>Run `php artisan errors:analyze` for detailed analysis.</>');
    }
}
