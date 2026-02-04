<?php

declare(strict_types=1);

namespace WorkDoneRight\ApiGuardian\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use WorkDoneRight\ApiGuardian\Models\ApiError;

final class ErrorService
{
    /**
     * Get paginated errors with filters.
     */
    public function getErrors(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        // Validate per page range
        if ($perPage < 1 || $perPage > 100) {
            throw new InvalidArgumentException('Per page must be between 1 and 100');
        }

        return ApiError::with('trends')
            ->latest()
            ->when(
                Arr::get($filters, 'status') === 'resolved',
                fn ($query) => $query->resolved()
            )
            ->when(
                Arr::get($filters, 'status') === 'unresolved',
                fn ($query) => $query->unresolved()
            )
            ->when(
                Arr::has($filters, 'status_code'),
                fn ($query) => $query->statusCode((int) Arr::get($filters, 'status_code'))
            )
            ->when(
                Arr::has($filters, 'search'),
                fn ($query) => $query->where(function ($q) use ($filters): void {
                    $search = Arr::get($filters, 'search');
                    $q->where('message', 'like', sprintf('%%%s%%', $search))
                        ->orWhere('endpoint', 'like', sprintf('%%%s%%', $search))
                        ->orWhere('code', 'like', sprintf('%%%s%%', $search));
                })
            )
            ->when(
                Arr::has($filters, 'from_date'),
                fn ($query) => $query->where('created_at', '>=', Arr::get($filters, 'from_date'))
            )
            ->when(
                Arr::has($filters, 'to_date'),
                fn ($query) => $query->where('created_at', '<=', Arr::get($filters, 'to_date'))
            )
            ->paginate($perPage);
    }

    /**
     * Find an error by ID.
     */
    public function findError(string $id): ApiError
    {
        return ApiError::with('trends')->findOrFail($id);
    }

    /**
     * Get related errors for a given error.
     */
    public function getRelatedErrors(ApiError $error, int $limit = 5): Collection
    {
        return ApiError::where('endpoint', $error->endpoint)
            ->where('id', '!=', $error->id)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Resolve an error.
     */
    public function resolveError(string $id): ApiError
    {
        $error = ApiError::findOrFail($id);

        // Check if already resolved
        if ($error->resolved_at) {
            throw new InvalidArgumentException(sprintf('Error %s is already resolved', $id));
        }

        $error->resolved_at = now();
        $error->save();

        return $error;
    }

    /**
     * Delete an error.
     */
    public function deleteError(string $id): bool
    {
        $error = ApiError::findOrFail($id);

        return (bool) $error->delete();
    }

    /**
     * Bulk resolve errors.
     */
    public function bulkResolveErrors(array $ids): bool
    {
        // Validate IDs array
        if ($ids === []) {
            throw new InvalidArgumentException('IDs array cannot be empty');
        }

        if (count($ids) > 100) {
            throw new InvalidArgumentException('Cannot resolve more than 100 errors at once');
        }

        return ApiError::whereIn('id', $ids)
            ->whereNull('resolved_at')
            ->update(['resolved_at' => now()]);
    }

    /**
     * Bulk delete errors.
     */
    public function bulkDeleteErrors(array $ids): bool
    {
        // Validate IDs array
        if ($ids === []) {
            throw new InvalidArgumentException('IDs array cannot be empty');
        }

        if (count($ids) > 100) {
            throw new InvalidArgumentException('Cannot delete more than 100 errors at once');
        }

        return ApiError::whereIn('id', $ids)->delete();
    }

    /**
     * Get error statistics.
     *
     * @return array<string, mixed>
     */
    public function getStats(): array
    {
        return [
            'total' => ApiError::count(),
            'unresolved' => ApiError::unresolved()->count(),
            'resolved' => ApiError::resolved()->count(),
            'today' => ApiError::whereDate('created_at', today())->count(),
            'this_week' => ApiError::where('created_at', '>=', now()->startOfWeek())->count(),
            'this_month' => ApiError::where('created_at', '>=', now()->startOfMonth())->count(),
        ];
    }

    /**
     * Get errors for export.
     */
    public function getErrorsForExport(int $days): array
    {
        // Validate days range
        if ($days < 1 || $days > 365) {
            throw new InvalidArgumentException('Days must be between 1 and 365');
        }

        return ApiError::where('created_at', '>=', now()->subDays($days))
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($error): array => [
                'id' => $error->id,
                'error_code' => $error->error_code,
                'message' => $error->message,
                'status_code' => $error->status_code,
                'request_url' => $error->request_url,
                'request_method' => $error->request_method,
                'occurrence_count' => $error->occurrence_count,
                'created_at' => $error->created_at?->toIso8601String(),
                'updated_at' => $error->updated_at?->toIso8601String(),
                'is_resolved' => (bool) $error->resolved_at,
            ])
            ->toArray();
    }
}
