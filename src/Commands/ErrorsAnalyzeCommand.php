<?php

declare(strict_types=1);

namespace WorkDoneRight\ApiGuardian\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use WorkDoneRight\ApiGuardian\Services\ErrorCollector;

use function Laravel\Prompts\info;
use function Laravel\Prompts\spin;

final class ErrorsAnalyzeCommand extends Command
{
    protected $signature = 'errors:analyze
                            {--days=7 : Number of days to analyze}
                            {--top=10 : Number of top errors to show}
                            {--format=table : Output format (table, json)}';

    protected $description = 'Analyze error patterns and trends';

    public function __construct(
        private readonly ErrorCollector $errorCollector
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $topLimit = (int) $this->option('top');
        $format = $this->option('format');

        info("📊 Analyzing error patterns for the last {$days} days...");
        $this->newLine();

        if ($format === 'json') {
            return $this->displayJson($days, $topLimit);
        }

        // Get analytics data with spinner
        $analytics = spin(
            fn (): array => $this->errorCollector->getAnalytics($days),
            'Fetching analytics...'
        );

        $topErrors = spin(
            fn (): array => $this->errorCollector->getTopErrors($topLimit, $days),
            'Analyzing top errors...'
        );

        $distribution = spin(
            fn (): array => $this->errorCollector->getStatusCodeDistribution($days),
            'Calculating distribution...'
        );

        $trends = spin(
            fn (): array => $this->errorCollector->getTrendData($days, 'day'),
            'Generating trends...'
        );

        // Display summary
        $this->displaySummary($analytics);

        // Display top errors
        $this->newLine();
        $this->displayTopErrors($topErrors);

        // Display status code distribution
        $this->newLine();
        $this->displayDistribution($distribution);

        // Display trends
        $this->newLine();
        $this->displayTrends($trends);

        // Display recommendations
        $this->newLine();
        $this->displayRecommendations($analytics, $topErrors);

        return self::SUCCESS;
    }

    private function displaySummary(array $analytics): void
    {
        $this->info('=== Summary ===');

        $total = Arr::get($analytics, 'total_errors', 0);
        $unique = Arr::get($analytics, 'unique_errors', 0);
        $unresolved = Arr::get($analytics, 'unresolved_count', 0);
        $resolved = Arr::get($analytics, 'resolved_count', 0);
        $resolutionRate = $total > 0 ? round(($resolved / $total) * 100, 1) : 0;

        $this->line("Total Errors: <fg=cyan>{$total}</>");
        $this->line("Unique Errors: <fg=cyan>{$unique}</>");
        $this->line("Unresolved: <fg=red>{$unresolved}</>");
        $this->line("Resolved: <fg=green>{$resolved}</>");
        $this->line("Resolution Rate: <fg=yellow>{$resolutionRate}%</>");

        if ($affectedUsers = Arr::get($analytics, 'affected_users')) {
            $this->line("Affected Users: <fg=magenta>{$affectedUsers}</>");
        }

        if ($avgResponseTime = Arr::get($analytics, 'avg_response_time')) {
            $this->line('Avg Response Time: <fg=blue>'.round($avgResponseTime, 2).'ms</>');
        }
    }

    private function displayTopErrors(array $topErrors): void
    {
        $this->info('=== Top Errors ===');

        if ($topErrors === []) {
            $this->line('No errors found.');

            return;
        }

        $rows = [];
        foreach ($topErrors as $index => $error) {
            $count = Arr::get($error, 'occurrence_count', Arr::get($error, 'count', 0));
            $message = $this->truncate(Arr::get($error, 'message', 'Unknown'), 50);
            $endpoint = Arr::get($error, 'endpoint', 'N/A');

            $rows[] = [
                '#'.($index + 1),
                $message,
                $endpoint,
                "<fg=red>{$count}</>",
            ];
        }

        $this->table(['Rank', 'Error Message', 'Endpoint', 'Count'], $rows);
    }

    private function displayDistribution(array $distribution): void
    {
        $this->info('=== Status Code Distribution ===');

        if ($distribution === []) {
            $this->line('No data available.');

            return;
        }

        $rows = [];
        foreach ($distribution as $item) {
            $statusCode = Arr::get($item, 'status_code');
            $count = Arr::get($item, 'count');
            $percentage = Arr::get($item, 'percentage', 0);

            $color = match (true) {
                $statusCode >= 500 => 'red',
                $statusCode >= 400 => 'yellow',
                default => 'green',
            };

            $rows[] = [
                "<fg={$color}>{$statusCode}</>",
                $count,
                $this->renderBar($percentage, 30),
                "{$percentage}%",
            ];
        }

        $this->table(['Status Code', 'Count', 'Distribution', '%'], $rows);
    }

    private function displayTrends(array $trends): void
    {
        $this->info('=== Error Trends ===');

        if ($trends === []) {
            $this->line('No trend data available.');

            return;
        }

        // Calculate trend direction
        $counts = array_column($trends, 'count');
        if (count($counts) >= 2) {
            $recent = array_slice($counts, -3);
            $previous = array_slice($counts, -6, 3);

            $recentAvg = array_sum($recent) / count($recent);
            $previousAvg = count($previous) > 0 ? array_sum($previous) / count($previous) : 0;

            if ($previousAvg > 0) {
                $change = (($recentAvg - $previousAvg) / $previousAvg) * 100;
                $arrow = $change > 0 ? '↑' : '↓';
                $color = $change > 0 ? 'red' : 'green';

                $this->line("Trend: <fg={$color}>{$arrow} ".abs(round($change, 1)).'% from previous period</>');
            }
        }

        // Show recent periods
        $recentTrends = array_slice($trends, -7);
        foreach ($recentTrends as $trend) {
            $period = Arr::get($trend, 'period');
            $count = Arr::get($trend, 'count');
            $bar = $this->renderBar($count, 20, max($counts));

            $this->line("{$period}: {$bar} ({$count})");
        }
    }

    private function displayRecommendations(array $analytics, array $topErrors): void
    {
        $this->info('=== Recommendations ===');

        $unresolved = Arr::get($analytics, 'unresolved_count', 0);
        $total = Arr::get($analytics, 'total_errors', 0);

        if ($unresolved > 10) {
            $this->warn("🔥 {$unresolved} unresolved errors need attention!");
            $this->line('   Run: php artisan errors:list --status=unresolved');
        }

        if (count($topErrors) > 0) {
            $topError = $topErrors[0];
            $count = Arr::get($topError, 'occurrence_count', Arr::get($topError, 'count', 0));

            if ($count > 5) {
                $message = $this->truncate(Arr::get($topError, 'message', ''), 60);
                $this->warn("🎯 Most frequent error: \"{$message}\" ({$count} times)");
                $this->line('   Consider investigating this error first.');
            }
        }

        $resolutionRate = $total > 0 ? (Arr::get($analytics, 'resolved_count', 0) / $total) * 100 : 0;
        if ($resolutionRate < 50) {
            $this->warn("⚠️  Low resolution rate ({$resolutionRate}%). Consider resolving old errors.");
            $this->line('   Run: php artisan errors:clear --days=30 --status=resolved');
        }

        if ($unresolved === 0 && $total > 0) {
            $this->info('✅ All errors are resolved! Great job!');
        }
    }

    private function displayJson(int $days, int $topLimit): int
    {
        $data = [
            'period' => "{$days} days",
            'analytics' => $this->errorCollector->getAnalytics($days),
            'top_errors' => $this->errorCollector->getTopErrors($topLimit, $days),
            'distribution' => $this->errorCollector->getStatusCodeDistribution($days),
            'trends' => $this->errorCollector->getTrendData($days, 'day'),
        ];

        $this->line(json_encode($data, JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }

    private function renderBar(float $value, int $maxWidth, ?float $maxValue = null): string
    {
        $maxValue ??= 100;
        $filled = (int) round(($value / $maxValue) * $maxWidth);
        $empty = $maxWidth - $filled;

        return '<fg=cyan>'.str_repeat('█', $filled).'</><fg=gray>'.str_repeat('░', $empty).'</>';
    }

    private function truncate(string $text, int $length): string
    {
        return mb_strlen($text) > $length ? mb_substr($text, 0, $length).'...' : $text;
    }
}
