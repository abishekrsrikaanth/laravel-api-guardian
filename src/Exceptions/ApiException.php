<?php

namespace WorkDoneRight\ApiGuardian\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class ApiException extends Exception
{
    protected string $errorCode = 'API_ERROR';

    protected int $statusCode = 500;

    protected array $meta = [];

    protected ?string $suggestion = null;

    protected ?string $link = null;

    protected bool $recoverable = false;

    protected ?string $category = null;

    protected array $context = [];

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
    public function throw(): void
    {
        throw $this;
    }

    /**
     * Convert the exception to a JSON response.
     */
    public function toResponse(): JsonResponse
    {
        return app('api-guardian')->format($this, $this->statusCode);
    }

    /**
     * Create a new instance with a message.
     */
    public static function make(string $message = ''): self
    {
        return new static($message);
    }

    /**
     * Quick helper to create and throw a not found exception.
     */
    public static function notFound(string $message = 'Resource not found'): self
    {
        return static::make($message)
            ->statusCode(404)
            ->code('RESOURCE_NOT_FOUND');
    }

    /**
     * Quick helper to create and throw an unauthorized exception.
     */
    public static function unauthorized(string $message = 'Unauthorized'): self
    {
        return static::make($message)
            ->statusCode(401)
            ->code('UNAUTHORIZED');
    }

    /**
     * Quick helper to create and throw a forbidden exception.
     */
    public static function forbidden(string $message = 'Forbidden'): self
    {
        return static::make($message)
            ->statusCode(403)
            ->code('FORBIDDEN');
    }

    /**
     * Quick helper to create and throw a validation exception.
     */
    public static function validationFailed(string $message = 'Validation failed'): self
    {
        return static::make($message)
            ->statusCode(422)
            ->code('VALIDATION_FAILED');
    }

    /**
     * Quick helper to create and throw a server error exception.
     */
    public static function serverError(string $message = 'Internal server error'): self
    {
        return static::make($message)
            ->statusCode(500)
            ->code('SERVER_ERROR');
    }

    /**
     * Quick helper to create and throw a bad request exception.
     */
    public static function badRequest(string $message = 'Bad request'): self
    {
        return static::make($message)
            ->statusCode(400)
            ->code('BAD_REQUEST');
    }
}
