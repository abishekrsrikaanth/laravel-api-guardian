<?php

namespace WorkDoneRight\ApiGuardian\Formatters;

use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * JSend Formatter
 *
 * Formats errors according to the JSend specification.
 *
 * @see https://github.com/omniti-labs/jsend
 */
class JSendFormatter extends AbstractFormatter
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
            $response['data'] = $this->buildValidationErrors($exception);
            $response['message'] = $this->getErrorMessage($exception);
        } else {
            $response['message'] = $this->getErrorMessage($exception);
            $response['code'] = $this->getErrorCode($exception);

            // Add additional data if available
            $data = $this->buildErrorData($exception);
            if (! empty($data)) {
                $response['data'] = $data;
            }
        }

        return $response;
    }

    /**
     * Get JSend status based on HTTP status code.
     */
    protected function getStatus(int $statusCode): string
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
    protected function buildErrorData(Throwable $exception): array
    {
        $data = [];

        // Add metadata
        $meta = $this->getMetadata($exception);
        if (! empty($meta)) {
            $data = array_merge($data, $meta);
        }

        // Add context
        if (config('api-guardian.context.include_error_id')) {
            $data['error_id'] = $this->buildContext($exception)['error_id'] ?? null;
        }

        if (config('api-guardian.context.include_timestamp')) {
            $data['timestamp'] = now()->toIso8601String();
        }

        // Add suggestion
        if (config('api-guardian.context.include_suggestions')) {
            $suggestion = $this->getSuggestion($exception);
            if ($suggestion) {
                $data['suggestion'] = $suggestion;
            }
        }

        // Add documentation link
        $link = $this->getLink($exception);
        if ($link) {
            $data['documentation'] = $link;
        }

        // Add debug information
        $debug = $this->buildDebugInfo($exception);
        if (! empty($debug)) {
            $data['debug'] = $debug;
        }

        return $data;
    }
}
