<?php

declare(strict_types=1);

namespace WorkDoneRight\ApiGuardian\Contracts;

use Illuminate\Http\Request;
use WorkDoneRight\ApiGuardian\Models\ApiError;

interface ErrorCollectorContract
{
    public function collect(array $errorData, Request $request): ApiError;

    public function getAnalytics(int $days = 7): array;

    public function getLiveErrors(int $limit = 50): array;
}
