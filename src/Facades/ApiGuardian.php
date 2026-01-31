<?php

namespace WorkDoneRight\ApiGuardian\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \WorkDoneRight\ApiGuardian\ApiGuardian useFormatter(string|\WorkDoneRight\ApiGuardian\Contracts\ErrorFormatterContract $formatter)
 * @method static \WorkDoneRight\ApiGuardian\Contracts\ErrorFormatterContract getFormatter()
 * @method static \Illuminate\Http\JsonResponse format(\Throwable $exception, ?int $statusCode = null)
 * @method static \WorkDoneRight\ApiGuardian\Exceptions\ApiException exception(string $message = '')
 * @method static \WorkDoneRight\ApiGuardian\Exceptions\ApiException notFound(string $message = 'Resource not found')
 * @method static \WorkDoneRight\ApiGuardian\Exceptions\ApiException unauthorized(string $message = 'Unauthorized')
 * @method static \WorkDoneRight\ApiGuardian\Exceptions\ApiException forbidden(string $message = 'Forbidden')
 * @method static \WorkDoneRight\ApiGuardian\Exceptions\ApiException validationFailed(string $message = 'Validation failed')
 * @method static \WorkDoneRight\ApiGuardian\Exceptions\ApiException serverError(string $message = 'Internal server error')
 * @method static \WorkDoneRight\ApiGuardian\Exceptions\ApiException badRequest(string $message = 'Bad request')
 * @method static \WorkDoneRight\ApiGuardian\Exceptions\ApiException rateLimitExceeded(string $message = 'Rate limit exceeded', ?int $retryAfter = null)
 *
 * @see \WorkDoneRight\ApiGuardian\ApiGuardian
 */
class ApiGuardian extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'api-guardian';
    }
}
