<?php

declare(strict_types=1);

namespace WorkDoneRight\ApiGuardian\Commands;

use Illuminate\Console\Command;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use WorkDoneRight\ApiGuardian\Services\ErrorService;

use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

final class ErrorsExportCommand extends Command
{
    protected $signature = 'errors:export
                            {--format=csv : Export format (csv, json)}
                            {--days=7 : Limit to errors from last N days}
                            {--status= : Filter by status (unresolved, resolved)}
                            {--output= : Output file path}
                            {--interactive : Interactive mode with prompts}';

    protected $description = 'Export errors to CSV or JSON';

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

        $format = $this->option('format');
        $days = (int) $this->option('days');
        $status = $this->option('status');
        $output = $this->option('output');

        return $this->performExport($format, $days, $status, $output);
    }

    private function handleInteractive(): int
    {
        $format = select(
            label: 'Select export format',
            options: [
                'csv' => 'CSV (spreadsheet)',
                'json' => 'JSON (structured data)',
            ],
            default: 'csv'
        );

        $days = text(
            label: 'Export errors from last N days (leave blank for all)',
            placeholder: 'e.g. 7, 30',
            default: '7',
            validate: fn ($value): ?string => $value && ! is_numeric($value)
                ? 'Days must be a number'
                : null
        );

        $status = select(
            label: 'Filter by status',
            options: [
                '' => 'All errors',
                'unresolved' => 'Unresolved only',
                'resolved' => 'Resolved only',
            ]
        );

        $output = text(
            label: 'Output file path (leave blank for auto-generated)',
            placeholder: 'e.g. /path/to/export.csv'
        );

        return $this->performExport($format, (int) $days, $status ?: null, $output ?: null);
    }

    private function performExport(string $format, int $days, ?string $status, ?string $output): int
    {
        // Build filters
        $filters = [];
        if ($status) {
            $filters['status'] = $status;
        }

        if ($days !== 0) {
            $filters['from_date'] = now()->subDays($days)->toDateString();
        }

        // Get errors with spinner
        $errors = spin(
            fn (): LengthAwarePaginator => $this->errorService->getErrors($filters, 10000),
            'Fetching errors for export...'
        );

        if ($errors->isEmpty()) {
            warning('No errors found matching the criteria.');

            return self::SUCCESS;
        }

        info(sprintf('Found %d error(s) to export.', $errors->count()));

        // Determine output path
        if (! $output) {
            $timestamp = now()->format('Y-m-d_His');
            $output = storage_path(sprintf('app/errors_export_%s.%s', $timestamp, $format));
        }

        // Ensure directory exists
        $directory = dirname($output);
        if (! File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        // Export with spinner
        spin(
            fn () => match ($format) {
                'csv' => $this->exportCsv($errors, $output),
                'json' => $this->exportJson($errors, $output),
                default => throw new InvalidArgumentException('Unsupported format: '.$format),
            },
            sprintf('Exporting to %s...', $format)
        );

        info('✅ Export completed successfully!');
        $this->line(sprintf('   File: <fg=cyan>%s</>', $output));
        $this->line('   Size: '.File::size($output).' bytes');

        return self::SUCCESS;
    }

    private function exportCsv($errors, string $output): void
    {
        $handle = fopen($output, 'w');

        // Write headers
        fputcsv($handle, [
            'Error ID',
            'Message',
            'Status Code',
            'Method',
            'URL',
            'Occurrence Count',
            'Resolved',
            'Created At',
            'Updated At',
        ]);

        // Write rows
        foreach ($errors as $error) {
            fputcsv($handle, [
                $error->error_id,
                $error->message,
                $error->status_code,
                $error->request_method,
                $error->request_url,
                $error->occurrence_count,
                $error->resolved_at ? 'Yes' : 'No',
                $error->created_at->toIso8601String(),
                $error->updated_at->toIso8601String(),
            ]);
        }

        fclose($handle);
    }

    private function exportJson($errors, string $output): void
    {
        $data = $errors->map(fn ($error): array => [
            'error_id' => $error->error_id,
            'message' => $error->message,
            'exception_class' => $error->exception_class,
            'status_code' => $error->status_code,
            'request_method' => $error->request_method,
            'request_url' => $error->request_url,
            'occurrence_count' => $error->occurrence_count,
            'resolved' => ! is_null($error->resolved_at),
            'resolved_at' => $error->resolved_at?->toIso8601String(),
            'created_at' => $error->created_at->toIso8601String(),
            'updated_at' => $error->updated_at->toIso8601String(),
        ]);

        File::put($output, json_encode($data, JSON_PRETTY_PRINT));
    }
}
