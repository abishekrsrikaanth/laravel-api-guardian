<?php

declare(strict_types=1);

namespace WorkDoneRight\ApiGuardian\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Throwable;
use WorkDoneRight\ApiGuardian\Facades\ApiGuardian;

final class Handler extends ExceptionHandler
{
    /**
     * Render an exception into an HTTP response.
     */
    public function render($request, Throwable $e): mixed
    {
        // Only handle API requests
        if ($this->shouldHandleApiException($request)) {
            return $this->renderApiException($e);
        }

        return parent::render($request, $e);
    }

    /**
     * Report or log an exception.
     */
    public function report(Throwable $e): void
    {
        if ($this->shouldReport($e)) {
            $this->reportToDrivers($e);
        }

        parent::report($e);
    }

    /**
     * Determine if this is an API request that should be handled.
     */
    protected function shouldHandleApiException(Request $request): bool
    {
        // Check if request expects JSON
        if ($request->expectsJson()) {
            return true;
        }

        // Check if request is to an API route
        if ($request->is('api/*')) {
            return true;
        }

        // Check if Accept header includes JSON
        return $request->header('Accept') && str_contains($request->header('Accept'), 'application/json');
    }

    /**
     * Render an API exception.
     */
    protected function renderApiException(Throwable $exception): JsonResponse
    {
        return ApiGuardian::format($exception);
    }

    /**
     * Report to configured drivers.
     */
    protected function reportToDrivers(Throwable $exception): void
    {
        $drivers = config('api-guardian.reporting.drivers', []);

        foreach ($drivers as $driver => $config) {
            if (! (Arr::get($config, 'enabled', false))) {
                continue;
            }

            match ($driver) {
                'webhook' => $this->reportToWebhook($exception, $config),
                default => null,
            };
        }
    }

    /**
     * Report to webhook.
     *
     * @param  array<string, mixed>  $config
     */
    protected function reportToWebhook(Throwable $exception, array $config): void
    {
        if (! isset($config['url'])) {
            return;
        }

        // Only report critical errors if configured
        if ((Arr::get($config, 'critical_only', false)) && ! $this->isCritical($exception)) {
            return;
        }

        // Send webhook asynchronously
        try {
            resolve('http')->post($config['url'], [
                'error' => [
                    'message' => $exception->getMessage(),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                    'timestamp' => now()->toIso8601String(),
                ],
            ]);
        } catch (Throwable) {
            // Silently fail webhook reporting
        }
    }

    /**
     * Check if exception is critical.
     */
    protected function isCritical(Throwable $exception): bool
    {
        if ($exception instanceof ApiException) {
            $statusCode = $exception->getStatusCode();
        } else {
            $statusCode = method_exists($exception, 'getStatusCode')
                ? $exception->getStatusCode()
                : 500;
        }

        $criticalCodes = config('api-guardian.categories.critical', [500, 502]);

        return in_array($statusCode, $criticalCodes);
    }
}
