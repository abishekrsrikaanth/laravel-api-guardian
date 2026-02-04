<?php

declare(strict_types=1);

namespace WorkDoneRight\ApiGuardian\Commands;

use Illuminate\Console\Command;
use WorkDoneRight\ApiGuardian\Services\ErrorService;

use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

final class ErrorsListCommand extends Command
{
    protected $signature = 'errors:list
                            {--status=all : Filter by status (all, unresolved, resolved)}
                            {--status-code= : Filter by HTTP status code}
                            {--days= : Limit to errors from last N days}
                            {--limit=25 : Maximum number of errors to display}
                            {--sort-by=updated_at : Sort by field (updated_at, occurrence_count, created_at)}
                            {--sort-dir=desc : Sort direction (asc, desc)}
                            {--format=table : Output format (table, json, compact)}
                            {--interactive : Interactive mode with prompts}';

    protected $description = 'List errors from the database with filtering and sorting options';

    public function __construct(
        private readonly ErrorService $errorService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if ($this->option('interactive')) {
            return $this->handleInteractive();
        }

        $filters = $this->buildFilters();
        $perPage = (int) $this->option('limit');

        info('Fetching errors...');

        $errors = $this->errorService->getErrors($filters, $perPage);

        if ($errors->isEmpty()) {
            warning('No errors found matching the criteria.');

            return self::SUCCESS;
        }

        $format = $this->option('format');

        match ($format) {
            'json' => $this->displayJson($errors),
            'compact' => $this->displayCompact($errors),
            default => $this->displayTable($errors),
        };

        $this->displaySummary($errors);

        return self::SUCCESS;
    }

    private function handleInteractive(): int
    {
        $status = select(
            label: 'Filter by status',
            options: [
                'all' => 'All Errors',
                'unresolved' => 'Unresolved Only',
                'resolved' => 'Resolved Only',
            ],
            default: 'all'
        );

        $statusCode = text(
            label: 'Filter by status code (leave blank for all)',
            placeholder: 'e.g. 404, 422, 500',
            validate: fn ($value): ?string => $value && ! is_numeric($value)
                ? 'Status code must be a number'
                : null
        );

        $days = text(
            label: 'Show errors from last N days (leave blank for all)',
            placeholder: 'e.g. 7, 30',
            validate: fn ($value): ?string => $value && ! is_numeric($value)
                ? 'Days must be a number'
                : null
        );

        $limit = text(
            label: 'Maximum number of errors',
            default: '25',
            validate: fn ($value): ?string => ! is_numeric($value) || $value < 1
                ? 'Limit must be a positive number'
                : null
        );

        $format = select(
            label: 'Output format',
            options: [
                'table' => 'Table (formatted)',
                'compact' => 'Compact (one line per error)',
                'json' => 'JSON (machine readable)',
            ],
            default: 'table'
        );

        // Build filters
        $filters = [];
        if ($status !== 'all') {
            $filters['status'] = $status;
        }
        if ($statusCode !== '' && $statusCode !== '0') {
            $filters['status_code'] = (int) $statusCode;
        }
        if ($days !== '' && $days !== '0') {
            $filters['from_date'] = now()->subDays((int) $days)->toDateString();
        }

        info('Fetching errors...');

        $errors = $this->errorService->getErrors($filters, (int) $limit);

        if ($errors->isEmpty()) {
            warning('No errors found matching the criteria.');

            return self::SUCCESS;
        }

        match ($format) {
            'json' => $this->displayJson($errors),
            'compact' => $this->displayCompact($errors),
            default => $this->displayTable($errors),
        };

        $this->displaySummary($errors);

        return self::SUCCESS;
    }

    private function buildFilters(): array
    {
        $filters = [];

        // Status filter
        $status = $this->option('status');
        if ($status !== 'all') {
            $filters['status'] = $status;
        }

        // Status code filter
        if ($statusCode = $this->option('status-code')) {
            $filters['status_code'] = (int) $statusCode;
        }

        // Days filter
        if ($days = $this->option('days')) {
            $filters['from_date'] = now()->subDays((int) $days)->toDateString();
        }

        return $filters;
    }

    private function displayTable(\Illuminate\Pagination\LengthAwarePaginator $errors): void
    {
        $rows = [];

        foreach ($errors as $error) {
            $rows[] = [
                mb_substr((string) $error->error_id, 0, 12).'...',
                $this->truncate($error->message, 40),
                $error->status_code,
                $error->occurrence_count,
                $error->updated_at->diffForHumans(),
                $error->resolved_at ? '✓ Resolved' : 'Open',
            ];
        }

        table(
            headers: ['Error ID', 'Message', 'Status', 'Count', 'Last Seen', 'Status'],
            rows: $rows
        );
    }

    private function displayCompact(\Illuminate\Pagination\LengthAwarePaginator $errors): void
    {
        foreach ($errors as $error) {
            $status = $error->resolved_at ? '✓' : '✗';
            $this->line(sprintf(
                '%s [%d] %s (×%d) - %s',
                $status,
                $error->status_code,
                $this->truncate($error->message, 60),
                $error->occurrence_count,
                $error->updated_at->diffForHumans()
            ));
        }
    }

    private function displayJson(\Illuminate\Pagination\LengthAwarePaginator $errors): void
    {
        $data = $errors->map(fn ($error): array => [
            'error_id' => $error->error_id,
            'message' => $error->message,
            'status_code' => $error->status_code,
            'occurrence_count' => $error->occurrence_count,
            'resolved' => ! is_null($error->resolved_at),
            'last_seen' => $error->updated_at->toIso8601String(),
            'endpoint' => $error->request_url,
        ]);

        $this->line(json_encode($data, JSON_PRETTY_PRINT));
    }

    private function displaySummary(\Illuminate\Pagination\LengthAwarePaginator $errors): void
    {
        $total = $errors->count();
        $resolved = $errors->where('resolved_at', '!=')->count();
        $unresolved = $total - $resolved;

        $this->newLine();
        info("Total: {$total} | Resolved: {$resolved} | Unresolved: {$unresolved}");

        // Top status code
        $topStatusCode = $errors->groupBy('status_code')
            ->sortByDesc(fn ($group): int => $group->count())
            ->keys()
            ->first();

        if ($topStatusCode) {
            $this->line("Most common status code: {$topStatusCode}");
        }
    }

    private function truncate(string $text, int $length): string
    {
        return mb_strlen($text) > $length ? mb_substr($text, 0, $length).'...' : $text;
    }
}
