<?php

declare(strict_types=1);

namespace WorkDoneRight\ApiGuardian\Contracts;

use Closure;
use Exception;

interface RecoveryStrategyContract
{
    public function execute(string $service, Closure $operation, ?string $operationName = null): mixed;

    public function registerFallbackStrategy(string $service, Closure $strategy): void;

    public function generateRecoverySuggestion(Exception $exception): array;

    public function getCircuitBreakerStatus(): array;
}
