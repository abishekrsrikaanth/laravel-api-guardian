<?php

declare(strict_types=1);

namespace WorkDoneRight\ApiGuardian\Facades;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Facade;
use Throwable;
use WorkDoneRight\ApiGuardian\Contracts\ErrorFormatterContract;
use WorkDoneRight\ApiGuardian\Exceptions\ApiException;

/**
 * @method static \WorkDoneRight\ApiGuardian\ApiGuardian useFormatter(string|ErrorFormatterContract $formatter)
 * @method static ErrorFormatterContract getFormatter()
 * @method static JsonResponse format(Throwable $exception, ?int $statusCode = null)
 * @method static ApiException exception(string $message = '')
 * @method static ApiException notFound(string $message = 'Resource not found')
 * @method static ApiException unauthorized(string $message = 'Unauthorized')
 * @method static ApiException forbidden(string $message = 'Forbidden')
 * @method static ApiException validationFailed(string $message = 'Validation failed')
 * @method static ApiException serverError(string $message = 'Internal server error')
 * @method static ApiException badRequest(string $message = 'Bad request')
 * @method static ApiException rateLimitExceeded(string $message = 'Rate limit exceeded', ?int $retryAfter = null)
 *
 * @see \WorkDoneRight\ApiGuardian\ApiGuardian
 */
final class ApiGuardian extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'api-guardian';
    }
}
