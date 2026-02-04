<?php

declare(strict_types=1);

namespace WorkDoneRight\ApiGuardian\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Response as ResponseFacade;
use Illuminate\Validation\Rule;
use WorkDoneRight\ApiGuardian\Services\ErrorCollector;
use WorkDoneRight\ApiGuardian\Services\ErrorService;

final class AnalyticsController extends Controller
{
    public function __construct(
        private readonly ErrorCollector $collector,
        private readonly ErrorService $errorService
    ) {}

    /**
     * Get analytics data for the dashboard.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'days' => ['sometimes', 'integer', 'min:1', 'max:365'],
        ]);

        $days = $validated['days'] ?? 7;
        $analytics = $this->collector->getAnalytics($days);

        return response()->json([
            'success' => true,
            'data' => $analytics,
        ]);
    }

    /**
     * Get trend data for charts.
     */
    public function trends(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'days' => ['sometimes', 'integer', 'min:1', 'max:365'],
            'group_by' => ['sometimes', 'string', Rule::in(['hour', 'day', 'week', 'month'])],
        ]);

        $days = $validated['days'] ?? 7;
        $groupBy = $validated['group_by'] ?? 'day';

        $trends = $this->collector->getTrendData($days, $groupBy);

        return response()->json([
            'success' => true,
            'data' => $trends,
        ]);
    }

    /**
     * Get top errors by occurrence.
     */
    public function topErrors(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'days' => ['sometimes', 'integer', 'min:1', 'max:365'],
        ]);

        $limit = $validated['limit'] ?? 10;
        $days = $validated['days'] ?? 7;

        $topErrors = $this->collector->getTopErrors($limit, $days);

        return response()->json([
            'success' => true,
            'data' => $topErrors,
        ]);
    }

    /**
     * Get error distribution by status code.
     */
    public function distribution(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'days' => ['sometimes', 'integer', 'min:1', 'max:365'],
        ]);

        $days = $validated['days'] ?? 7;
        $distribution = $this->collector->getStatusCodeDistribution($days);

        return response()->json([
            'success' => true,
            'data' => $distribution,
        ]);
    }

    /**
     * Get error rate over time.
     */
    public function errorRate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'days' => ['sometimes', 'integer', 'min:1', 'max:365'],
            'interval' => ['sometimes', 'string', Rule::in(['minute', 'hour', 'day'])],
        ]);

        $days = $validated['days'] ?? 7;
        $interval = $validated['interval'] ?? 'hour';

        $errorRate = $this->collector->getErrorRate($days, $interval);

        return response()->json([
            'success' => true,
            'data' => $errorRate,
        ]);
    }

    /**
     * Export analytics data in various formats.
     */
    public function export(Request $request): Response|JsonResponse
    {
        $request->validate([
            'format' => ['sometimes', 'in:json,csv'],
            'type' => ['sometimes', 'in:errors,analytics,trends'],
            'days' => ['sometimes', 'integer', 'min:1', 'max:365'],
        ]);

        $format = $request->get('format', 'json'); // json, csv
        $days = (int) $request->get('days', 7);
        $type = $request->get('type', 'errors'); // errors, analytics, trends

        $filename = "api-guardian-{$type}-".now()->format('Y-m-d').".{$format}";

        if ($format === 'csv') {
            return $this->exportToCsv($type, $days, $filename);
        }

        // JSON export
        $data = match ($type) {
            'analytics' => $this->collector->getAnalytics($days),
            'trends' => $this->collector->getTrendData($days, 'day'),
            'errors' => $this->getErrorsForExport($days),
        };

        return ResponseFacade::json($data)
            ->header('Content-Type', 'application/json')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    /**
     * Export data to CSV format.
     */
    protected function exportToCsv(string $type, int $days, string $filename): Response
    {
        $data = match ($type) {
            'analytics' => $this->prepareAnalyticsForCsv($days),
            'trends' => $this->prepareTrendsForCsv($days),
            'errors' => $this->prepareErrorsForCsv($days),
        };

        $csv = $this->arrayToCsv($data);

        return ResponseFacade::make($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Prepare analytics data for CSV export.
     */
    protected function prepareAnalyticsForCsv(int $days): array
    {
        $analytics = $this->collector->getAnalytics($days);

        $rows = [
            ['Metric', 'Value'],
            ['Total Errors', Arr::get($analytics, 'total_errors', 0)],
            ['Unique Errors', Arr::get($analytics, 'unique_errors', 0)],
            ['Resolved Errors', Arr::get($analytics, 'resolved_errors', 0)],
            ['Unresolved Errors', Arr::get($analytics, 'unresolved_errors', 0)],
            ['Average per Day', Arr::get($analytics, 'avg_errors_per_day', 0)],
        ];

        // Add top errors if available
        if (Arr::has($analytics, 'top_errors') && is_array(Arr::get($analytics, 'top_errors'))) {
            $rows[] = ['', ''];
            $rows[] = ['Top Errors', 'Occurrences'];
            foreach (Arr::get($analytics, 'top_errors', []) as $error) {
                $rows[] = [
                    Arr::get($error, 'message', 'Unknown'),
                    Arr::get($error, 'occurrence_count', Arr::get($error, 'count', 0)),
                ];
            }
        }

        return $rows;
    }

    /**
     * Prepare trends data for CSV export.
     */
    protected function prepareTrendsForCsv(int $days): array
    {
        $trends = $this->collector->getTrendData($days, 'day');

        $rows = [['Period', 'Error Count']];

        foreach ($trends as $trend) {
            $rows[] = [
                Arr::get($trend, 'period', ''),
                Arr::get($trend, 'count', 0),
            ];
        }

        return $rows;
    }

    /**
     * Prepare errors data for CSV export.
     */
    protected function prepareErrorsForCsv(int $days): array
    {
        $errors = $this->getErrorsForExport($days);

        $rows = [[
            'ID',
            'Error Code',
            'Message',
            'Status Code',
            'Endpoint',
            'Method',
            'Occurrences',
            'First Seen',
            'Last Seen',
            'Resolved',
        ]];

        foreach ($errors as $error) {
            $rows[] = [
                Arr::get($error, 'id', ''),
                Arr::get($error, 'error_code', ''),
                Arr::get($error, 'message', ''),
                Arr::get($error, 'status_code', ''),
                Arr::get($error, 'request_url', ''),
                Arr::get($error, 'request_method', ''),
                Arr::get($error, 'occurrence_count', 0),
                Arr::get($error, 'created_at', ''),
                Arr::get($error, 'updated_at', ''),
                Arr::get($error, 'is_resolved') ? 'Yes' : 'No',
            ];
        }

        return $rows;
    }

    /**
     * Get errors for export.
     */
    protected function getErrorsForExport(int $days): array
    {
        return $this->errorService->getErrorsForExport($days);
    }

    /**
     * Convert array to CSV string.
     */
    protected function arrayToCsv(array $data): string
    {
        $output = fopen('php://temp', 'r+');

        foreach ($data as $row) {
            fputcsv($output, $row);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }
}
