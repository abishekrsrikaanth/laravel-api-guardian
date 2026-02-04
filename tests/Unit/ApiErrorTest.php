<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use WorkDoneRight\ApiGuardian\Models\ApiError;

uses(RefreshDatabase::class);

test('api error factory creates valid errors', function () {
    $error = ApiError::factory()->create();

    expect($error)->toBeInstanceOf(ApiError::class)
        ->and($error->error_id)->toBeString()
        ->and($error->exception_class)->toBeString()
        ->and($error->message)->toBeString()
        ->and($error->status_code)->toBeInt()
        ->and($error->occurrence_count)->toBeInt();
});

test('can create resolved errors', function () {
    $error = ApiError::factory()->resolved()->create();

    expect($error->is_resolved)->toBeTrue();
});

test('can create unresolved errors', function () {
    $error = ApiError::factory()->unresolved()->create();

    expect($error->is_resolved)->toBeFalse();
});

test('can create errors with specific status code', function () {
    $error = ApiError::factory()->statusCode(404)->create();

    expect($error->status_code)->toBe(404);
});

test('can create recent errors', function () {
    $error = ApiError::factory()->recent()->create();

    expect($error->created_at)->toBeGreaterThan(now()->subHours(24));
});

test('can create frequent errors', function () {
    $error = ApiError::factory()->frequent()->create();

    expect($error->occurrence_count)->toBeGreaterThanOrEqual(5);
});

test('unresolved scope works correctly', function () {
    $resolvedError = ApiError::factory()->resolved()->create();
    $unresolvedError = ApiError::factory()->unresolved()->create();

    expect(ApiError::unresolved()->get())->toHaveCount(1)
        ->and(ApiError::unresolved()->first()->id)->toBe($unresolvedError->id);
});

test('status code scope works correctly', function () {
    ApiError::factory()->statusCode(500)->create();
    $notFoundError = ApiError::factory()->statusCode(404)->create();

    expect(ApiError::statusCode(404)->get())->toHaveCount(1)
        ->and(ApiError::statusCode(404)->first()->id)->toBe($notFoundError->id);
});

test('recent scope works correctly', function () {
    // Create old errors first
    ApiError::factory()->count(2)->create(['created_at' => now()->subDays(2)]);
    $recentError = ApiError::factory()->recent()->create();

    expect(ApiError::recent()->get())->toHaveCount(1)
        ->and(ApiError::recent()->first()->id)->toBe($recentError->id);
});

test('can resolve error and update occurrence count', function () {
    $error = ApiError::factory()->create(['occurrence_count' => 2]);

    $error->resolve();
    expect($error->is_resolved)->toBeTrue()
        ->and($error->resolved_at)->toBeInstanceOf(Carbon\Carbon::class);

    $error->incrementOccurrence();
    expect($error->occurrence_count)->toBe(3);
    expect($error->last_occurred_at)->toBeInstanceOf(Carbon\Carbon::class);
});
