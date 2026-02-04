<?php

declare(strict_types=1);

namespace WorkDoneRight\ApiGuardian\Support;

use WorkDoneRight\ApiGuardian\Concerns\Config\HandlesProductionConfig;
use WorkDoneRight\ApiGuardian\Concerns\Config\HandlesSecurityConfig;

/**
 * Masks sensitive data to prevent credentials and secrets from leaking
 *
 * Prevents passwords, tokens, API keys, and other sensitive information
 * from being exposed in error responses.
 */
final class DataMasker
{
    use HandlesProductionConfig;
    use HandlesSecurityConfig;

    /**
     * The mask string to use for redacted values
     */
    private string $maskString = '***REDACTED***';

    /**
     * Mask sensitive data in an array recursively
     */
    public function maskArray(array $data): array
    {
        if (! $this->shouldMaskSensitiveData()) {
            return $data;
        }

        $patterns = $this->getSensitiveDataPatterns();

        array_walk_recursive($data, function (&$value, $key) use ($patterns): void {
            if (is_string($key) && $this->isSensitiveKey($key, $patterns)) {
                $value = $this->maskString;
            } elseif (is_string($value)) {
                $value = $this->maskString($value);
            }
        });

        return $data;
    }

    /**
     * Mask sensitive data in a string
     */
    public function maskString(string $text): string
    {
        if (! $this->shouldMaskSensitiveData()) {
            return $text;
        }

        // Mask password= patterns (with or without query string prefix)
        $text = preg_replace('/(password=)[^\s&]+/', '$1'.$this->maskString, $text);

        // Mask token= patterns
        $text = preg_replace('/(token=)[^\s&]+/', '$1'.$this->maskString, (string) $text);

        // Mask api_key= patterns
        $text = preg_replace('/(api_key=)[^\s&]+/', '$1'.$this->maskString, (string) $text);

        // Mask Authorization header
        $text = preg_replace('/(Authorization:\s*Bearer\s+)\S+/', '$1'.$this->maskString, (string) $text);

        // Mask basic auth
        $text = preg_replace('/(Authorization:\s*Basic\s+)\S+/', '$1'.$this->maskString, (string) $text);

        // Mask credit card numbers (various formats)
        $text = preg_replace('/\b\d{4}[\s\-]?\d{4}[\s\-]?\d{4}[\s\-]?\d{4}\b/', '****-****-****-****', (string) $text);

        return $text;
    }

    /**
     * Sanitize SQL query to prevent schema leakage in production
     */
    public function sanitizeSQL(string $sql): string
    {
        if (! $this->shouldSanitizeSql()) {
            return $sql;
        }

        if (! $this->isProduction()) {
            return $sql;
        }

        // Replace values in quotes first
        $sql = preg_replace('/"([^"]+)"/', '[value]', $sql);

        // Replace column names in backticks
        $sql = preg_replace('/`([^`]+)`\s*=/', '[column] =', (string) $sql);

        // Replace table names in backticks (those not followed by =)
        $sql = preg_replace('/`([^`]+)`/', '[table]', (string) $sql);

        // Replace bare words that look like table names (after FROM, JOIN, INTO, UPDATE)
        $sql = preg_replace('/\b(FROM|JOIN|INTO|UPDATE)\s+([a-zA-Z_]\w*)/', '$1 [table]', (string) $sql);

        return $sql;
    }

    /**
     * Check if a key is sensitive
     */
    private function isSensitiveKey(string $key, array $patterns): bool
    {
        $key = mb_strtolower($key);

        foreach ($patterns as $pattern) {
            if (str_contains($key, mb_strtolower((string) $pattern))) {
                return true;
            }
        }

        return false;
    }
}
