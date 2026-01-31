<?php

namespace WorkDoneRight\ApiGuardian\Formatters;

use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * RFC 7807 Formatter
 *
 * Formats errors according to RFC 7807 (Problem Details for HTTP APIs).
 *
 * @see https://tools.ietf.org/html/rfc7807
 */
class Rfc7807Formatter extends AbstractFormatter
{
    /**
     * Build the error response array.
     */
    public function buildErrorResponse(Throwable $exception, int $statusCode): array
    {
        $response = [
            'type' => $this->getType($exception),
            'title' => $this->getTitle($exception, $statusCode),
            'status' => $statusCode,
            'detail' => $this->getErrorMessage($exception),
        ];

        // Add instance identifier
        if (config('api-guardian.context.include_error_id')) {
            $response['instance'] = $this->buildContext($exception)['error_id'] ?? null;
        }

        // Add validation errors
        if ($exception instanceof ValidationException) {
            $response['errors'] = $this->buildValidationErrors($exception);
        }

        // Add metadata
        $meta = $this->getMetadata($exception);
        if (! empty($meta)) {
            foreach ($meta as $key => $value) {
                $response[$key] = $value;
            }
        }

        // Add suggestion
        if (config('api-guardian.context.include_suggestions')) {
            $suggestion = $this->getSuggestion($exception);
            if ($suggestion) {
                $response['suggestion'] = $suggestion;
            }
        }

        // Add documentation link
        $link = $this->getLink($exception);
        if ($link) {
            $response['help'] = $link;
        }

        // Add debug information
        $debug = $this->buildDebugInfo($exception);
        if (! empty($debug)) {
            $response['debug'] = $debug;
        }

        return $response;
    }

    /**
     * Get the error type URI.
     */
    protected function getType(Throwable $exception): string
    {
        $prefix = config('api-guardian.formats.rfc7807.type_url_prefix', 'https://api.example.com/errors/');
        $code = $this->getErrorCode($exception);

        return $prefix.strtolower(str_replace('_', '-', $code));
    }

    /**
     * Get the error title.
     */
    protected function getTitle(Throwable $exception, int $statusCode): string
    {
        if ($exception instanceof ValidationException) {
            return 'Validation Failed';
        }

        return $this->getStatusText($statusCode);
    }

    /**
     * Get status text from HTTP status code.
     */
    protected function getStatusText(int $statusCode): string
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
