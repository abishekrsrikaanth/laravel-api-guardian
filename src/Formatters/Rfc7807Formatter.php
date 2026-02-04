<?php

declare(strict_types=1);

namespace WorkDoneRight\ApiGuardian\Formatters;

use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * RFC 7807 Formatter
 *
 * Formats errors according to RFC 7807 (Problem Details for HTTP APIs).
 *
 * @see https://tools.ietf.org/html/rfc7807
 */
final class Rfc7807Formatter extends AbstractFormatter
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

        // Add instance identifier using trait method
        if ($this->shouldIncludeErrorId()) {
            $response = Arr::set($response, 'instance', Arr::get($this->buildContext($exception), 'error_id'));
        }

        // Add validation errors
        if ($exception instanceof ValidationException) {
            $response = Arr::set($response, 'errors', $this->buildValidationErrors($exception));
        }

        // Add metadata
        $meta = $this->getMetadata($exception);
        foreach ($meta as $key => $value) {
            $response = Arr::set($response, $key, $value);
        }

        // Add suggestion using trait method
        if ($this->shouldIncludeSuggestions()) {
            $suggestion = $this->getSuggestion($exception);
            if ($suggestion) {
                $response = Arr::set($response, 'suggestion', $suggestion);
            }
        }

        // Add documentation link
        $link = $this->getLink($exception);
        if ($link) {
            $response = Arr::set($response, 'help', $link);
        }

        // Add debug information
        $debug = $this->buildDebugInfo($exception);
        if ($debug !== []) {
            return Arr::set($response, 'debug', $debug);
        }

        return $response;
    }

    /**
     * Get the error type URI.
     */
    private function getType(Throwable $exception): string
    {
        $prefix = config('api-guardian.formats.rfc7807.type_url_prefix', 'https://api.example.com/errors/');
        $code = $this->getErrorCode($exception);

        return $prefix.mb_strtolower(str_replace('_', '-', $code));
    }

    /**
     * Get the error title.
     */
    private function getTitle(Throwable $exception, int $statusCode): string
    {
        if ($exception instanceof ValidationException) {
            return 'Validation Failed';
        }

        return $this->getStatusText($statusCode);
    }

    /**
     * Get status text from HTTP status code.
     */
    private function getStatusText(int $statusCode): string
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

        return Arr::get($statusTexts, $statusCode, 'Error');
    }
}
