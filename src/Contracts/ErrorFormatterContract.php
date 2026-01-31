<?php

namespace WorkDoneRight\ApiGuardian\Contracts;

use Illuminate\Http\JsonResponse;
use Throwable;

interface ErrorFormatterContract
{
    /**
     * Format an exception into a JSON response.
     */
    public function format(Throwable $exception, ?int $statusCode = null): JsonResponse;

    /**
     * Build the error response array.
     */
    public function buildErrorResponse(Throwable $exception, int $statusCode): array;

    /**
     * Get the HTTP status code for an exception.
     */
    public function getStatusCode(Throwable $exception): int;
}
