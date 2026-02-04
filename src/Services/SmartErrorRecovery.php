<?php

declare(strict_types=1);

namespace WorkDoneRight\ApiGuardian\Services;

use Closure;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use WorkDoneRight\ApiGuardian\Contracts\RecoveryStrategyContract;
use WorkDoneRight\ApiGuardian\Models\CircuitBreaker;

final class SmartErrorRecovery implements RecoveryStrategyContract
{
    private array $retryStrategies = [];

    private array $fallbackStrategies = [];

    public function execute(string $service, Closure $operation, ?string $operationName = null): mixed
    {
        $circuitBreaker = CircuitBreaker::getOrCreate($service, $operationName);

        if (! $circuitBreaker->canAttempt()) {
            return $this->executeFallback($service, $operationName, new Exception('Circuit breaker is open'));
        }

        try {
            $result = $this->executeWithRetry($service, $operation, $operationName);

            $circuitBreaker->recordSuccess();

            return $result;
        } catch (Exception $exception) {
            $circuitBreaker->recordFailure();

            return $this->executeFallback($service, $operationName, $exception);
        }
    }

    public function registerRetryStrategy(string $service, Closure $strategy): void
    {
        $this->retryStrategies[$service] = $strategy;
    }

    public function registerFallbackStrategy(string $service, Closure $strategy): void
    {
        $this->fallbackStrategies[$service] = $strategy;
    }

    public function generateRecoverySuggestion(Exception $exception): array
    {
        $suggestions = [
            'timeout' => [
                'message' => 'The operation timed out. Consider increasing the timeout or optimizing the request.',
                'actions' => [
                    'Try again with a longer timeout',
                    'Check if the request payload can be optimized',
                    'Verify network connectivity',
                ],
            ],
            'connection' => [
                'message' => 'Connection to the service failed. The service may be temporarily unavailable.',
                'actions' => [
                    'Retry the operation in a few moments',
                    'Check service status',
                    'Verify network configuration',
                ],
            ],
            'rate_limit' => [
                'message' => 'Rate limit exceeded. Too many requests were made in a short period.',
                'actions' => [
                    'Wait before retrying',
                    'Implement exponential backoff',
                    'Consider reducing request frequency',
                ],
            ],
            'authentication' => [
                'message' => 'Authentication failed. Invalid or expired credentials.',
                'actions' => [
                    'Refresh authentication tokens',
                    'Verify API credentials',
                    'Check token expiration',
                ],
            ],
            'validation' => [
                'message' => 'Request validation failed. The request data is invalid.',
                'actions' => [
                    'Check request format',
                    'Validate required fields',
                    'Review API documentation',
                ],
            ],
        ];

        $message = mb_strtolower($exception->getMessage());

        foreach ($suggestions as $key => $suggestion) {
            if (str_contains($message, $key)) {
                return $suggestion + ['type' => $key];
            }
        }

        return [
            'type' => 'general',
            'message' => 'An unexpected error occurred. Please try again or contact support if the problem persists.',
            'actions' => [
                'Retry the operation',
                'Check service status',
                'Contact support team',
            ],
        ];
    }

    public function getCircuitBreakerStatus(): array
    {
        return CircuitBreaker::all()->map(fn ($breaker): array => [
            'service' => $breaker->service,
            'operation' => $breaker->operation,
            'state' => $breaker->state,
            'failure_count' => $breaker->failure_count,
            'failure_threshold' => $breaker->failure_threshold,
            'can_attempt' => $breaker->canAttempt(),
            'next_attempt_at' => $breaker->next_attempt_at?->toISOString(),
        ])->toArray();
    }

    private function executeWithRetry(string $service, Closure $operation, ?string $operationName = null): mixed
    {
        $maxRetries = config('api-guardian.recovery.max_retries', 3);
        $baseDelay = config('api-guardian.recovery.base_delay', 1000); // milliseconds
        $backoffMultiplier = config('api-guardian.recovery.backoff_multiplier', 2);

        $attempt = 0;
        $delay = $baseDelay;

        while ($attempt < $maxRetries) {
            try {
                return $operation();
            } catch (Exception $exception) {
                $attempt++;

                if (! $this->isTransientError($exception)) {
                    throw $exception;
                }

                if ($attempt >= $maxRetries) {
                    throw $exception;
                }

                Sleep::usleep($delay * 1000); // Convert to microseconds
                $delay *= $backoffMultiplier;

                Log::warning('Retrying operation after transient error', [
                    'service' => $service,
                    'operation' => $operationName,
                    'attempt' => $attempt,
                    'max_retries' => $maxRetries,
                    'delay' => $delay,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }

        throw new Exception('Max retries exceeded');
    }

    private function isTransientError(Exception $exception): bool
    {
        $transientPatterns = config('api-guardian.recovery.transient_error_patterns', [
            '/timeout/i',
            '/connection/i',
            '/network/i',
            '/temporary/i',
            '/503/',
            '/502/',
            '/504/',
            '/429/',
        ]);

        foreach ($transientPatterns as $pattern) {
            if (preg_match($pattern, $exception->getMessage())) {
                return true;
            }
        }

        $transientCodes = config('api-guardian.recovery.transient_status_codes', [429, 502, 503, 504]);

        return in_array($this->getStatusCode($exception), $transientCodes);
    }

    private function getStatusCode(Exception $exception): int
    {
        if (method_exists($exception, 'getStatusCode')) {
            return $exception->getStatusCode();
        }

        if (method_exists($exception, 'getCode')) {
            return $exception->getCode();
        }

        return 500;
    }

    private function executeFallback(string $service, ?string $operationName, Exception $exception): mixed
    {
        $fallbackKey = $service.($operationName ? '.'.$operationName : '');
        $globalFallbackKey = $service;

        if (isset($this->fallbackStrategies[$fallbackKey])) {
            return $this->fallbackStrategies[$fallbackKey]($exception);
        }

        if (isset($this->fallbackStrategies[$globalFallbackKey])) {
            return $this->fallbackStrategies[$globalFallbackKey]($exception);
        }

        // Default fallback strategies
        if ($this->isTransientError($exception)) {
            return [
                'error' => 'Service temporarily unavailable',
                'message' => 'The service is experiencing temporary issues. Please try again later.',
                'retry_after' => 60,
                'type' => 'transient_error',
            ];
        }

        return [
            'error' => 'Service unavailable',
            'message' => 'The service is currently unavailable. Please contact support if the problem persists.',
            'type' => 'permanent_error',
        ];
    }
}
