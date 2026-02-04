<?php

declare(strict_types=1);

namespace WorkDoneRight\ApiGuardian\Http\Controllers\Api;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use WorkDoneRight\ApiGuardian\Models\CircuitBreaker;

final class HealthController
{
    /**
     * Get system health status.
     */
    public function index(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'circuit_breakers' => $this->checkCircuitBreakers(),
        ];

        $isHealthy = ! in_array(false, array_column($checks, 'healthy'), true);

        return response()->json([
            'status' => $isHealthy ? 'healthy' : 'degraded',
            'timestamp' => now()->toIso8601String(),
            'checks' => $checks,
        ], $isHealthy ? 200 : 503);
    }

    /**
     * Check database connection.
     */
    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();

            return [
                'healthy' => true,
                'message' => 'Database connection successful',
            ];
        } catch (Exception $e) {
            return [
                'healthy' => false,
                'message' => 'Database connection failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check cache system.
     */
    private function checkCache(): array
    {
        try {
            $key = 'api-guardian-health-check';
            $value = 'ok';

            Cache::put($key, $value, 60);
            $retrieved = Cache::get($key);
            Cache::forget($key);

            return [
                'healthy' => $retrieved === $value,
                'message' => $retrieved === $value ? 'Cache working' : 'Cache test failed',
            ];
        } catch (Exception $e) {
            return [
                'healthy' => false,
                'message' => 'Cache system failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check circuit breaker status.
     */
    private function checkCircuitBreakers(): array
    {
        try {
            $breakers = CircuitBreaker::all();
            $total = $breakers->count();
            $open = $breakers->where('state', 'open')->count();
            $healthy = $breakers->where('state', 'closed')->count();

            return [
                'healthy' => $open === 0,
                'message' => $open === 0
                    ? 'All circuit breakers closed'
                    : "{$open} circuit breaker(s) open",
                'stats' => [
                    'total' => $total,
                    'healthy' => $healthy,
                    'open' => $open,
                    'half_open' => $breakers->where('state', 'half_open')->count(),
                ],
                'details' => $breakers->where('state', '!=', 'closed')->map(fn ($breaker) => [
                    'service' => $breaker->service,
                    'operation' => $breaker->operation,
                    'state' => $breaker->state,
                    'can_attempt' => $breaker->canAttempt(),
                ])->values()->toArray(),
            ];
        } catch (Exception $e) {
            return [
                'healthy' => false,
                'message' => 'Circuit breaker check failed',
                'error' => $e->getMessage(),
            ];
        }
    }
}
