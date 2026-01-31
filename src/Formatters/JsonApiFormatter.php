<?php

namespace WorkDoneRight\ApiGuardian\Formatters;

use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * JSON:API Formatter
 *
 * Formats errors according to the JSON:API specification.
 * @see https://jsonapi.org/format/#errors
 */
class JsonApiFormatter extends AbstractFormatter
{
    /**
     * Build the error response array.
     */
    public function buildErrorResponse(Throwable $exception, int $statusCode): array
    {
        if ($exception instanceof ValidationException) {
            return [
                'errors' => $this->buildValidationErrorsJsonApi($exception),
            ];
        }

        return [
            'errors' => [
                $this->buildErrorObject($exception, $statusCode),
            ],
        ];
    }

    /**
     * Build a single error object.
     */
    protected function buildErrorObject(Throwable $exception, int $statusCode): array
    {
        $error = [
            'status' => (string) $statusCode,
            'code' => $this->getErrorCode($exception),
            'title' => $this->getTitle($statusCode),
            'detail' => $this->getErrorMessage($exception),
        ];

        // Add error ID
        if (config('api-guardian.context.include_error_id')) {
            $error['id'] = $this->buildContext($exception)['error_id'] ?? null;
        }

        // Add source information for debugging
        if ($this->shouldIncludeDebugInfo()) {
            $error['source'] = [
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ];
        }

        // Add metadata
        $meta = $this->buildMeta($exception);
        if (! empty($meta)) {
            $error['meta'] = $meta;
        }

        // Add links
        $links = $this->buildLinks($exception);
        if (! empty($links)) {
            $error['links'] = $links;
        }

        return $error;
    }

    /**
     * Build validation errors in JSON:API format.
     */
    protected function buildValidationErrorsJsonApi(ValidationException $exception): array
    {
        $errors = [];

        foreach ($exception->errors() as $field => $messages) {
            foreach ($messages as $message) {
                $errors[] = [
                    'status' => '422',
                    'code' => $this->generateValidationCode($field, $message),
                    'title' => 'Validation Error',
                    'detail' => $message,
                    'source' => [
                        'pointer' => '/data/attributes/' . str_replace('.', '/', $field),
                    ],
                ];
            }
        }

        return $errors;
    }

    /**
     * Build meta object.
     */
    protected function buildMeta(Throwable $exception): array
    {
        $meta = [];

        // Add custom metadata
        $customMeta = $this->getMetadata($exception);
        if (! empty($customMeta)) {
            $meta = array_merge($meta, $customMeta);
        }

        // Add timestamp
        if (config('api-guardian.context.include_timestamp')) {
            $meta['timestamp'] = now()->toIso8601String();
        }

        // Add suggestion
        if (config('api-guardian.context.include_suggestions')) {
            $suggestion = $this->getSuggestion($exception);
            if ($suggestion) {
                $meta['suggestion'] = $suggestion;
            }
        }

        // Add debug information
        $debug = $this->buildDebugInfo($exception);
        if (! empty($debug)) {
            $meta['debug'] = $debug;
        }

        return $meta;
    }

    /**
     * Build links object.
     */
    protected function buildLinks(Throwable $exception): array
    {
        $links = [];

        $link = $this->getLink($exception);
        if ($link) {
            $links['about'] = $link;
        }

        return $links;
    }

    /**
     * Get the error title.
     */
    protected function getTitle(int $statusCode): string
    {
        $statusTexts = [
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            422 => 'Unprocessable Entity',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout',
        ];

        return $statusTexts[$statusCode] ?? 'Error';
    }
}
