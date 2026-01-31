<?php

namespace WorkDoneRight\ApiGuardian;

use Illuminate\Http\JsonResponse;
use WorkDoneRight\ApiGuardian\Contracts\ErrorFormatterContract;
use WorkDoneRight\ApiGuardian\Formatters\JSendFormatter;
use WorkDoneRight\ApiGuardian\Formatters\JsonApiFormatter;
use WorkDoneRight\ApiGuardian\Formatters\Rfc7807Formatter;

class ApiGuardian
{
    protected ?ErrorFormatterContract $formatter = null;

    protected string $defaultFormat;

    public function __construct()
    {
        $this->defaultFormat = config('api-guardian.default_format', 'jsend');
    }

    /**
     * Set the error formatter.
     */
    public function useFormatter(string|ErrorFormatterContract $formatter): self
    {
        if (is_string($formatter)) {
            $this->formatter = $this->resolveFormatter($formatter);
        } else {
            $this->formatter = $formatter;
        }

        return $this;
    }

    /**
     * Get the current error formatter.
     */
    public function getFormatter(): ErrorFormatterContract
    {
        if (! $this->formatter) {
            $this->formatter = $this->resolveFormatter($this->defaultFormat);
        }

        return $this->formatter;
    }

    /**
     * Format an exception into a JSON response.
     */
    public function format(\Throwable $exception, ?int $statusCode = null): JsonResponse
    {
        return $this->getFormatter()->format($exception, $statusCode);
    }

    /**
     * Resolve a formatter by name.
     */
    protected function resolveFormatter(string $format): ErrorFormatterContract
    {
        return match ($format) {
            'jsend' => new JSendFormatter(),
            'rfc7807' => new Rfc7807Formatter(),
            'jsonapi' => new JsonApiFormatter(),
            default => new JSendFormatter(),
        };
    }

    /**
     * Create a new API exception with fluent interface.
     */
    public static function exception(string $message = ''): Exceptions\ApiException
    {
        return new Exceptions\ApiException($message);
    }

    /**
     * Quick helper to create a not found exception.
     */
    public static function notFound(string $message = 'Resource not found'): Exceptions\ApiException
    {
        return static::exception($message)
            ->statusCode(404)
            ->code('RESOURCE_NOT_FOUND');
    }

    /**
     * Quick helper to create an unauthorized exception.
     */
    public static function unauthorized(string $message = 'Unauthorized'): Exceptions\ApiException
    {
        return static::exception($message)
            ->statusCode(401)
            ->code('UNAUTHORIZED');
    }

    /**
     * Quick helper to create a forbidden exception.
     */
    public static function forbidden(string $message = 'Forbidden'): Exceptions\ApiException
    {
        return static::exception($message)
            ->statusCode(403)
            ->code('FORBIDDEN');
    }

    /**
     * Quick helper to create a validation exception.
     */
    public static function validationFailed(string $message = 'Validation failed'): Exceptions\ApiException
    {
        return static::exception($message)
            ->statusCode(422)
            ->code('VALIDATION_FAILED');
    }

    /**
     * Quick helper to create a server error exception.
     */
    public static function serverError(string $message = 'Internal server error'): Exceptions\ApiException
    {
        return static::exception($message)
            ->statusCode(500)
            ->code('SERVER_ERROR');
    }

    /**
     * Quick helper to create a bad request exception.
     */
    public static function badRequest(string $message = 'Bad request'): Exceptions\ApiException
    {
        return static::exception($message)
            ->statusCode(400)
            ->code('BAD_REQUEST');
    }

    /**
     * Quick helper to create a rate limit exception.
     */
    public static function rateLimitExceeded(string $message = 'Rate limit exceeded', ?int $retryAfter = null): Exceptions\ApiException
    {
        $exception = static::exception($message)
            ->statusCode(429)
            ->code('RATE_LIMIT_EXCEEDED');

        if ($retryAfter) {
            $exception->meta(['retry_after' => $retryAfter]);
        }

        return $exception;
    }
}
