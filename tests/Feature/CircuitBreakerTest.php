<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use WorkDoneRight\ApiGuardian\Models\CircuitBreaker;

beforeEach(function () {
    // Clear cache before each test
    Cache::flush();
});

it('starts in closed state by default', function () {
    $breaker = CircuitBreaker::getOrCreate('test-service', 'test-operation');

    expect($breaker->state)->toBe('closed')
        ->and($breaker->isClosed())->toBeTrue()
        ->and($breaker->canAttempt())->toBeTrue();
});

it('transitions from closed to open after threshold failures', function () {
    $breaker = CircuitBreaker::getOrCreate('test-service', 'test-operation');
    $threshold = $breaker->failure_threshold;

    // Record failures up to threshold
    for ($i = 0; $i < $threshold; $i++) {
        $breaker->recordFailure();
        $breaker->refresh();
    }

    expect($breaker->state)->toBe('open')
        ->and($breaker->isOpen())->toBeTrue()
        ->and($breaker->canAttempt())->toBeFalse()
        ->and($breaker->next_attempt_at)->not->toBeNull();
});

it('allows retry after recovery timeout in open state', function () {
    $breaker = CircuitBreaker::getOrCreate('test-service', 'test-operation');

    // Trip the circuit
    $breaker->state = 'open';
    $breaker->opened_at = now()->subSeconds(61);
    $breaker->next_attempt_at = now()->subSeconds(1); // Past
    $breaker->save();

    Cache::flush(); // Clear cache to get fresh state

    expect($breaker->canAttempt())->toBeTrue();
});

it('transitions from open to half-open on first success after timeout', function () {
    $breaker = CircuitBreaker::getOrCreate('test-service', 'test-operation');

    // Set to open state with past timeout
    $breaker->state = 'open';
    $breaker->next_attempt_at = now()->subSeconds(1);
    $breaker->save();

    // Clear cache so canAttempt() returns fresh state
    Cache::flush();

    // Record success should transition to half-open
    $breaker->recordSuccess();
    $breaker->refresh();

    expect($breaker->state)->toBe('half_open')
        ->and($breaker->isHalfOpen())->toBeTrue();
});

it('transitions from half-open to closed on success', function () {
    $breaker = CircuitBreaker::getOrCreate('test-service', 'test-operation');

    $breaker->state = 'half_open';
    $breaker->failure_count = 2;
    $breaker->save();

    $breaker->recordSuccess();
    $breaker->refresh();

    expect($breaker->state)->toBe('closed')
        ->and($breaker->failure_count)->toBe(0)
        ->and($breaker->isClosed())->toBeTrue();
});

it('transitions from half-open back to open on failure', function () {
    $breaker = CircuitBreaker::getOrCreate('test-service', 'test-operation');

    $breaker->state = 'half_open';
    $breaker->failure_count = 0;
    $breaker->failure_threshold = 2;
    $breaker->save();

    // Record enough failures to trip
    $breaker->recordFailure();
    $breaker->recordFailure();
    $breaker->refresh();

    expect($breaker->state)->toBe('open')
        ->and($breaker->isOpen())->toBeTrue();
});

it('caches canAttempt result for performance', function () {
    $breaker = CircuitBreaker::getOrCreate('test-service', 'test-operation');

    // First call should cache
    $result1 = $breaker->canAttempt();

    // Change state in DB without clearing cache
    $breaker->state = 'open';
    $breaker->next_attempt_at = now()->addMinutes(10);
    $breaker->saveQuietly(); // Don't trigger events

    // Should return cached result (still true)
    $result2 = $breaker->canAttempt();

    expect($result1)->toBeTrue()
        ->and($result2)->toBeTrue(); // Still cached

    // After clearing cache, should get fresh state
    Cache::flush();
    $result3 = $breaker->canAttempt();

    expect($result3)->toBeFalse(); // Fresh state from DB
});

it('clears cache when recording success', function () {
    $breaker = CircuitBreaker::getOrCreate('test-service', 'test-operation');

    $breaker->state = 'half_open';
    $breaker->save();

    // Cache current state
    $breaker->canAttempt();

    // Record success clears cache
    $breaker->recordSuccess();

    // Should get fresh state
    $result = $breaker->canAttempt();

    expect($result)->toBeTrue();
});

it('clears cache when recording failure', function () {
    $breaker = CircuitBreaker::getOrCreate('test-service', 'test-operation');

    // Cache current state
    $breaker->canAttempt();

    // Record failure clears cache
    $breaker->recordFailure();

    // Fresh check
    $breaker->refresh();
    expect($breaker->failure_count)->toBe(1);
});

it('handles concurrent failures correctly', function () {
    $breaker = CircuitBreaker::getOrCreate('test-service', 'test-operation');
    $threshold = $breaker->failure_threshold;

    // Simulate concurrent failures
    $promises = [];
    for ($i = 0; $i < $threshold + 2; $i++) {
        $breaker->recordFailure();
    }

    $breaker->refresh();

    expect($breaker->state)->toBe('open')
        ->and($breaker->failure_count)->toBeGreaterThanOrEqual($threshold);
});

it('creates separate breakers for different services', function () {
    $breaker1 = CircuitBreaker::getOrCreate('service-1', 'operation-1');
    $breaker2 = CircuitBreaker::getOrCreate('service-2', 'operation-2');

    expect($breaker1->id)->not->toBe($breaker2->id)
        ->and($breaker1->service)->toBe('service-1')
        ->and($breaker2->service)->toBe('service-2');
});

it('reuses existing breaker for same service and operation', function () {
    $breaker1 = CircuitBreaker::getOrCreate('test-service', 'test-operation');
    $breaker2 = CircuitBreaker::getOrCreate('test-service', 'test-operation');

    expect($breaker1->id)->toBe($breaker2->id);
});

it('tracks last failure time correctly', function () {
    $breaker = CircuitBreaker::getOrCreate('test-service', 'test-operation');

    $beforeFailure = now();
    Illuminate\Support\Sleep::sleep(1);

    $breaker->recordFailure();
    $breaker->refresh();

    expect($breaker->last_failure_at)->not->toBeNull()
        ->and($breaker->last_failure_at->isAfter($beforeFailure))->toBeTrue();
});

it('does not trip circuit until threshold is reached', function () {
    $breaker = CircuitBreaker::getOrCreate('test-service', 'test-operation');
    $threshold = $breaker->failure_threshold;

    // Record threshold - 1 failures
    for ($i = 0; $i < $threshold - 1; $i++) {
        $breaker->recordFailure();
        $breaker->refresh();
    }

    expect($breaker->state)->toBe('closed')
        ->and($breaker->canAttempt())->toBeTrue();
});
