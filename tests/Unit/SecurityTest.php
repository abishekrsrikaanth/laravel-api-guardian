<?php

declare(strict_types=1);

use WorkDoneRight\ApiGuardian\Exceptions\ApiException;
use WorkDoneRight\ApiGuardian\Formatters\GraphQLFormatter;
use WorkDoneRight\ApiGuardian\Formatters\JSendFormatter;
use WorkDoneRight\ApiGuardian\Formatters\JsonApiFormatter;
use WorkDoneRight\ApiGuardian\Formatters\Rfc7807Formatter;
use WorkDoneRight\ApiGuardian\Support\DataMasker;
use WorkDoneRight\ApiGuardian\Support\PIIRedactor;

describe('Security - Data Masking', function () {
    beforeEach(function () {
        config(['api-guardian.security.mask_sensitive_data' => true]);
        $this->dataMasker = resolve(DataMasker::class);
    });

    it('masks passwords in array data', function () {
        $data = [
            'user' => [
                'name' => 'John',
                'password' => 'secret123',
                'email' => 'john@example.com',
            ],
        ];

        $masked = $this->dataMasker->maskArray($data);

        expect($masked['user']['password'])->toBe('***REDACTED***')
            ->and($masked['user']['name'])->toBe('John')
            ->and($masked['user']['email'])->toBe('john@example.com');
    });

    it('masks tokens in array data', function () {
        $data = [
            'api_key' => 'sk_test_123456',
            'access_token' => 'eyJhbGciOi...',
            'name' => 'Test',
        ];

        $masked = $this->dataMasker->maskArray($data);

        expect($masked['api_key'])->toBe('***REDACTED***')
            ->and($masked['access_token'])->toBe('***REDACTED***')
            ->and($masked['name'])->toBe('Test');
    });

    it('masks sensitive data in strings', function () {
        $text = 'User logged in with password=secret123 and token=abc123def';

        $masked = $this->dataMasker->maskString($text);

        expect($masked)->toContain('password=***REDACTED***')
            ->and($masked)->toContain('token=***REDACTED***');
    });

    it('masks authorization headers', function () {
        $text = 'Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9';

        $masked = $this->dataMasker->maskString($text);

        expect($masked)->toContain('Bearer ***REDACTED***');
    });

    it('masks credit card numbers', function () {
        $text = 'Payment with card 4111-1111-1111-1111';

        $masked = $this->dataMasker->maskString($text);

        expect($masked)->toContain('****-****-****-****');
    });

    it('sanitizes SQL queries', function () {
        config(['api-guardian.production.sanitize_sql' => true]);

        $sql = 'SELECT * FROM `users` WHERE `email` = "test@example.com"';

        $sanitized = $this->dataMasker->sanitizeSQL($sql);

        expect($sanitized)->not->toContain('users')
            ->and($sanitized)->not->toContain('email')
            ->and($sanitized)->not->toContain('test@example.com')
            ->and($sanitized)->toContain('[table]')
            ->and($sanitized)->toContain('[column]')
            ->and($sanitized)->toContain('[value]');
    });

    it('respects masking config when disabled', function () {
        config(['api-guardian.security.mask_sensitive_data' => false]);

        $dataMasker = resolve(DataMasker::class);
        $data = ['password' => 'secret123'];

        $masked = $dataMasker->maskArray($data);

        expect($masked['password'])->toBe('secret123');
    });
});

describe('Security - PII Redaction', function () {
    beforeEach(function () {
        config(['api-guardian.security.pii_redaction.enabled' => true]);
        $this->piiRedactor = resolve(PIIRedactor::class);
    });

    it('redacts email addresses', function () {
        $text = 'User john@example.com tried to access the resource';

        $redacted = $this->piiRedactor->redact($text);

        // Smart redaction: j***@example.com
        expect($redacted)->not->toContain('john@example.com')
            ->and($redacted)->toContain('j***@example.com');
    });

    it('redacts phone numbers', function () {
        $text = 'Contact at +1234567890';

        $redacted = $this->piiRedactor->redact($text);

        // Smart redaction: keeps last 4 digits
        expect($redacted)->not->toContain('+1234567890')
            ->and($redacted)->toEndWith('7890')
            ->and($redacted)->toContain('*');
    });

    it('redacts IP addresses', function () {
        $text = 'Request from 192.168.1.100';

        $redacted = $this->piiRedactor->redact($text);

        // Smart redaction: 192.*.*.*
        expect($redacted)->not->toContain('192.168.1.100')
            ->and($redacted)->toContain('192.*.*.*');
    });

    it('redacts PII in arrays recursively', function () {
        $data = [
            'message' => 'User john@example.com logged in',
            'details' => [
                'ip' => '192.168.1.100',
                'phone' => '+1234567890',
            ],
        ];

        $redacted = $this->piiRedactor->redactArray($data);

        // Smart redaction
        expect($redacted['message'])->toContain('j***@example.com')
            ->and($redacted['details']['ip'])->toContain('192.*.*.*')
            ->and($redacted['details']['phone'])->toEndWith('7890');
    });

    it('respects PII config when disabled', function () {
        config(['api-guardian.security.pii_redaction.enabled' => false]);

        $piiRedactor = resolve(PIIRedactor::class);
        $text = 'john@example.com';

        $redacted = $piiRedactor->redact($text);

        expect($redacted)->toBe('john@example.com');
    });
});

describe('Security - Formatter Integration', function () {
    it('applies security measures in JSend formatter', function () {
        config([
            'api-guardian.security.mask_sensitive_data' => true,
            'api-guardian.security.pii_redaction.enabled' => true,
        ]);

        $exception = ApiException::make('User john@example.com with password secret123')
            ->meta([
                'email' => 'john@example.com',
                'password' => 'secret123',
            ]);

        $formatter = resolve(JSendFormatter::class);
        $response = $formatter->format($exception);
        $data = $response->getData(true);

        // Message should have smart-redacted email (j***@example.com)
        expect($data['message'])->toContain('j***@example.com')
            ->and($data['message'])->not->toContain('john@example.com');

        // Metadata should have masked password
        if (isset($data['data']['password'])) {
            expect($data['data']['password'])->toBe('***REDACTED***');
        }
    });

    it('applies security measures in GraphQL formatter', function () {
        config([
            'api-guardian.security.mask_sensitive_data' => true,
            'api-guardian.security.pii_redaction.enabled' => true,
        ]);

        $exception = ApiException::make('Error for user john@example.com')
            ->meta([
                'api_key' => 'sk_test_123',
                'user_email' => 'test@example.com',
            ]);

        $formatter = resolve(GraphQLFormatter::class);
        $response = $formatter->format($exception);
        $data = $response->getData(true);

        // Message should have smart-redacted email
        expect($data['errors'][0]['message'])->toContain('j***@example.com');

        // Extensions should have masked API key
        if (isset($data['errors'][0]['extensions']['api_key'])) {
            expect($data['errors'][0]['extensions']['api_key'])->toBe('***REDACTED***');
        }
    });

    it('applies security measures in RFC 7807 formatter', function () {
        config([
            'api-guardian.security.mask_sensitive_data' => true,
            'api-guardian.security.pii_redaction.enabled' => true,
        ]);

        $exception = ApiException::make('Error for john@example.com')
            ->meta(['password' => 'secret']);

        $formatter = resolve(Rfc7807Formatter::class);
        $response = $formatter->format($exception);
        $data = $response->getData(true);

        expect($data['detail'])->toContain('j***@example.com');
    });

    it('applies security measures in JSON:API formatter', function () {
        config([
            'api-guardian.security.mask_sensitive_data' => true,
            'api-guardian.security.pii_redaction.enabled' => true,
        ]);

        $exception = ApiException::make('Error for john@example.com')
            ->meta(['token' => 'abc123']);

        $formatter = resolve(JsonApiFormatter::class);
        $response = $formatter->format($exception);
        $data = $response->getData(true);

        expect($data['errors'][0]['detail'])->toContain('j***@example.com');
    });

    it('respects security config when disabled', function () {
        config([
            'api-guardian.security.mask_sensitive_data' => false,
            'api-guardian.security.pii_redaction.enabled' => false,
        ]);

        $exception = ApiException::make('User john@example.com with password secret123')
            ->meta(['password' => 'secret123']);

        $formatter = resolve(JSendFormatter::class);
        $response = $formatter->format($exception);
        $data = $response->getData(true);

        // Data should NOT be masked when security is disabled
        expect($data['message'])->toContain('john@example.com')
            ->and($data['message'])->toContain('password');
    });
});

describe('Security - Production Mode', function () {
    it('does not mask in debug mode when production masking is specifically configured', function () {
        config([
            'app.debug' => true,
            'api-guardian.security.mask_sensitive_data' => false,  // Disable masking
        ]);

        $dataMasker = resolve(DataMasker::class);
        $data = ['password' => 'secret123'];

        $masked = $dataMasker->maskArray($data);

        // With masking disabled, no redaction occurs
        expect($masked['password'])->toBe('secret123');
    });

    it('sanitizes SQL in production', function () {
        config([
            'app.debug' => false,
            'api-guardian.production.sanitize_sql' => true,
        ]);

        $dataMasker = resolve(DataMasker::class);
        $sql = 'SELECT * FROM users WHERE email = "test@example.com"';

        $sanitized = $dataMasker->sanitizeSQL($sql);

        expect($sanitized)->toContain('[table]')
            ->and($sanitized)->toContain('[value]')
            ->and($sanitized)->not->toContain('users')
            ->and($sanitized)->not->toContain('test@example.com');
    });
});
