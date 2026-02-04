<?php

declare(strict_types=1);

namespace WorkDoneRight\ApiGuardian\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Throwable;

final class ApiException extends Exception
{
    private string $errorCode = 'API_ERROR';

    private int $statusCode = 500;

    private array $meta = [];

    private ?string $suggestion = null;

    private ?string $link = null;

    private bool $recoverable = false;

    private ?string $category = null;

    private array $context = [];

    /**
     * Create a new instance with a message.
     */
    public static function make(string $message = ''): self
    {
        return new self($message);
    }

    /**
     * Quick helper to create and throw a not found exception.
     */
    public static function notFound(string $message = 'Resource not found'): self
    {
        return self::make($message)
            ->statusCode(404)
            ->code('RESOURCE_NOT_FOUND');
    }

    /**
     * Quick helper to create and throw an unauthorized exception.
     */
    public static function unauthorized(string $message = 'Unauthorized'): self
    {
        return self::make($message)
            ->statusCode(401)
            ->code('UNAUTHORIZED');
    }

    /**
     * Quick helper to create and throw a forbidden exception.
     */
    public static function forbidden(string $message = 'Forbidden'): self
    {
        return self::make($message)
            ->statusCode(403)
            ->code('FORBIDDEN');
    }

    /**
     * Quick helper to create and throw a validation exception.
     */
    public static function validationFailed(string $message = 'Validation failed'): self
    {
        return self::make($message)
            ->statusCode(422)
            ->code('VALIDATION_FAILED');
    }

    /**
     * Quick helper to create and throw a server error exception.
     */
    public static function serverError(string $message = 'Internal server error'): self
    {
        return self::make($message)
            ->statusCode(500)
            ->code('SERVER_ERROR');
    }

    /**
     * Quick helper to create and throw a bad request exception.
     */
    public static function badRequest(string $message = 'Bad request'): self
    {
        return self::make($message)
            ->statusCode(400)
            ->code('BAD_REQUEST');
    }

    /**
     * Quick helper to create and throw a rate limit exception.
     */
    public static function rateLimitExceeded(string $message = 'Rate limit exceeded'): self
    {
        return self::make($message)
            ->statusCode(429)
            ->code('RATE_LIMIT_EXCEEDED')
            ->recoverable(true)
            ->suggestion('Please wait before making another request');
    }

    /**
     * Create an ApiException from a generic Throwable.
     */
    public static function fromThrowable(Throwable $throwable): self
    {
        $exception = self::make($throwable->getMessage());

        if (method_exists($throwable, 'getStatusCode')) {
            $exception->statusCode($throwable->getStatusCode());
        }

        if (method_exists($throwable, 'getCode')) {
            $code = $throwable->getCode();
            if (is_int($code) && $code >= 400 && $code < 600) {
                $exception->statusCode($code);
            }
        }

        return $exception;
    }

    /**
     * Set the error code.
     */
    public function code(string $code): self
    {
        $this->errorCode = $code;

        return $this;
    }

    /**
     * Get the error code.
     */
    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * Set the HTTP status code.
     */
    public function statusCode(int $statusCode): self
    {
        $this->statusCode = $statusCode;

        return $this;
    }

    /**
     * Get the HTTP status code.
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Set additional metadata.
     */
    public function meta(array $meta): self
    {
        $this->meta = array_merge($this->meta, $meta);

        return $this;
    }

    /**
     * Get the metadata.
     */
    public function getMeta(): array
    {
        return $this->meta;
    }

    /**
     * Set a suggestion for fixing the error.
     */
    public function suggestion(string $suggestion): self
    {
        $this->suggestion = $suggestion;

        return $this;
    }

    /**
     * Get the suggestion.
     */
    public function getSuggestion(): ?string
    {
        return $this->suggestion;
    }

    /**
     * Set a documentation link.
     */
    public function link(string $link): self
    {
        $this->link = $link;

        return $this;
    }

    /**
     * Get the documentation link.
     */
    public function getLink(): ?string
    {
        return $this->link;
    }

    /**
     * Mark this error as recoverable.
     */
    public function recoverable(bool $recoverable = true): self
    {
        $this->recoverable = $recoverable;

        return $this;
    }

    /**
     * Check if the error is recoverable.
     */
    public function isRecoverable(): bool
    {
        return $this->recoverable;
    }

    /**
     * Set the error category.
     */
    public function category(string $category): self
    {
        $this->category = $category;

        return $this;
    }

    /**
     * Get the error category.
     */
    public function getCategory(): ?string
    {
        return $this->category;
    }

    /**
     * Set additional context.
     */
    public function context(array $context): self
    {
        $this->context = array_merge($this->context, $context);

        return $this;
    }

    /**
     * Get the context.
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Throw the exception.
     *
     * @throws self
     */
    public function throw(): never
    {
        throw $this;
    }

    /**
     * Convert the exception to a JSON response.
     */
    public function toResponse(): JsonResponse
    {
        return resolve('api-guardian')->format($this, $this->statusCode);
    }

    /**
     * Get recovery suggestions for this error type.
     */
    public function getRecoverySuggestions(): array
    {
        if ($this->recoverable) {
            $recovery = resolve(\WorkDoneRight\ApiGuardian\Contracts\RecoveryStrategyContract::class);

            return $recovery->generateRecoverySuggestion($this);
        }

        return [
            'type' => 'non_recoverable',
            'message' => 'This error cannot be automatically recovered. Please contact support.',
            'actions' => [
                'Check the request parameters',
                'Verify your permissions',
                'Contact support team if the problem persists',
            ],
        ];
    }

    /**
     * Check if this error should trigger circuit breaker.
     */
    public function shouldTripCircuitBreaker(): bool
    {
        $criticalErrors = config('api-guardian.circuit_breaker.critical_errors', [
            'DATABASE_CONNECTION_FAILED',
            'EXTERNAL_SERVICE_UNAVAILABLE',
            'TIMEOUT_ERROR',
        ]);

        return in_array($this->errorCode, $criticalErrors) ||
               $this->statusCode >= 500;
    }
}
