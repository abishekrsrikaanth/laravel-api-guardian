<?php

declare(strict_types=1);

namespace WorkDoneRight\ApiGuardian\Commands;

use Illuminate\Console\Command;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\progress;
use function Laravel\Prompts\warning;

final class ErrorsClearCommand extends Command
{
    protected $signature = 'errors:clear
                            {--days=30 : Clear errors older than N days}
                            {--status=resolved : Clear only errors with this status (resolved, all)}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Clear old errors from the database';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $status = $this->option('status');
        $force = $this->option('force');

        // Build criteria description
        $criteria = [];
        $criteria[] = "older than {$days} days";

        $criteria[] = $status === 'resolved' ? 'resolved errors only' : 'all errors';

        $description = implode(' and ', $criteria);

        info("Preparing to clear errors: {$description}");

        // Count errors to be deleted
        $count = $this->countErrorsToDelete($days, $status);

        if ($count === 0) {
            info('No errors found matching the criteria.');

            return self::SUCCESS;
        }

        warning("Found {$count} error(s) to delete.");

        // Confirm deletion using Laravel Prompts
        if (! $force) {
            $confirmed = confirm(
                label: 'Do you want to proceed?',
                default: false,
                hint: 'This action cannot be undone.'
            );

            if (! $confirmed) {
                info('Operation cancelled.');

                return self::SUCCESS;
            }
        }

        // Delete errors with progress bar
        info('Deleting errors...');

        $errors = $this->getErrorsToDelete($days, $status);

        $deleted = progress(
            label: 'Deleting errors',
            steps: $errors,
            callback: fn ($error) => $error->delete()
        );

        $this->newLine();
        info('✅ Successfully deleted '.count($deleted).' error(s).');

        return self::SUCCESS;
    }

    private function countErrorsToDelete(int $days, string $status): int
    {
        $query = \WorkDoneRight\ApiGuardian\Models\ApiError::query()
            ->where('created_at', '<', now()->subDays($days));

        if ($status === 'resolved') {
            $query->whereNotNull('resolved_at');
        }

        return $query->count();
    }

    private function getErrorsToDelete(int $days, string $status)
    {
        $query = \WorkDoneRight\ApiGuardian\Models\ApiError::query()
            ->where('created_at', '<', now()->subDays($days));

        if ($status === 'resolved') {
            $query->whereNotNull('resolved_at');
        }

        return $query->get();
    }
}
