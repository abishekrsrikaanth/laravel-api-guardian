<?php

declare(strict_types=1);

namespace WorkDoneRight\ApiGuardian\Formatters;

use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * GraphQL Formatter
 *
 * Formats errors according to the GraphQL specification.
 *
 * @see https://spec.graphql.org/October2021/#sec-Errors
 */
final class GraphQLFormatter extends AbstractFormatter
{
    /**
     * Build the error response array.
     */
    public function buildErrorResponse(Throwable $exception, int $statusCode): array
    {
        $errors = [];

        if ($exception instanceof ValidationException) {
            // Multiple validation errors
            foreach ($exception->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $errors[] = $this->buildGraphQLError(
                        $message,
                        Arr::wrap(['field' => $field, 'validation' => true]),
                        $exception
                    );
                }
            }
        } else {
            // Single error
            $errors[] = $this->buildGraphQLError(
                $this->getErrorMessage($exception),
                [],
                $exception
            );
        }

        return [
            'errors' => $errors,
            'data' => null,
        ];
    }

    /**
     * Build a single GraphQL error object.
     *
     * @param  array<string, mixed>  $additionalContext
     */
    private function buildGraphQLError(string $message, array $additionalContext, Throwable $exception): array
    {
        $error = ['message' => $message];

        // Add locations if available (line and column in GraphQL query)
        $locations = $this->extractLocations($exception);
        if ($locations !== []) {
            $error = Arr::set($error, 'locations', $locations);
        }

        // Add path if available (path to the field in the result)
        $path = $this->extractPath($exception);
        if ($path !== []) {
            $error = Arr::set($error, 'path', $path);
        }

        // Add extensions (additional error information)
        $extensions = $this->buildExtensions($exception, $additionalContext);
        if ($extensions !== []) {
            return Arr::set($error, 'extensions', $extensions);
        }

        return $error;
    }

    /**
     * Extract GraphQL query locations from exception.
     *
     * @return array<array{line: int, column: int}>
     */
    private function extractLocations(Throwable $exception): array
    {
        // Check if exception has location data (custom GraphQL exceptions might implement this)
        if (method_exists($exception, 'getLocations')) {
            return $exception->getLocations();
        }

        // Check metadata for location information
        $meta = $this->getMetadata($exception);

        if (Arr::has($meta, 'locations') && is_array(Arr::get($meta, 'locations'))) {
            return Arr::get($meta, 'locations');
        }

        if (Arr::has($meta, 'line') && Arr::has($meta, 'column')) {
            return [[
                'line' => (int) Arr::get($meta, 'line'),
                'column' => (int) Arr::get($meta, 'column'),
            ]];
        }

        return [];
    }

    /**
     * Extract GraphQL path from exception.
     *
     * @return array<string|int>
     */
    private function extractPath(Throwable $exception): array
    {
        // Check if the exception has path data
        if (method_exists($exception, 'getPath')) {
            return $exception->getPath();
        }

        // Check metadata for path information
        $meta = $this->getMetadata($exception);

        if (Arr::has($meta, 'path') && is_array(Arr::get($meta, 'path'))) {
            return Arr::get($meta, 'path');
        }

        return [];
    }

    /**
     * Build GraphQL extensions object.
     *
     * @param  array<string, mixed>  $additionalContext
     * @return array<string, mixed>
     */
    private function buildExtensions(Throwable $exception, array $additionalContext): array
    {
        $extensions = [];

        // Add error code
        $extensions = Arr::set($extensions, 'code', $this->getErrorCode($exception));

        // Add category/classification
        $extensions = Arr::set($extensions, 'category', $this->categorizeError($exception));

        // Add timestamp using trait method
        if ($this->shouldIncludeTimestamp()) {
            $extensions = Arr::set($extensions, 'timestamp', now()->toIso8601String());
        }

        // Add error ID for tracking using trait method
        if ($this->shouldIncludeErrorId()) {
            $context = $this->buildContext($exception);
            $extensions = Arr::set($extensions, 'errorId', Arr::get($context, 'error_id'));
        }

        // Add field for validation errors
        if (Arr::has($additionalContext, 'field')) {
            $extensions = Arr::set($extensions, 'field', Arr::get($additionalContext, 'field'));
        }

        // Add validation flag
        if (Arr::has($additionalContext, 'validation')) {
            $extensions = Arr::set($extensions, 'validation', true);
        }

        // Add metadata
        $meta = $this->getMetadata($exception);
        if ($meta !== []) {
            // Filter out data already used in locations/path
            $filteredMeta = Arr::except($meta, ['locations', 'line', 'column', 'path']);
            if (! empty($filteredMeta)) {
                $extensions = array_merge($extensions, $filteredMeta);
            }
        }

        // Add suggestion using trait method
        if ($this->shouldIncludeSuggestions()) {
            $suggestion = $this->getSuggestion($exception);
            if ($suggestion) {
                $extensions = Arr::set($extensions, 'suggestion', $suggestion);
            }
        }

        // Add documentation link
        $link = $this->getLink($exception);
        if ($link) {
            $extensions = Arr::set($extensions, 'documentation', $link);
        }

        // Add debug information
        $debug = $this->buildDebugInfo($exception);
        if ($debug !== []) {
            return Arr::set($extensions, 'debug', $debug);
        }

        return $extensions;
    }

    /**
     * Categorize error type according to GraphQL best practices.
     */
    private function categorizeError(Throwable $exception): string
    {
        if ($exception instanceof ValidationException) {
            return 'validation';
        }

        $statusCode = $this->getStatusCode($exception);

        return match (true) {
            $statusCode === 401 => 'authentication',
            $statusCode === 403 => 'authorization',
            $statusCode === 404 => 'not_found',
            $statusCode === 429 => 'rate_limit',
            $statusCode >= 500 => 'internal',
            $statusCode >= 400 => 'client',
            default => 'unknown',
        };
    }
}
