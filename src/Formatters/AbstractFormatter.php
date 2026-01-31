<?php

namespace WorkDoneRight\ApiGuardian\Formatters;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;
use WorkDoneRight\ApiGuardian\Contracts\ErrorFormatterContract;
use WorkDoneRight\ApiGuardian\Exceptions\ApiException;
use WorkDoneRight\ApiGuardian\Support\ErrorContext;

abstract class AbstractFormatter implements ErrorFormatterContract
{
    /**
     * Format an exception into a JSON response.
     */
    public function format(Throwable $exception, ?int $statusCode = null): JsonResponse
    {
        $statusCode = $statusCode ?? $this->getStatusCode($exception);
        $response = $this->buildErrorResponse($exception, $statusCode);

        return response()->json($response, $statusCode);
    }

    /**
     * Get the HTTP status code for an exception.
     */
    public function getStatusCode(Throwable $exception): int
    {
        if ($exception instanceof ApiException) {
            return $exception->getStatusCode();
        }

        if ($exception instanceof ValidationException) {
            return 422;
        }

        if ($exception instanceof HttpExceptionInterface) {
            return $exception->getStatusCode();
        }

        return config('api-guardian.status_codes.default', 500);
    }

    /**
     * Get error code from exception.
     */
    protected function getErrorCode(Throwable $exception): string
    {
        if ($exception instanceof ApiException) {
            return $exception->getErrorCode();
        }

        if ($exception instanceof ValidationException) {
            return 'VALIDATION_ERROR';
        }

        // Generate code from exception class name
        $className = class_basename($exception);

        return Str::snake(str_replace('Exception', '', $className), '_');
    }

    /**
     * Get error message from exception.
     */
    protected function getErrorMessage(Throwable $exception): string
    {
        if (! config('app.debug') && config('api-guardian.production.hide_exception_message')) {
            return config('api-guardian.production.generic_message', 'An error occurred.');
        }

        return $exception->getMessage() ?: 'An error occurred.';
    }

    /**
     * Build error context.
     */
    protected function buildContext(Throwable $exception): array
    {
        return ErrorContext::build($exception);
    }

    /**
     * Check if we should include debug information.
     */
    protected function shouldIncludeDebugInfo(): bool
    {
        return config('app.debug') && config('api-guardian.development.enabled', false);
    }

    /**
     * Build debug information.
     */
    protected function buildDebugInfo(Throwable $exception): array
    {
        if (! $this->shouldIncludeDebugInfo()) {
            return [];
        }

        $debug = [
            'exception' => get_class($exception),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ];

        if (config('api-guardian.context.include_trace')) {
            $debug['trace'] = collect($exception->getTrace())
                ->map(function ($trace) {
                    return [
                        'file' => $trace['file'] ?? 'unknown',
                        'line' => $trace['line'] ?? 0,
                        'function' => $trace['function'] ?? 'unknown',
                        'class' => $trace['class'] ?? null,
                    ];
                })
                ->take(10)
                ->toArray();
        }

        if (config('api-guardian.development.include_exception_chain') && $exception->getPrevious()) {
            $debug['previous'] = $this->buildPreviousExceptions($exception->getPrevious());
        }

        return $debug;
    }

    /**
     * Build previous exceptions chain.
     */
    protected function buildPreviousExceptions(Throwable $exception, int $depth = 0): array
    {
        if ($depth > 5) {
            return [];
        }

        $data = [
            'exception' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ];

        if ($previous = $exception->getPrevious()) {
            $data['previous'] = $this->buildPreviousExceptions($previous, $depth + 1);
        }

        return $data;
    }

    /**
     * Get metadata from exception.
     */
    protected function getMetadata(Throwable $exception): array
    {
        if ($exception instanceof ApiException) {
            return $exception->getMeta();
        }

        return [];
    }

    /**
     * Get suggestion from exception.
     */
    protected function getSuggestion(Throwable $exception): ?string
    {
        if ($exception instanceof ApiException) {
            return $exception->getSuggestion();
        }

        return null;
    }

    /**
     * Get documentation link from exception.
     */
    protected function getLink(Throwable $exception): ?string
    {
        if ($exception instanceof ApiException) {
            return $exception->getLink();
        }

        return null;
    }

    /**
     * Build validation errors array.
     */
    protected function buildValidationErrors(ValidationException $exception): array
    {
        $errors = [];

        foreach ($exception->errors() as $field => $messages) {
            $errors[$field] = $this->formatValidationField($field, $messages);
        }

        return $errors;
    }

    /**
     * Format a single validation field error.
     */
    protected function formatValidationField(string $field, array $messages): array|string
    {
        if (! config('api-guardian.validation.include_error_codes')) {
            return $messages[0] ?? 'Validation failed';
        }

        return [
            'message' => $messages[0] ?? 'Validation failed',
            'code' => $this->generateValidationCode($field, $messages[0] ?? ''),
        ];
    }

    /**
     * Generate a validation error code.
     */
    protected function generateValidationCode(string $field, string $message): string
    {
        // Try to extract validation rule from message
        if (str_contains($message, 'required')) {
            return 'FIELD_REQUIRED';
        }
        if (str_contains($message, 'email')) {
            return 'INVALID_EMAIL';
        }
        if (str_contains($message, 'numeric')) {
            return 'MUST_BE_NUMERIC';
        }
        if (str_contains($message, 'string')) {
            return 'MUST_BE_STRING';
        }

        return 'VALIDATION_ERROR';
    }
}
