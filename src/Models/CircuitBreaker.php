<?php

declare(strict_types=1);

namespace WorkDoneRight\ApiGuardian\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

final class CircuitBreaker extends Model
{
    use HasFactory;

    protected $table = 'api_guardian_circuit_breakers';

    protected $fillable = [
        'service',
        'operation',
        'state',
        'failure_count',
        'failure_threshold',
        'recovery_timeout',
        'success_threshold',
        'last_failure_at',
        'opened_at',
        'next_attempt_at',
    ];

    protected $casts = [
        'failure_count' => 'integer',
        'failure_threshold' => 'integer',
        'recovery_timeout' => 'integer',
        'success_threshold' => 'integer',
        'last_failure_at' => 'datetime',
        'opened_at' => 'datetime',
        'next_attempt_at' => 'datetime',
    ];

    public static function getOrCreate(string $service, ?string $operation = null): self
    {
        return self::firstOrCreate(
            ['service' => $service, 'operation' => $operation],
            [
                'state' => 'closed',
                'failure_count' => 0,
                'failure_threshold' => config('api-guardian.circuit_breaker.failure_threshold', 5),
                'recovery_timeout' => config('api-guardian.circuit_breaker.recovery_timeout', 60),
                'success_threshold' => config('api-guardian.circuit_breaker.success_threshold', 3),
            ]
        );
    }

    public function isOpen(): bool
    {
        return $this->state === 'open' && $this->next_attempt_at?->isFuture();
    }

    public function isHalfOpen(): bool
    {
        return $this->state === 'half_open';
    }

    public function isClosed(): bool
    {
        return $this->state === 'closed';
    }

    public function canAttempt(): bool
    {
        // Cache the state check for 1 minute to reduce DB load
        return Cache::remember(
            $this->getCacheKey('can_attempt'),
            60,
            fn (): bool => $this->checkCanAttempt()
        );
    }

    public function recordSuccess(): void
    {
        $this->clearCache();

        if ($this->isHalfOpen()) {
            $this->failure_count = 0;
            $this->state = 'closed';
            $this->save();
        } elseif ($this->state === 'open' && $this->checkCanAttempt()) {
            // Check state directly, not isOpen() which checks if still future
            $this->state = 'half_open';
            $this->save();
        }
    }

    public function recordFailure(): void
    {
        $this->clearCache();

        $this->increment('failure_count');
        $this->update(['last_failure_at' => now()]);

        if ($this->failure_count >= $this->failure_threshold) {
            $this->trip();
        }
    }

    /**
     * Internal check without caching
     */
    protected function checkCanAttempt(): bool
    {
        if ($this->isClosed()) {
            return true;
        }

        if ($this->isHalfOpen()) {
            return true;
        }

        return $this->state === 'open' && $this->next_attempt_at?->isPast();
    }

    /**
     * Get cache key for this circuit breaker
     */
    protected function getCacheKey(string $suffix = ''): string
    {
        $key = "circuit_breaker:{$this->service}";
        if ($this->operation) {
            $key .= ":{$this->operation}";
        }
        if ($suffix !== '' && $suffix !== '0') {
            $key .= ":{$suffix}";
        }

        return $key;
    }

    /**
     * Clear cache for this circuit breaker
     */
    protected function clearCache(): void
    {
        Cache::forget($this->getCacheKey('can_attempt'));
        Cache::forget($this->getCacheKey('state'));
    }

    protected function trip(): void
    {
        $this->clearCache();

        $this->state = 'open';
        $this->opened_at = now();
        $this->next_attempt_at = now()->addSeconds($this->recovery_timeout);
        $this->save();
    }
}
