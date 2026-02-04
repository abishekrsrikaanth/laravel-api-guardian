<?php

declare(strict_types=1);

namespace WorkDoneRight\ApiGuardian\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use WorkDoneRight\ApiGuardian\Services\CircuitBreakerService;

final class CircuitBreakerController extends Controller
{
    public function __construct(
        private readonly CircuitBreakerService $circuitBreakerService
    ) {}

    /**
     * List all circuit breakers.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'state' => ['sometimes', 'string', Rule::in(['open', 'half_open', 'closed'])],
            'service' => ['sometimes', 'string', 'max:255'],
        ]);

        $filters = [
            'state' => $validated['state'] ?? null,
            'service' => $validated['service'] ?? null,
        ];

        $breakers = $this->circuitBreakerService->getCircuitBreakers(array_filter($filters));

        return response()->json([
            'success' => true,
            'data' => $breakers,
        ]);
    }

    /**
     * Get a specific circuit breaker.
     */
    public function show(string $id): JsonResponse
    {
        // ID validation happens in service (findOrFail)
        $breaker = $this->circuitBreakerService->findCircuitBreaker($id);

        return response()->json([
            'success' => true,
            'data' => $breaker,
        ]);
    }

    /**
     * Reset a circuit breaker to closed state.
     */
    public function reset(string $id): JsonResponse
    {
        // ID validation happens in service (findOrFail)
        $breaker = $this->circuitBreakerService->resetCircuitBreaker($id);

        return response()->json([
            'success' => true,
            'message' => 'Circuit breaker reset to closed state',
            'data' => $breaker,
        ]);
    }

    /**
     * Test a circuit breaker (manual trigger).
     */
    public function test(string $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'string', Rule::in(['success', 'failure'])],
        ]);

        // ID validation happens in service (findOrFail)
        $breaker = $this->circuitBreakerService->testCircuitBreaker($id, $validated['action']);

        $message = $validated['action'] === 'success'
            ? 'Success recorded for circuit breaker'
            : 'Failure recorded for circuit breaker';

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $breaker,
        ]);
    }

    /**
     * Get circuit breaker statistics.
     */
    public function stats(): JsonResponse
    {
        $stats = $this->circuitBreakerService->getStats();

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Get circuit breaker history/timeline.
     */
    public function history(string $id): JsonResponse
    {
        // ID validation happens in service (findOrFail)
        $history = $this->circuitBreakerService->getHistory($id);

        return response()->json([
            'success' => true,
            'data' => $history,
        ]);
    }
}
