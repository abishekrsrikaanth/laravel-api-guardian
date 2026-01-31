<?php

namespace WorkDoneRight\ApiGuardian\Commands;

use Illuminate\Console\Command;
use WorkDoneRight\ApiGuardian\Facades\ApiGuardian;

class ErrorsTestCommand extends Command
{
    protected $signature = 'errors:test
                            {code : Error code to test}
                            {--format=jsend : Format to use (jsend, rfc7807, jsonapi)}
                            {--message= : Custom error message}';

    protected $description = 'Test error rendering for a specific error code';

    public function handle(): int
    {
        $code = $this->argument('code');
        $format = $this->option('format');
        $message = $this->option('message') ?? 'Test error message';

        $this->info("Testing error code: {$code}");
        $this->info("Format: {$format}");
        $this->line('');

        // Set the formatter
        ApiGuardian::useFormatter($format);

        // Create a test exception
        $exception = $this->createTestException($code, $message);

        // Format the error
        $response = ApiGuardian::format($exception);

        // Display the result
        $this->line('Response Status: ' . $response->getStatusCode());
        $this->line('Response Body:');
        $this->line(json_encode($response->getData(), JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }

    protected function createTestException(string $code, string $message): \Throwable
    {
        return match (strtoupper($code)) {
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
}
