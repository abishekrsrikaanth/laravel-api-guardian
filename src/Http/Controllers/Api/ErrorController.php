<?php

declare(strict_types=1);

namespace WorkDoneRight\ApiGuardian\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use WorkDoneRight\ApiGuardian\Services\ErrorService;

final class ErrorController extends Controller
{
    public function __construct(
        private readonly ErrorService $errorService
    ) {}

    /**
     * List all errors with pagination and filters.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', 'string', Rule::in(['resolved', 'unresolved'])],
            'status_code' => ['sometimes', 'integer', 'between:100,599'],
            'search' => ['sometimes', 'string', 'max:255'],
            'from_date' => ['sometimes', 'date'],
            'to_date' => ['sometimes', 'date', 'after_or_equal:from_date'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $filters = [
            'status' => $validated['status'] ?? null,
            'status_code' => $validated['status_code'] ?? null,
            'search' => $validated['search'] ?? null,
            'from_date' => $validated['from_date'] ?? null,
            'to_date' => $validated['to_date'] ?? null,
        ];

        $perPage = $validated['per_page'] ?? config('api-guardian.dashboard.pagination', 25);
        $errors = $this->errorService->getErrors(array_filter($filters), $perPage);

        return response()->json([
            'success' => true,
            'data' => $errors,
        ]);
    }

    /**
     * Get a specific error by ID.
     */
    public function show(string $id): JsonResponse
    {
        // ID validation happens in service (findOrFail)
        $error = $this->errorService->findError($id);
        $relatedErrors = $this->errorService->getRelatedErrors($error);

        return response()->json([
            'success' => true,
            'data' => [
                'error' => $error,
                'related' => $relatedErrors,
            ],
        ]);
    }

    /**
     * Resolve an error.
     */
    public function resolve(string $id): JsonResponse
    {
        // ID validation happens in service (findOrFail)
        $error = $this->errorService->resolveError($id);

        return response()->json([
            'success' => true,
            'message' => 'Error marked as resolved',
            'data' => $error,
        ]);
    }

    /**
     * Delete an error.
     */
    public function destroy(string $id): JsonResponse
    {
        // ID validation happens in service (findOrFail)
        $this->errorService->deleteError($id);

        return response()->json([
            'success' => true,
            'message' => 'Error deleted successfully',
        ]);
    }

    /**
     * Bulk resolve errors.
     */
    public function bulkResolve(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'string', 'exists:api_errors,id'],
        ]);

        $count = $this->errorService->bulkResolveErrors($validated['ids']);

        return response()->json([
            'success' => true,
            'message' => $count.' errors marked as resolved',
            'count' => $count,
        ]);
    }

    /**
     * Bulk delete errors.
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'string', 'exists:api_errors,id'],
        ]);

        $count = $this->errorService->bulkDeleteErrors($validated['ids']);

        return response()->json([
            'success' => true,
            'message' => $count.' errors deleted successfully',
            'count' => $count,
        ]);
    }

    /**
     * Get error statistics.
     */
    public function stats(): JsonResponse
    {
        $stats = $this->errorService->getStats();

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
