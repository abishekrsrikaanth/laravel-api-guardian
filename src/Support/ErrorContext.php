<?php

declare(strict_types=1);

namespace WorkDoneRight\ApiGuardian\Support;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use WorkDoneRight\ApiGuardian\Concerns\Config\HandlesContextConfig;
use WorkDoneRight\ApiGuardian\Concerns\Config\HandlesSecurityConfig;

final class ErrorContext
{
    use HandlesContextConfig;
    use HandlesSecurityConfig;

    /**
     * Build error context information.
     */
    public static function build(): array
    {
        $instance = new self;
        $context = [];
        if ($instance->shouldIncludeErrorId()) {
            $context = Arr::set($context, 'error_id', self::generateErrorId());
        }

        if ($instance->shouldIncludeTimestamp()) {
            $context = Arr::set($context, 'timestamp', now()->toIso8601String());
        }

        if ($instance->shouldIncludeRequestId()) {
            $context = Arr::set($context, 'request_id', request()->header('X-Request-ID') ?? Str::uuid()->toString());
        }

        if ($instance->shouldIncludeUserInfo() && auth()->check()) {
            return Arr::set($context, 'user', self::buildUserInfo());
        }

        return $context;
    }

    /**
     * Build request metadata.
     *
     * @return array<string, string|null>
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
        $instance = new self;

        if (! $instance->shouldMaskSensitiveData()) {
            return $data;
        }

        $patterns = $instance->getSensitiveDataPatterns();

        if ($patterns === []) {
            return $data;
        }

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data = Arr::set($data, $key, self::sanitize($value));
            } elseif (is_string($value)) {
                foreach ($patterns as $pattern) {
                    if (str_contains(mb_strtolower((string) $key), mb_strtolower((string) $pattern))) {
                        $data = Arr::set($data, $key, '***REDACTED***');
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
        $instance = new self;

        if (! $instance->isPiiRedactionEnabled()) {
            return $text;
        }

        $patterns = $instance->getPiiRedactionPatterns();

        foreach ($patterns as $type => $pattern) {
            $text = preg_replace($pattern, sprintf('[%s:REDACTED]', $type), (string) $text);
        }

        return $text;
    }

    /**
     * Generate a unique error ID.
     */
    private static function generateErrorId(): string
    {
        return 'err_'.Str::random(16);
    }

    /**
     * Build user information (without sensitive data).
     *
     * @return array<string, mixed>
     */
    private static function buildUserInfo(): array
    {
        $user = auth()->user();

        return [
            'id' => Arr::get($user, 'id'),
            'type' => class_basename($user),
        ];
    }
}
