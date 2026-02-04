<?php

declare(strict_types=1);

namespace WorkDoneRight\ApiGuardian\Concerns\Config;

trait HandlesSecurityConfig
{
    /**
     * Determine if sensitive data should be masked.
     */
    protected function shouldMaskSensitiveData(): bool
    {
        return config('api-guardian.security.mask_sensitive_data', false);
    }

    /**
     * Get the list of sensitive data patterns to mask.
     */
    protected function getSensitiveDataPatterns(): array
    {
        return config('api-guardian.security.mask_patterns', []);
    }

    /**
     * Get the list of sensitive keys to redact.
     */
    protected function getSensitiveKeys(): array
    {
        return config('api-guardian.security.sensitive_keys', [
            'password',
            'password_confirmation',
            'secret',
            'token',
            'api_key',
            'access_token',
            'refresh_token',
            'private_key',
        ]);
    }

    /**
     * Get the list of sensitive headers to redact.
     */
    protected function getSensitiveHeaders(): array
    {
        return config('api-guardian.security.sensitive_headers', [
            'authorization',
            'x-api-key',
            'password',
            'secret',
            'token',
            'cookie',
            'x-csrf-token',
        ]);
    }

    /**
     * Determine if PII redaction is enabled.
     */
    protected function isPiiRedactionEnabled(): bool
    {
        return config('api-guardian.security.pii_redaction.enabled', false);
    }

    /**
     * Get PII redaction patterns.
     */
    protected function getPiiRedactionPatterns(): array
    {
        return config('api-guardian.security.pii_redaction.patterns', [
            'email' => '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/',
            'phone' => '/\+?[1-9]\d{1,14}/',
            'ip' => '/\b(?:\d{1,3}\.){3}\d{1,3}\b/',
        ]);
    }

    /**
     * Determine if request data should be sanitized.
     */
    protected function shouldSanitizeRequestData(): bool
    {
        return config('api-guardian.security.sanitize_request_data', false);
    }
}
