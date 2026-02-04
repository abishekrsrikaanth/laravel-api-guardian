<?php

declare(strict_types=1);

namespace WorkDoneRight\ApiGuardian\Formatters;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;
use WorkDoneRight\ApiGuardian\Concerns\Config\HandlesContextConfig;
use WorkDoneRight\ApiGuardian\Concerns\Config\HandlesDevelopmentConfig;
use WorkDoneRight\ApiGuardian\Concerns\Config\HandlesProductionConfig;
use WorkDoneRight\ApiGuardian\Concerns\Config\HandlesSecurityConfig;
use WorkDoneRight\ApiGuardian\Contracts\ErrorFormatterContract;
use WorkDoneRight\ApiGuardian\Exceptions\ApiException;
use WorkDoneRight\ApiGuardian\Support\DataMasker;
use WorkDoneRight\ApiGuardian\Support\ErrorContext;
use WorkDoneRight\ApiGuardian\Support\PIIRedactor;

abstract class AbstractFormatter implements ErrorFormatterContract
{
    use HandlesContextConfig;
    use HandlesDevelopmentConfig;
    use HandlesProductionConfig;
    use HandlesSecurityConfig;

    /**
     * Create a new formatter instance.
     */
    public function __construct(
        protected DataMasker $dataMasker,
        protected PIIRedactor $piiRedactor
    ) {}

    /**
     * Format an exception into a JSON response.
     */
    final public function format(Throwable $exception, ?int $statusCode = null): JsonResponse
    {
        $statusCode ??= $this->getStatusCode($exception);
        $response = $this->buildErrorResponse($exception, $statusCode);

        // Apply security sanitization
        $response = $this->applySecurity($response);

        return response()->json($response, $statusCode);
    }

    /**
     * Get the HTTP status code for an exception.
     */
    final public function getStatusCode(Throwable $exception): int
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
     * Apply security measures to the response
     */
    protected function applySecurity(array $response): array
    {
        // Apply sensitive data masking
        if ($this->shouldMaskSensitiveData()) {
            $response = $this->dataMasker->maskArray($response);
        }

        // Apply PII redaction
        if ($this->isPiiRedactionEnabled()) {
            return $this->piiRedactor->redactArray($response);
        }

        return $response;
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

        // Generate code from an exception class name
        $className = class_basename($exception);

        return Str::snake(str_replace('Exception', '', $className), '_');
    }

    /**
     * Get error message from exception.
     */
    protected function getErrorMessage(Throwable $exception): string
    {
        $message = $exception->getMessage() ?: 'An error occurred.';

        // Apply PII redaction to message
        if ($this->isPiiRedactionEnabled()) {
            $message = $this->piiRedactor->redact($message);
        }

        // Hide message in production if configured
        if ($this->shouldHideExceptionMessage()) {
            return $this->getGenericErrorMessage();
        }

        return $message;
    }

    /**
     * Build error context.
     */
    protected function buildContext(Throwable $exception): array
    {
        return ErrorContext::build();
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
            'exception' => $exception::class,
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ];

        if ($this->shouldIncludeTraceInfo()) {
            $trace = collect($exception->getTrace())
                ->map(function ($trace) {
                    $item = [
                        'file' => Arr::get($trace, 'file', 'unknown'),
                        'line' => Arr::get($trace, 'line', 0),
                        'function' => Arr::get($trace, 'function', 'unknown'),
                        'class' => Arr::get($trace, 'class'),
                    ];

                    // Mask sensitive data in function arguments if present
                    if (Arr::has($trace, 'args')) {
                        return Arr::set($item, 'args', $this->dataMasker->maskArray(Arr::get($trace, 'args')));
                    }

                    return $item;
                })
                ->take(10)
                ->toArray();

            $debug = Arr::set($debug, 'trace', $trace);
        }

        if ($this->shouldIncludeExceptionChain() && $exception->getPrevious()) {
            return Arr::set($debug, 'previous', $this->buildPreviousExceptions($exception->getPrevious()));
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

        $message = $exception->getMessage();

        // Redact PII from exception messages
        if ($this->isPiiRedactionEnabled()) {
            $message = $this->piiRedactor->redact($message);
        }

        $data = [
            'exception' => $exception::class,
            'message' => $message,
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ];

        if (($previous = $exception->getPrevious()) instanceof Throwable) {
            return Arr::set($data, 'previous', $this->buildPreviousExceptions($previous, $depth + 1));
        }

        return $data;
    }

    /**
     * Get metadata from exception.
     */
    protected function getMetadata(Throwable $exception): array
    {
        if ($exception instanceof ApiException) {
            $meta = $exception->getMeta();

            // Mask sensitive data in metadata
            if ($this->shouldMaskSensitiveData()) {
                return $this->dataMasker->maskArray($meta);
            }

            return $meta;
        }

        return [];
    }

    /**
     * Get suggestion from exception.
     */
    protected function getSuggestion(Throwable $exception): ?string
    {
        if ($exception instanceof ApiException) {
            $suggestion = $exception->getSuggestion();

            // Redact PII from suggestions
            if ($suggestion && $this->isPiiRedactionEnabled()) {
                return $this->piiRedactor->redact($suggestion);
            }

            return $suggestion;
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
        // Redact PII from validation messages
        if ($this->isPiiRedactionEnabled()) {
            $messages = array_map($this->piiRedactor->redact(...), $messages);
        }

        if (! $this->shouldIncludeErrorCodes()) {
            return Arr::first($messages, default: 'Validation failed');
        }

        return [
            'message' => Arr::first($messages, default: 'Validation failed'),
            'code' => $this->generateValidationCode($field, Arr::first($messages, default: '')),
        ];
    }

    /**
     * Generate a validation error code.
     */
    protected function generateValidationCode(string $field, string $message): string
    {
        // Try to extract validation rule from a message
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
