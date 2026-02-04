<?php

declare(strict_types=1);

namespace WorkDoneRight\ApiGuardian\Support;

use WorkDoneRight\ApiGuardian\Concerns\Config\HandlesSecurityConfig;

/**
 * Redacts Personally Identifiable Information (PII) from strings
 *
 * Prevents PII like email addresses, phone numbers, and IP addresses
 * from being exposed in error responses using smart partial masking.
 */
final class PIIRedactor
{
    use HandlesSecurityConfig;

    /**
     * Redact PII from a string using smart partial masking
     *
     * Examples:
     *
     * - Email: test@example.com → t***@example.com
     * - Phone: +1234567890 → ******7890
     * - IP: 192.168.1.100 → 192.*.*.*
     */
    public function redact(string $text): string
    {
        if (! $this->isPiiRedactionEnabled()) {
            return $text;
        }

        // Process IPs FIRST (before phones to avoid conflicts)
        $text = preg_replace_callback(
            '/\b(?:\d{1,3}\.){3}\d{1,3}\b/',
            fn ($matches): string => $this->maskIP($matches[0]),
            $text
        );

        // Mask emails
        $text = preg_replace_callback(
            '/([a-zA-Z0-9._%+-]+)@([a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/',
            fn ($matches): string => $this->maskEmail($matches[0]),
            (string) $text
        );

        // Mask phones (more specific pattern to avoid matching bare numbers)
        // Requires + prefix or specific phone-like patterns
        $text = preg_replace_callback(
            '/\+[1-9]\d{7,14}/',  // International format with + prefix
            fn ($matches): string => $this->maskPhone($matches[0]),
            (string) $text
        );

        return $text;
    }

    /**
     * Redact PII from an array recursively using smart partial masking
     */
    public function redactArray(array $data): array
    {
        if (! $this->isPiiRedactionEnabled()) {
            return $data;
        }

        array_walk_recursive($data, function (&$value): void {
            if (is_string($value)) {
                $value = $this->redact($value);
            }
        });

        return $data;
    }

    /**
     * Partially mask email address (keep first char and domain)
     *
     * Example: john@example.com → j***@example.com
     */
    private function maskEmail(string $email): string
    {
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $email;
        }

        [$local, $domain] = explode('@', $email);

        $maskedLocal = mb_substr($local, 0, 1).str_repeat('*', min(mb_strlen($local) - 1, 5));

        return $maskedLocal.'@'.$domain;
    }

    /**
     * Partially mask phone number (keep last 4 digits)
     * Example: +12345671234 → *******1234
     */
    private function maskPhone(string $phone): string
    {
        // Keep only digits
        $digits = preg_replace('/\D/', '', $phone);

        if (mb_strlen((string) $digits) < 4) {
            return str_repeat('*', mb_strlen((string) $digits));
        }

        $lastFour = mb_substr((string) $digits, -4);
        $masked = str_repeat('*', mb_strlen((string) $digits) - 4);

        return $masked.$lastFour;
    }

    /**
     * Partially mask IP address (keep first octet)
     * Example: 192.168.1.100 → 192.*.*.*
     */
    private function maskIP(string $ip): string
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }

        $parts = explode('.', $ip);

        if (count($parts) !== 4) {
            return $ip;
        }

        return $parts[0].'.*.*.*';
    }
}
