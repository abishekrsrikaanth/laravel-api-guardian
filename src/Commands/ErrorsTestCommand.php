<?php

declare(strict_types=1);

namespace WorkDoneRight\ApiGuardian\Commands;

use Illuminate\Console\Command;
use Throwable;
use WorkDoneRight\ApiGuardian\Facades\ApiGuardian;
use WorkDoneRight\ApiGuardian\Models\ApiError;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

final class ErrorsTestCommand extends Command
{
    protected $signature = 'errors:test
                            {code? : Error code to test (optional)}
                            {--all : Test all formatters}
                            {--message= : Custom error message}
                            {--log : Test database logging}
                            {--format=jsend : Format to use (jsend, rfc7807, jsonapi)}
                            {--interactive : Interactive mode with prompts}';

    protected $description = 'Test error formatting and logging';

    public function handle(): int
    {
        if ($this->option('interactive')) {
            return $this->handleInteractive();
        }

        $code = $this->argument('code');
        $testAll = $this->option('all');

        if (! $code && ! $testAll) {
            error('Please provide an error code or use --all flag');

            return self::FAILURE;
        }

        if ($testAll) {
            return $this->testAllErrorCodes();
        }

        return $this->testSingleError($code);
    }

    private function handleInteractive(): int
    {
        $action = select(
            label: 'What would you like to test?',
            options: [
                'single' => 'Test a single error code',
                'all' => 'Test all standard error codes',
            ],
            default: 'single'
        );

        if ($action === 'all') {
            return $this->testAllErrorCodes();
        }

        $code = select(
            label: 'Select error code to test',
            options: [
                'BAD_REQUEST' => 'BAD_REQUEST (400)',
                'UNAUTHORIZED' => 'UNAUTHORIZED (401)',
                'FORBIDDEN' => 'FORBIDDEN (403)',
                'NOT_FOUND' => 'NOT_FOUND (404)',
                'VALIDATION_FAILED' => 'VALIDATION_FAILED (422)',
                'RATE_LIMIT_EXCEEDED' => 'RATE_LIMIT_EXCEEDED (429)',
                'SERVER_ERROR' => 'SERVER_ERROR (500)',
            ],
            default: 'NOT_FOUND'
        );

        $message = text(
            label: 'Enter error message',
            placeholder: 'Custom test message',
            default: "Test message for {$code}"
        );

        $format = select(
            label: 'Select formatter',
            options: [
                'jsend' => 'JSend',
                'rfc7807' => 'RFC 7807 (Problem Details)',
                'jsonapi' => 'JSON:API',
            ],
            default: 'jsend'
        );

        $testLogging = confirm(
            label: 'Test database logging?',
            default: false,
            hint: 'Test error will be created and optionally deleted'
        );

        return $this->testSingleError($code, $message, $format, $testLogging);
    }

    private function testSingleError(
        string $code,
        ?string $customMessage = null,
        ?string $customFormat = null,
        ?bool $customTestLogging = null
    ): int {
        $message = $customMessage ?? $this->option('message') ?? 'Test error message';
        $format = $customFormat ?? $this->option('format');
        $testLogging = $customTestLogging ?? $this->option('log');

        info("Testing error code: <fg=cyan>{$code}</>");
        info("Format: <fg=yellow>{$format}</>");
        $this->newLine();

        // Create test exception
        try {
            $exception = $this->createTestException($code, $message);
        } catch (Throwable $e) {
            error("Failed to create exception: {$e->getMessage()}");

            return self::FAILURE;
        }

        // Set formatter
        ApiGuardian::useFormatter($format);

        // Format the error
        $response = ApiGuardian::format($exception);

        // Display response
        $this->displayResponse($response);

        // Test database logging if requested
        if ($testLogging) {
            $this->newLine();
            $this->testDatabaseLogging($exception);
        }

        return self::SUCCESS;
    }

    private function testAllErrorCodes(): int
    {
        info('Testing all standard error codes...');
        $this->newLine();

        $codes = [
            'BAD_REQUEST' => 400,
            'UNAUTHORIZED' => 401,
            'FORBIDDEN' => 403,
            'NOT_FOUND' => 404,
            'VALIDATION_FAILED' => 422,
            'RATE_LIMIT_EXCEEDED' => 429,
            'SERVER_ERROR' => 500,
        ];

        $formatters = ['jsend', 'rfc7807', 'jsonapi'];

        foreach ($codes as $code => $expectedStatus) {
            $this->line("Testing <fg=cyan>{$code}</> (expected status: {$expectedStatus})");

            $exception = $this->createTestException($code, "Test message for {$code}");

            foreach ($formatters as $formatter) {
                ApiGuardian::useFormatter($formatter);
                $response = ApiGuardian::format($exception);

                $statusMatch = $response->getStatusCode() === $expectedStatus ? '✓' : '✗';
                $color = $statusMatch === '✓' ? 'green' : 'red';

                $this->line("  {$formatter}: <fg={$color}>{$statusMatch}</> (status: {$response->getStatusCode()})");
            }

            $this->newLine();
        }

        info('✅ All tests completed');

        return self::SUCCESS;
    }

    private function createTestException(string $code, string $message): Throwable
    {
        return match (mb_strtoupper($code)) {
            'NOT_FOUND', 'RESOURCE_NOT_FOUND' => ApiGuardian::notFound($message),
            'UNAUTHORIZED' => ApiGuardian::unauthorized($message),
            'FORBIDDEN' => ApiGuardian::forbidden($message),
            'VALIDATION_FAILED' => ApiGuardian::validationFailed($message),
            'BAD_REQUEST' => ApiGuardian::badRequest($message),
            'RATE_LIMIT_EXCEEDED' => ApiGuardian::rateLimitExceeded($message),
            'SERVER_ERROR' => ApiGuardian::serverError($message),
            default => ApiGuardian::exception($message)->code($code),
        };
    }

    private function displayResponse($response): void
    {
        info('Response Status: <fg=yellow>'.$response->getStatusCode().'</>');
        $this->newLine();

        $this->line('Response Body:');
        $this->line('<fg=gray>'.str_repeat('─', 60).'</>');

        $data = $response->getData(true);
        $json = json_encode($data, JSON_PRETTY_PRINT);

        // Syntax highlight
        $highlighted = preg_replace_callback(
            '/"([^"]+)"\s*:\s*("[^"]*"|\d+|true|false|null)/',
            fn ($matches): string => "<fg=green>\"{$matches[1]}\"</> : <fg=cyan>{$matches[2]}</>",
            $json
        );

        $this->line($highlighted);
        $this->line('<fg=gray>'.str_repeat('─', 60).'</>');
    }

    private function testDatabaseLogging(Throwable $exception): void
    {
        info('Testing database logging...');

        try {
            $initialCount = ApiError::count();

            // Simulate error logging
            ApiError::createFromException($exception, request());

            $newCount = ApiError::count();

            if ($newCount > $initialCount) {
                $latestError = ApiError::latest()->first();
                info('✅ Error logged successfully');
                $this->line("   Error ID: <fg=cyan>{$latestError->error_id}</>");
                $this->line("   Message: {$latestError->message}");
                $this->line("   Status Code: {$latestError->status_code}");

                // Clean up test error
                $deleteConfirmed = confirm(
                    label: 'Delete test error from database?',
                    default: true,
                    hint: 'Recommended to keep database clean'
                );

                if ($deleteConfirmed) {
                    $latestError->delete();
                    info('Test error deleted');
                }
            } else {
                warning('⚠️  Error logging may have failed');
            }
        } catch (Throwable $e) {
            error("❌ Database logging failed: {$e->getMessage()}");
        }
    }
}
