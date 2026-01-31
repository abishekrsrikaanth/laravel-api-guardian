<?php

namespace WorkDoneRight\ApiGuardian\Support;

use Illuminate\Support\Str;
use Throwable;

class ErrorContext
{
    /**
     * Build error context information.
     */
    public static function build(Throwable $exception): array
    {
        $context = [];

        if (config('api-guardian.context.include_error_id')) {
            $context['error_id'] = static::generateErrorId();
        }

        if (config('api-guardian.context.include_timestamp')) {
            $context['timestamp'] = now()->toIso8601String();
        }

        if (config('api-guardian.context.include_request_id')) {
            $context['request_id'] = request()->header('X-Request-ID') ?? Str::uuid()->toString();
        }

        if (config('api-guardian.context.include_user_info') && auth()->check()) {
            $context['user'] = static::buildUserInfo();
        }

        return $context;
    }

    /**
     * Generate a unique error ID.
     */
    protected static function generateErrorId(): string
    {
        return 'err_'.Str::random(16);
    }

    /**
     * Build user information (without sensitive data).
     */
    protected static function buildUserInfo(): array
    {
        $user = auth()->user();

        return [
            'id' => $user->id ?? null,
            'type' => class_basename($user),
        ];
    }

    /**
     * Build request metadata.
     */
    public static function buildRequestMeta(): array
    {
        $request = request();

        return [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ];
    }

    /**
     * Sanitize sensitive data from context.
     */
    public static function sanitize(array $data): array
    {
        $patterns = config('api-guardian.security.mask_patterns', []);

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = static::sanitize($value);
            } elseif (is_string($value)) {
                foreach ($patterns as $pattern) {
                    if (str_contains(strtolower($key), strtolower($pattern))) {
                        $data[$key] = '***REDACTED***';
                        break;
                    }
                }
            }
        }

        return $data;
    }

    /**
     * Redact PII (Personally Identifiable Information).
     */
    public static function redactPii(string $text): string
    {
        if (! config('api-guardian.security.pii_redaction.enabled')) {
            return $text;
        }

        $patterns = config('api-guardian.security.pii_redaction.patterns', []);

        foreach ($patterns as $type => $pattern) {
            $text = preg_replace($pattern, "[$type:REDACTED]", $text);
        }

        return $text;
    }
}
