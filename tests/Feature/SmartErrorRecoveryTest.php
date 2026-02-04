<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use WorkDoneRight\ApiGuardian\Models\CircuitBreaker;
use WorkDoneRight\ApiGuardian\Services\SmartErrorRecovery;

beforeEach(function () {
    Cache::flush();
    Log::spy();
});

it('executes operation successfully without retries', function () {
    $recovery = resolve(SmartErrorRecovery::class);

    $result = $recovery->execute('test-service', function () {
        return 'success';
    }, 'test-operation');

    expect($result)->toBe('success');

    $breaker = CircuitBreaker::where('service', 'test-service')->first();
    expect($breaker)->not->toBeNull()
        ->and($breaker->state)->toBe('closed');
});

it('retries on transient errors', function () {
    $recovery = resolve(SmartErrorRecovery::class);
    $attempts = 0;

    try {
        $recovery->execute('test-service', function () use (&$attempts) {
            $attempts++;
            if ($attempts < 3) {
                throw new Exception('Connection timeout');
            }

            return 'success';
        });
    } catch (Exception $e) {
        // Expected
    }

    expect($attempts)->toBeGreaterThan(1);
});

it('does not retry on non-transient errors', function () {
    $recovery = resolve(SmartErrorRecovery::class);
    $attempts = 0;

    try {
        $recovery->execute('test-service', function () use (&$attempts) {
            $attempts++;
            throw new Exception('Invalid input data');
        });
    } catch (Exception $e) {
        // Expected
    }

    expect($attempts)->toBe(1); // Should not retry
});

it('respects max retries configuration', function () {
    config([
        'api-guardian.recovery.max_retries' => 2,
        'api-guardian.recovery.transient_error_patterns' => ['/temporarily/i'],
    ]);

    $recovery = resolve(SmartErrorRecovery::class);
    $attempts = 0;

    // execute() returns fallback, doesn't throw
    $result = $recovery->execute('test-service', function () use (&$attempts) {
        $attempts++;
        throw new Exception('Service temporarily unavailable');
    });

    expect($attempts)->toBe(2) // max_retries
        ->and($result)->toBeArray() // Returns fallback
        ->and($result)->toHaveKey('type');
});

it('applies exponential backoff between retries', function () {
    config([
        'api-guardian.recovery.max_retries' => 3,
        'api-guardian.recovery.base_delay' => 100, // milliseconds
        'api-guardian.recovery.backoff_multiplier' => 2,
    ]);

    $recovery = resolve(SmartErrorRecovery::class);
    $timestamps = [];

    try {
        $recovery->execute('test-service', function () use (&$timestamps) {
            $timestamps[] = microtime(true);
            throw new Exception('Network error');
        });
    } catch (Exception $e) {
        // Expected
    }

    // Should have multiple attempts
    expect(count($timestamps))->toBeGreaterThan(1);

    // Check delays are increasing (exponential backoff)
    if (count($timestamps) >= 3) {
        $delay1 = ($timestamps[1] - $timestamps[0]) * 1000;
        $delay2 = ($timestamps[2] - $timestamps[1]) * 1000;

        expect($delay2)->toBeGreaterThan($delay1);
    }
});

it('identifies transient errors by pattern', function () {
    // Ensure config is set BEFORE creating recovery instance
    config([
        'api-guardian.recovery.max_retries' => 3,
        'api-guardian.recovery.base_delay' => 10, // Short delay for tests
        'api-guardian.recovery.transient_error_patterns' => [
            '/timeout/i',
            '/connection/i',
            '/network/i',
            '/temporary/i',
            '/temporarily/i',
            '/503/',
            '/502/',
            '/504/',
            '/429/',
        ],
    ]);

    // Create a fresh instance with the config
    $recovery = new SmartErrorRecovery;

    $transientPatterns = [
        'Connection timeout',
        'Network error',
        'HTTP 503 error',
        'HTTP 502 error',
    ];

    foreach ($transientPatterns as $pattern) {
        $attempts = 0;

        // execute() returns fallback, doesn't throw for transient errors
        $result = $recovery->execute('test-service-'.md5($pattern), function () use (&$attempts, $pattern) {
            $attempts++;
            throw new Exception($pattern);
        });

        expect($attempts)->toBeGreaterThan(1, "Pattern '{$pattern}' should be retried")
            ->and($result)->toBeArray();
    }
});

it('executes fallback when circuit breaker is open', function () {
    $recovery = resolve(SmartErrorRecovery::class);

    // Register custom fallback
    $recovery->registerFallbackStrategy('test-service', function ($e) {
        return ['fallback' => 'data'];
    });

    // Create and open circuit breaker
    $breaker = CircuitBreaker::getOrCreate('test-service', 'test-operation');
    $breaker->state = 'open';
    $breaker->next_attempt_at = now()->addMinutes(10);
    $breaker->save();
    Cache::flush();

    $result = $recovery->execute('test-service', function () {
        throw new Exception('Should not execute');
    }, 'test-operation');

    expect($result)->toBe(['fallback' => 'data']);
});

it('uses default fallback for transient errors', function () {
    $recovery = resolve(SmartErrorRecovery::class);

    // Force all retries to fail
    config(['api-guardian.recovery.max_retries' => 1]);

    $result = $recovery->execute('test-service', function () {
        throw new Exception('Connection timeout');
    });

    expect($result)->toBeArray()
        ->and($result)->toHaveKey('error')
        ->and($result['type'])->toBe('transient_error');
});

it('uses default fallback for permanent errors', function () {
    $recovery = resolve(SmartErrorRecovery::class);

    $result = $recovery->execute('test-service', function () {
        throw new Exception('Invalid data');
    });

    expect($result)->toBeArray()
        ->and($result)->toHaveKey('error')
        ->and($result['type'])->toBe('permanent_error');
});

it('generates recovery suggestions for timeout errors', function () {
    $recovery = resolve(SmartErrorRecovery::class);

    $exception = new Exception('Connection timeout error'); // Use "timeout" keyword
    $suggestion = $recovery->generateRecoverySuggestion($exception);

    expect($suggestion)->toHaveKey('type')
        ->and($suggestion['type'])->toBe('timeout')
        ->and($suggestion)->toHaveKey('message')
        ->and($suggestion)->toHaveKey('actions')
        ->and($suggestion['actions'])->toBeArray();
});

it('generates recovery suggestions for connection errors', function () {
    $recovery = resolve(SmartErrorRecovery::class);

    $exception = new Exception('Connection failed');
    $suggestion = $recovery->generateRecoverySuggestion($exception);

    expect($suggestion['type'])->toBe('connection')
        ->and($suggestion['actions'])->toContain('Retry the operation in a few moments');
});

it('generates recovery suggestions for rate limit errors', function () {
    $recovery = resolve(SmartErrorRecovery::class);

    $exception = new Exception('API rate_limit exceeded'); // Use "rate_limit" keyword
    $suggestion = $recovery->generateRecoverySuggestion($exception);

    expect($suggestion['type'])->toBe('rate_limit')
        ->and($suggestion['actions'])->toContain('Wait before retrying');
});

it('generates recovery suggestions for authentication errors', function () {
    $recovery = resolve(SmartErrorRecovery::class);

    $exception = new Exception('Authentication failed');
    $suggestion = $recovery->generateRecoverySuggestion($exception);

    expect($suggestion['type'])->toBe('authentication');
});

it('generates recovery suggestions for validation errors', function () {
    $recovery = resolve(SmartErrorRecovery::class);

    $exception = new Exception('Validation failed');
    $suggestion = $recovery->generateRecoverySuggestion($exception);

    expect($suggestion['type'])->toBe('validation');
});

it('generates general recovery suggestions for unknown errors', function () {
    $recovery = resolve(SmartErrorRecovery::class);

    $exception = new Exception('Unknown error');
    $suggestion = $recovery->generateRecoverySuggestion($exception);

    expect($suggestion['type'])->toBe('general')
        ->and($suggestion['actions'])->toContain('Retry the operation');
});

it('registers custom fallback strategies', function () {
    $recovery = resolve(SmartErrorRecovery::class);

    $recovery->registerFallbackStrategy('payment-service', function ($e) {
        return ['error' => 'Payment service down', 'use_cache' => true];
    });

    config(['api-guardian.recovery.max_retries' => 1]);

    $result = $recovery->execute('payment-service', function () {
        throw new Exception('Invalid data');
    });

    expect($result['error'])->toBe('Payment service down')
        ->and($result['use_cache'])->toBeTrue();
});

it('returns circuit breaker status for all services', function () {
    $recovery = resolve(SmartErrorRecovery::class);

    // Create multiple breakers
    CircuitBreaker::getOrCreate('service-1', 'operation-1');
    CircuitBreaker::getOrCreate('service-2', 'operation-2');

    $status = $recovery->getCircuitBreakerStatus();

    expect($status)->toBeArray()
        ->and(count($status))->toBe(2)
        ->and($status[0])->toHaveKeys(['service', 'operation', 'state', 'can_attempt']);
});

it('logs retry attempts', function () {
    config(['api-guardian.recovery.max_retries' => 2]);

    $recovery = resolve(SmartErrorRecovery::class);

    try {
        $recovery->execute('test-service', function () {
            throw new Exception('Network timeout');
        }, 'test-operation');
    } catch (Exception $e) {
        // Expected
    }

    Log::shouldHaveReceived('warning')
        ->atLeast()
        ->once();
});

it('records circuit breaker success after successful retry', function () {
    $recovery = resolve(SmartErrorRecovery::class);
    $attempts = 0;

    $result = $recovery->execute('test-service', function () use (&$attempts) {
        $attempts++;
        if ($attempts === 1) {
            throw new Exception('Temporary network error');
        }

        return 'success';
    });

    expect($result)->toBe('success');

    $breaker = CircuitBreaker::where('service', 'test-service')->first();
    expect($breaker->state)->toBe('closed');
});

it('records circuit breaker failure after max retries', function () {
    config(['api-guardian.recovery.max_retries' => 2]);

    $recovery = resolve(SmartErrorRecovery::class);

    try {
        $recovery->execute('test-service', function () {
            throw new Exception('Connection timeout');
        });
    } catch (Exception $e) {
        // Expected - will use fallback
    }

    $breaker = CircuitBreaker::where('service', 'test-service')->first();
    expect($breaker->failure_count)->toBeGreaterThan(0);
});
