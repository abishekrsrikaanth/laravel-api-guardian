<?php

declare(strict_types=1);

namespace WorkDoneRight\ApiGuardian\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use WorkDoneRight\ApiGuardian\Concerns\Config\HandlesSecurityConfig;
use WorkDoneRight\ApiGuardian\Contracts\ErrorCollectorContract;
use WorkDoneRight\ApiGuardian\Models\ApiError;
use WorkDoneRight\ApiGuardian\Models\ErrorTrend;

final class ErrorCollector implements ErrorCollectorContract
{
    use HandlesSecurityConfig;

    public function collect(array $errorData, Request $request): ApiError
    {
        $errorId = Arr::get($errorData, 'error_id', $this->generateErrorId());

        // Check if this error already exists
        $existingError = ApiError::where('error_id', $errorId)->first();

        if ($existingError) {
            $existingError->incrementOccurrence();

            return $existingError;
        }

        return DB::transaction(function () use ($errorData, $request, $errorId) {
            $apiError = ApiError::create([
                'error_id' => $errorId,
                'exception_class' => Arr::get($errorData, 'exception_class'),
                'error_code' => Arr::get($errorData, 'error_code'),
                'message' => Arr::get($errorData, 'message'),
                'status_code' => Arr::get($errorData, 'status_code'),
                'context' => Arr::get($errorData, 'context'),
                'meta' => Arr::get($errorData, 'meta'),
                'request_method' => $request->method(),
                'request_url' => $request->fullUrl(),
                'request_headers' => $this->sanitizeHeaders($request->headers->all()),
                'request_data' => $this->sanitizeRequestData($request->all()),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'user_id' => auth()->id(),
                'first_occurred_at' => now(),
                'last_occurred_at' => now(),
            ]);

            $this->updateTrends($apiError);

            return $apiError;
        });
    }

    public function getAnalytics(int $days = 7): array
    {
        $startDate = now()->subDays($days);

        return [
            'total_errors' => ApiError::where('created_at', '>=', $startDate)->count(),
            'unique_errors' => ApiError::where('created_at', '>=', $startDate)->distinct('error_id')->count(),
            'unresolved_errors' => ApiError::where('created_at', '>=', $startDate)->unresolved()->count(),
            'top_errors' => ApiError::select('error_code', 'message', DB::raw('count(*) as count'))
                ->where('created_at', '>=', $startDate)
                ->groupBy('error_code', 'message')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->get(),
            'errors_by_status' => ApiError::select('status_code', DB::raw('count(*) as count'))
                ->where('created_at', '>=', $startDate)
                ->groupBy('status_code')
                ->orderBy('count', 'desc')
                ->get(),
            'trend_data' => ErrorTrend::where('date', '>=', $startDate->toDateString())
                ->orderBy('date')
                ->get(),
        ];
    }

    public function getLiveErrors(int $limit = 50): array
    {
        return ApiError::with(['trends'])->latest()
            ->limit($limit)
            ->get()
            ->map(fn ($error): array => [
                'id' => $error->id,
                'error_id' => $error->error_id,
                'message' => $error->message,
                'error_code' => $error->error_code,
                'status_code' => $error->status_code,
                'occurrence_count' => $error->occurrence_count,
                'request_url' => $error->request_url,
                'request_method' => $error->request_method,
                'ip_address' => $error->ip_address,
                'created_at' => $error->created_at->toISOString(),
                'is_resolved' => $error->is_resolved,
            ])
            ->toArray();
    }

    public function getTrendData(int $days = 7, string $groupBy = 'day'): array
    {
        $startDate = now()->subDays($days);

        $format = match ($groupBy) {
            'hour' => '%Y-%m-%d %H:00:00',
            'week' => '%Y-%W',
            default => '%Y-%m-%d',
        };

        $trends = ApiError::where('created_at', '>=', $startDate)
            ->selectRaw("DATE_FORMAT(created_at, '{$format}') as period, COUNT(*) as count")
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        return $trends->map(fn ($trend): array => [
            'period' => $trend->period,
            'count' => $trend->count,
        ])->toArray();
    }

    public function getTopErrors(int $limit = 10, int $days = 7): array
    {
        return ApiError::where('created_at', '>=', now()->subDays($days))
            ->orderBy('occurrence_count', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn ($error): array => [
                'id' => $error->id,
                'message' => $error->message,
                'code' => $error->error_code,
                'status_code' => $error->status_code,
                'count' => $error->occurrence_count,
                'endpoint' => $error->request_url,
            ])
            ->toArray();
    }

    public function getStatusCodeDistribution(int $days = 7): array
    {
        $errors = ApiError::where('created_at', '>=', now()->subDays($days))
            ->selectRaw('status_code, COUNT(*) as count')
            ->groupBy('status_code')
            ->orderBy('count', 'desc')
            ->get();

        return $errors->map(fn ($error): array => [
            'status_code' => $error->status_code,
            'count' => $error->count,
            'percentage' => 0, // Calculate after we have total
        ])->toArray();
    }

    public function getErrorRate(int $days = 7, string $interval = 'hour'): array
    {
        return $this->getTrendData($days, $interval);
    }

    private function generateErrorId(): string
    {
        return 'err_'.uniqid('', true).'_'.time();
    }

    private function sanitizeHeaders(array $headers): array
    {
        $sensitiveHeaders = $this->getSensitiveHeaders();

        return collect($headers)
            ->mapWithKeys(function ($value, $key) use ($sensitiveHeaders): array {
                if (in_array(mb_strtolower($key), $sensitiveHeaders)) {
                    return [$key => '[REDACTED]'];
                }

                return [$key => is_array($value) ? $value[0] : $value];
            })
            ->toArray();
    }

    private function sanitizeRequestData(array $data): array
    {
        return $this->redactSensitiveData($data);
    }

    private function redactSensitiveData(array $data): array
    {
        $sensitiveKeys = $this->getSensitiveKeys();

        array_walk_recursive($data, function (&$value, $key) use ($sensitiveKeys): void {
            if (in_array(mb_strtolower($key), $sensitiveKeys)) {
                $value = '[REDACTED]';
            }
        });

        return $data;
    }

    private function updateTrends(ApiError $apiError): void
    {
        $today = now()->toDateString();
        $errorCode = $apiError->error_code;
        $statusCode = $apiError->status_code;

        $trend = ErrorTrend::firstOrCreate(
            ['date' => $today, 'error_code' => $errorCode, 'status_code' => $statusCode],
            ['count' => 0, 'hourly_distribution' => array_fill(0, 24, 0)]
        );

        $currentHour = now()->hour;
        $hourlyDistribution = $trend->hourly_distribution ?? array_fill(0, 24, 0);
        $hourlyDistribution[$currentHour]++;

        $trend->increment('count');
        $trend->update(['hourly_distribution' => $hourlyDistribution]);
    }
}
