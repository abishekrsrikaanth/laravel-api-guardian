<?php

declare(strict_types=1);

namespace WorkDoneRight\ApiGuardian\Handlers;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;
use WorkDoneRight\ApiGuardian\ApiGuardian;
use WorkDoneRight\ApiGuardian\Exceptions\ApiException;
use WorkDoneRight\ApiGuardian\Services\ErrorCollector;
use WorkDoneRight\ApiGuardian\Services\SmartErrorRecovery;

final class EnhancedApiExceptionHandler extends ExceptionHandler
{
    public function __construct(private readonly ErrorCollector $errorCollector) {}

    public function render($request, Throwable $exception)
    {
        // Only handle API requests
        if (! $this->isApiRequest($request)) {
            return parent::render($request, $exception);
        }

        try {
            // Collect error data for monitoring
            $errorData = $this->collectErrorData($exception);
            $apiError = $this->errorCollector->collect($errorData, $request);

            // Check if we can apply recovery strategies
            $recoveryResult = $this->attemptRecovery($exception, $request);

            if ($recoveryResult instanceof Response) {
                return $recoveryResult;
            }

            // Format the error response using existing Guardian formatters
            return $this->formatErrorResponse($exception, $request, $apiError);

        } catch (Throwable $throwable) {
            Log::error('Failed to render API error', [
                'original_exception' => $exception->getMessage(),
                'render_exception' => $throwable->getMessage(),
            ]);

            return $this->fallbackErrorResponse();
        }
    }

    protected function isApiRequest(Request $request): bool
    {
        if ($request->expectsJson()) {
            return true;
        }

        if ($request->is('api/*')) {
            return true;
        }

        return $request->header('Accept') && str_contains($request->header('Accept'), 'json');
    }

    /**
     * @return array<string, mixed>
     */
    protected function collectErrorData(Throwable $exception): array
    {
        return [
            'error_id' => $this->generateErrorId(),
            'exception_class' => $exception::class,
            'error_code' => method_exists($exception, 'getErrorCode') ? $exception->getErrorCode() : null,
            'message' => $exception->getMessage(),
            'status_code' => $this->getStatusCode($exception),
            'context' => $this->extractContext($exception),
            'meta' => $this->extractMeta($exception),
        ];
    }

    protected function generateErrorId(): string
    {
        return 'err_'.uniqid().'_'.time();
    }

    protected function getStatusCode(Throwable $exception): int
    {
        if (method_exists($exception, 'getStatusCode')) {
            return $exception->getStatusCode();
        }

        if (method_exists($exception, 'getCode')) {
            $code = $exception->getCode();

            return is_int($code) && $code >= 400 && $code < 600 ? $code : 500;
        }

        return 500;
    }

    protected function extractContext(Throwable $exception): array
    {
        $context = [
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $this->formatTrace($exception->getTrace()),
        ];

        if (method_exists($exception, 'getContext')) {
            return array_merge($context, $exception->getContext());
        }

        return $context;
    }

    protected function extractMeta(Throwable $exception): array
    {
        $meta = [];

        if (method_exists($exception, 'getMeta')) {
            $meta = $exception->getMeta();
        }

        if (method_exists($exception, 'getSuggestions')) {
            $meta['suggestions'] = $exception->getSuggestions();
        }

        if (method_exists($exception, 'getLinks')) {
            $meta['links'] = $exception->getLinks();
        }

        return $meta;
    }

    /**
     * @param  array<int, array<string, int|list<mixed>|object|string>>  $trace
     */
    protected function formatTrace(array $trace): array
    {
        if (! config('app.debug')) {
            return [];
        }

        return array_map(fn (array $trace): array => [
            'file' => $trace['file'] ?? 'unknown',
            'line' => $trace['line'] ?? 0,
            'function' => $trace['function'] ?? 'unknown',
            'class' => $trace['class'] ?? null,
            'type' => $trace['type'] ?? null,
        ], array_slice($trace, 0, 10)); // Limit to first 10 frames
    }

    protected function attemptRecovery(Throwable $exception, Request $request): ?Response
    {
        // Check if this is a request that can be recovered
        if (! $this->isRecoverableRequest($exception, $request)) {
            return null;
        }

        $recovery = resolve(SmartErrorRecovery::class);

        // Try to execute recovery strategy
        try {
            return $recovery->execute(
                $request->route()->getName() ?? 'api_request',

                // Re-execute the original request
                fn () => app()->handle($request)
            );
        } catch (Throwable $throwable) {
            Log::warning('Recovery strategy failed', [
                'original_error' => $exception->getMessage(),
                'recovery_error' => $throwable->getMessage(),
            ]);

            return null;
        }
    }

    protected function isRecoverableRequest(Throwable $exception, Request $request): bool
    {
        // Only attempt recovery for specific HTTP methods
        $recoverableMethods = ['GET', 'HEAD'];
        if (! in_array($request->method(), $recoverableMethods)) {
            return false;
        }

        // Only attempt recovery for specific error types
        $recoverableErrors = config('api-guardian.recovery.recoverable_errors', [
            'timeout',
            'connection',
            'rate_limit',
        ]);

        $message = mb_strtolower($exception->getMessage());

        foreach ($recoverableErrors as $error) {
            if (str_contains($message, (string) $error)) {
                return true;
            }
        }

        // Check for recoverable status codes
        $recoverableCodes = config('api-guardian.recovery.recoverable_status_codes', [429, 502, 503, 504]);
        $statusCode = $this->getStatusCode($exception);

        return in_array($statusCode, $recoverableCodes);
    }

    protected function formatErrorResponse(Throwable $exception, Request $request, $apiError): Response
    {
        // Use the existing Guardian formatters
        $guardian = resolve(ApiGuardian::class);

        if ($exception instanceof ApiException) {
            return $guardian->formatException($exception, $request);
        }

        // Create an ApiException from the generic exception
        $apiException = ApiException::fromThrowable($exception);

        return $guardian->formatException($apiException, $request);
    }

    protected function fallbackErrorResponse(): Response
    {
        return response()->json([
            'status' => 'error',
            'message' => 'An internal error occurred',
            'error_id' => $this->generateErrorId(),
        ], 500);
    }
}
