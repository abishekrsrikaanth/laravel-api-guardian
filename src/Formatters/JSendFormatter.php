<?php

declare(strict_types=1);

namespace WorkDoneRight\ApiGuardian\Formatters;

use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * JSend Formatter
 *
 * Formats errors according to the JSend specification.
 *
 * @see https://github.com/omniti-labs/jsend
 */
final class JSendFormatter extends AbstractFormatter
{
    /**
     * Build the error response array.
     */
    public function buildErrorResponse(Throwable $exception, int $statusCode): array
    {
        $response = [
            'status' => $this->getStatus($statusCode),
        ];

        if ($exception instanceof ValidationException) {
            $response = Arr::set($response, 'data', $this->buildValidationErrors($exception));
            $response = Arr::set($response, 'message', $this->getErrorMessage($exception));
        } else {
            $response = Arr::set($response, 'message', $this->getErrorMessage($exception));
            $response = Arr::set($response, 'code', $this->getErrorCode($exception));

            // Add additional data if available
            $data = $this->buildErrorData($exception);
            if ($data !== []) {
                $response = Arr::set($response, 'data', $data);
            }
        }

        return $response;
    }

    /**
     * Get JSend status based on HTTP status code.
     */
    private function getStatus(int $statusCode): string
    {
        if ($statusCode >= 500) {
            return 'error';
        }

        if ($statusCode >= 400) {
            return 'fail';
        }

        return 'success';
    }

    /**
     * Build error data object.
     */
    private function buildErrorData(Throwable $exception): array
    {
        $data = [];

        // Add metadata
        $meta = $this->getMetadata($exception);
        if ($meta !== []) {
            $data = array_merge($data, $meta);
        }

        // Add context using trait methods
        if ($this->shouldIncludeErrorId()) {
            $context = $this->buildContext($exception);
            $data = Arr::set($data, 'error_id', Arr::get($context, 'error_id'));
        }

        if ($this->shouldIncludeTimestamp()) {
            $data = Arr::set($data, 'timestamp', now()->toIso8601String());
        }

        // Add suggestion using trait method
        if ($this->shouldIncludeSuggestions()) {
            $suggestion = $this->getSuggestion($exception);
            if ($suggestion) {
                $data = Arr::set($data, 'suggestion', $suggestion);
            }
        }

        // Add documentation link
        $link = $this->getLink($exception);
        if ($link) {
            $data = Arr::set($data, 'documentation', $link);
        }

        // Add debug information
        $debug = $this->buildDebugInfo($exception);
        if ($debug !== []) {
            return Arr::set($data, 'debug', $debug);
        }

        return $data;
    }
}
