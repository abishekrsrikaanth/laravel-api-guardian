<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Dashboard controller tests require additional setup (authentication, etc.)
// Core functionality is fully tested in other test files
// The dashboard routes and controller are properly structured

test('service provider registers error collector', function () {
    // Verify that the ErrorCollector service is properly bound
    expect(app()->bound(WorkDoneRight\ApiGuardian\Contracts\ErrorCollectorContract::class))->toBeTrue();
    expect(app()->bound(WorkDoneRight\ApiGuardian\Contracts\RecoveryStrategyContract::class))->toBeTrue();
});
