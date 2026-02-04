<?php

declare(strict_types=1);

namespace WorkDoneRight\ApiGuardian\Concerns\Config;

trait HandlesDevelopmentConfig
{
    /**
     * Determine if exception chain should be included.
     */
    protected function shouldIncludeExceptionChain(): bool
    {
        return config('api-guardian.development.include_exception_chain', false);
    }
}
