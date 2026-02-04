<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use WorkDoneRight\ApiGuardian\Models\ApiError;
use WorkDoneRight\ApiGuardian\Services\ErrorCollector;

beforeEach(function () {
    $this->errorCollector = resolve(ErrorCollector::class);
});

test('can collect error data from request', function () {
    $request = Request::create('/api/test', 'POST', ['test' => 'data']);
    $request->headers->set('Accept', 'application/json');
    $request->headers->set('Authorization', 'Bearer secret-token');

    $errorData = [
        'error_id' => 'test_error_123',
        'exception_class' => 'Exception',
        'error_code' => 'TEST_ERROR',
        'message' => 'Test error message',
        'status_code' => 500,
        'context' => ['file' => 'test.php', 'line' => 123],
    ];

    $apiError = $this->errorCollector->collect($errorData, $request);

    expect($apiError)->toBeInstanceOf(ApiError::class)
        ->and($apiError->error_id)->toBe('test_error_123')
        ->and($apiError->exception_class)->toBe('Exception')
        ->and($apiError->error_code)->toBe('TEST_ERROR')
        ->and($apiError->message)->toBe('Test error message')
        ->and($apiError->status_code)->toBe(500)
        ->and($apiError->request_method)->toBe('POST')
        ->and($apiError->request_url)->toBe('http://localhost/api/test')
        ->and($apiError->request_data['test'])->toBe('data')
        ->and($apiError->request_data['test'])->toBe('data')
        ->and($apiError->request_headers)->toBeArray(); // Check headers are stored as array
});

test('can increment occurrence count for existing errors', function () {
    $existingError = ApiError::factory()->create([
        'error_id' => 'existing_error_123',
        'occurrence_count' => 3,
    ]);

    $request = Request::create('/api/test');
    $errorData = [
        'error_id' => 'existing_error_123',
        'exception_class' => 'Exception',
        'message' => 'Existing error message',
        'status_code' => 500,
    ];

    $apiError = $this->errorCollector->collect($errorData, $request);

    expect($apiError->id)->toBe($existingError->id)
        ->and($apiError->occurrence_count)->toBe(4);
});

test('can generate analytics data', function () {
    // Create test data
    ApiError::factory()->count(10)->create(['created_at' => now()->subDays(3)]);
    ApiError::factory()->count(5)->create(['created_at' => now()->subDays(1), 'status_code' => 404]);
    ApiError::factory()->count(3)->unresolved()->create(['created_at' => now()]);

    $analytics = $this->errorCollector->getAnalytics(7);

    expect($analytics)->toHaveKey('total_errors')
        ->and($analytics)->toHaveKey('unique_errors')
        ->and($analytics)->toHaveKey('unresolved_errors')
        ->and($analytics)->toHaveKey('top_errors')
        ->and($analytics)->toHaveKey('errors_by_status')
        ->and($analytics)->toHaveKey('trend_data')
        ->and($analytics['total_errors'])->toBe(18);
});

test('can get live errors with limit', function () {
    ApiError::factory()->count(100)->create(['created_at' => now()]);

    $liveErrors = $this->errorCollector->getLiveErrors(10);

    expect($liveErrors)->toHaveCount(10)
        ->and(array_keys($liveErrors[0]))->toContain(
            'id', 'error_id', 'message', 'error_code', 'status_code',
            'occurrence_count', 'request_url', 'request_method', 'ip_address',
            'created_at', 'is_resolved'
        );
});

test('redacts sensitive data from requests', function () {
    $request = Request::create('/api/login', 'POST', [
        'email' => 'test@example.com',
        'password' => 'secret123',
        'api_key' => 'sk-test123',
    ]);

    $errorData = [
        'error_id' => 'sensitive_test_123',
        'exception_class' => 'Exception',
        'message' => 'Test with sensitive data',
        'status_code' => 400,
    ];

    $apiError = $this->errorCollector->collect($errorData, $request);

    expect($apiError->request_data['email'])->toBe('test@example.com')
        ->and($apiError->request_data['password'])->toBe('[REDACTED]')
        ->and($apiError->request_data['api_key'])->toBe('[REDACTED]');
});
