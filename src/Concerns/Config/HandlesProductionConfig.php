<?php

declare(strict_types=1);

namespace WorkDoneRight\ApiGuardian\Concerns\Config;

trait HandlesProductionConfig
{
    /**
     * Determine if exception messages should be hidden in production.
     */
    protected function shouldHideExceptionMessage(): bool
    {
        return ! config('app.debug')
            && config('api-guardian.production.hide_exception_message', false);
    }

    /**
     * Get the generic error message for production.
     */
    protected function getGenericErrorMessage(): string
    {
        return config('api-guardian.production.generic_message', 'An error occurred.');
    }

    /**
     * Determine if SQL queries should be sanitized in production.
     */
    protected function shouldSanitizeSql(): bool
    {
        return config('api-guardian.production.sanitize_sql', false);
    }

    /**
     * Determine if sensitive data should be masked in production.
     */
    protected function shouldMaskSensitiveDataInProduction(): bool
    {
        return config('api-guardian.production.mask_sensitive_data', false);
    }

    /**
     * Get the breadcrumb count limit.
     */
    protected function getBreadcrumbCount(): int
    {
        return config('api-guardian.production.breadcrumb_count', 10);
    }

    /**
     * Determine if we are in production mode.
     */
    protected function isProduction(): bool
    {
        return ! config('app.debug');
    }
}
