<?php

declare(strict_types=1);

namespace WorkDoneRight\ApiGuardian\Concerns\Config;

trait HandlesContextConfig
{
    protected function shouldIncludeErrorId(): bool
    {
        return config('api-guardian.context.include_error_id', false);
    }

    /**
     * Determine if debug information should be included.
     */
    protected function shouldIncludeDebugInfo(): bool
    {
        return config('api-guardian.context.include_debug_info', false);
    }

    protected function shouldIncludeTraceInfo(): bool
    {
        return config('api-guardian.context.include_trace', false);
    }

    protected function shouldIncludeQueryInfo(): bool
    {
        return config('api-guardian.context.include_queries', false);
    }

    protected function shouldIncludeErrorCodes(): bool
    {
        return config('api-guardian.context.include_error_codes', false);
    }

    protected function shouldIncludeSuggestions(): bool
    {
        return config('api-guardian.context.include_suggestions', false);
    }

    protected function shouldIncludeTimestamp(): bool
    {
        return config('api-guardian.context.include_timestamp', false);
    }

    protected function shouldIncludeUserInfo(): bool
    {
        return config('api-guardian.context.include_user_info', false);
    }

    protected function shouldIncludeRequestId(): bool
    {
        return config('api-guardian.context.include_request_id', false);
    }

    protected function shouldIncludeMemoryUsage(): bool
    {
        return config('api-guardian.context.include_memory', false);
    }
}
