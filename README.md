# Laravel API Guardian

[![Latest Version on Packagist](https://img.shields.io/packagist/v/workdoneright/laravel-api-guardian.svg?style=flat-square)](https://packagist.org/packages/workdoneright/laravel-api-guardian)
[![Total Downloads](https://img.shields.io/packagist/dt/workdoneright/laravel-api-guardian.svg?style=flat-square)](https://packagist.org/packages/workdoneright/laravel-api-guardian)

Advanced API error handling for Laravel with multiple format support, smart debugging, and developer-friendly features.

## Features

- 🎯 **Multiple Error Formats**: JSend, RFC 7807, JSON:API out of the box
- 🔧 **Developer-Friendly**: Smart error context, suggestions, and documentation links
- 🐛 **Enhanced Debugging**: Stack traces, query logs, clickable file paths for development
- 🔒 **Production-Safe**: Automatic sensitive data masking and PII redaction
- 🌍 **Multi-Language Support**: Localized error messages
- 📊 **Monitoring Integration**: Built-in support for Sentry, webhooks, and custom reporters
- 🎨 **Fluent API**: Clean, expressive exception creation
- 📝 **Auto-Documentation**: Generate error documentation in multiple formats
- ✅ **Validation Enhancement**: Better validation error responses with suggestions
- 🧪 **Testing Utilities**: Helpers for testing error scenarios

## Installation

You can install the package via composer:

```bash
composer require workdoneright/laravel-api-guardian
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag="api-guardian-config"
```

## Quick Start

### Basic Usage

```php
use WorkDoneRight\ApiGuardian\Facades\ApiGuardian;

// Throw a not found error
ApiGuardian::notFound('User not found')->throw();

// Create a custom error with fluent interface
ApiGuardian::exception('Invalid payment method')
    ->code('INVALID_PAYMENT_METHOD')
    ->statusCode(400)
    ->meta(['accepted_methods' => ['visa', 'mastercard']])
    ->suggestion('Please use a valid payment method')
    ->link('https://docs.example.com/payments')
    ->throw();
```

### Using the ApiException Class

```php
use WorkDoneRight\ApiGuardian\Exceptions\ApiException;

// Simple usage
throw ApiException::notFound('Resource not found');

// With fluent interface
ApiException::make('Custom error')
    ->code('CUSTOM_ERROR')
    ->statusCode(400)
    ->meta(['key' => 'value'])
    ->suggestion('Try this instead')
    ->recoverable()
    ->throw();
```

### Quick Exception Helpers

```php
// Pre-configured exceptions
ApiGuardian::notFound('User not found')->throw();
ApiGuardian::unauthorized('Invalid credentials')->throw();
ApiGuardian::forbidden('Access denied')->throw();
ApiGuardian::validationFailed('Invalid input')->throw();
ApiGuardian::badRequest('Invalid request')->throw();
ApiGuardian::rateLimitExceeded('Too many requests', 60)->throw();
ApiGuardian::serverError('Something went wrong')->throw();
```

## Error Formats

### JSend Format (Default)

```json
{
  "status": "fail",
  "message": "User not found",
  "code": "RESOURCE_NOT_FOUND",
  "data": {
    "error_id": "err_abc123xyz",
    "timestamp": "2026-01-31T12:00:00Z",
    "suggestion": "Check if the user ID is correct"
  }
}
```

### RFC 7807 (Problem Details)

```json
{
  "type": "https://api.example.com/errors/resource-not-found",
  "title": "Not Found",
  "status": 404,
  "detail": "User not found",
  "instance": "err_abc123xyz",
  "suggestion": "Check if the user ID is correct"
}
```

### JSON:API

```json
{
  "errors": [
    {
      "id": "err_abc123xyz",
      "status": "404",
      "code": "RESOURCE_NOT_FOUND",
      "title": "Not Found",
      "detail": "User not found",
      "meta": {
        "timestamp": "2026-01-31T12:00:00Z",
        "suggestion": "Check if the user ID is correct"
      }
    }
  ]
}
```

## Configuration

The configuration file provides extensive customization options:

```php
// config/api-guardian.php

return [
    // Default error format: jsend, rfc7807, jsonapi, custom
    'default_format' => env('API_GUARDIAN_FORMAT', 'jsend'),
    
    // Enable/disable various context information
    'context' => [
        'include_error_id' => true,
        'include_timestamp' => true,
        'include_suggestions' => true,
        'include_trace' => env('APP_DEBUG', false),
    ],
    
    // Development mode settings
    'development' => [
        'enabled' => env('APP_DEBUG', false),
        'include_file_path' => true,
        'clickable_paths' => true,
        'ide' => env('API_GUARDIAN_IDE', 'vscode'),
    ],
    
    // Security settings
    'security' => [
        'mask_sensitive_data' => true,
        'pii_redaction' => [
            'enabled' => true,
        ],
    ],
    
    // And much more...
];
```

## Format Negotiation

You can use middleware to automatically detect the format from the Accept header:

```php
// In your routes
Route::middleware('api-guardian')->group(function () {
    Route::get('/users', [UserController::class, 'index']);
});

// Or specify a format
Route::middleware('api-guardian:rfc7807')->group(function () {
    Route::get('/users', [UserController::class, 'index']);
});
```

### Accept Header Detection

```bash
# Request with RFC 7807
curl -H "Accept: application/problem+json" https://api.example.com/users

# Request with JSON:API
curl -H "Accept: application/vnd.api+json" https://api.example.com/users
```

## Validation Errors

Enhanced validation error responses with error codes and suggestions:

```json
{
  "status": "fail",
  "message": "Validation failed",
  "data": {
    "email": {
      "message": "The email must be a valid email address",
      "code": "INVALID_EMAIL"
    },
    "password": {
      "message": "The password field is required",
      "code": "FIELD_REQUIRED"
    }
  }
}
```

## Artisan Commands

### List All Errors

```bash
php artisan errors:list
```

### Generate Documentation

```bash
# Generate markdown documentation
php artisan errors:generate-docs --format=markdown

# Generate HTML documentation
php artisan errors:generate-docs --format=html

# Generate OpenAPI schema
php artisan errors:generate-docs --format=openapi
```

### Test Error Rendering

```bash
php artisan errors:test NOT_FOUND --format=jsend --message="Custom message"
```

### Analyze Error Patterns

```bash
php artisan errors:analyze --days=7
```

## Development vs Production

### Development Mode

When `APP_DEBUG=true`, API Guardian includes:
- Full stack traces
- Clickable file paths (IDE integration)
- Query logs for database errors
- Request/response dumps
- Exception chain visualization

### Production Mode

When `APP_DEBUG=false`, API Guardian:
- Hides sensitive information
- Masks passwords, tokens, API keys
- Redacts PII (emails, phone numbers)
- Shows user-friendly error messages
- Includes error IDs for support reference

## Testing

API Guardian includes helpful testing utilities:

```php
use WorkDoneRight\ApiGuardian\Exceptions\ApiException;

it('handles not found errors', function () {
    $this->getJson('/api/users/999')
        ->assertStatus(404)
        ->assertJsonStructure([
            'status',
            'message',
            'code',
            'data',
        ]);
});
```

Run tests:

```bash
composer test
```

## Advanced Usage

### Custom Exception Handler

If you need full control, you can extend the exception handler:

```php
namespace App\Exceptions;

use WorkDoneRight\ApiGuardian\Exceptions\Handler as ApiGuardianHandler;

class Handler extends ApiGuardianHandler
{
    protected function shouldHandleApiException($request): bool
    {
        // Custom logic to determine if this should be handled as API error
        return parent::shouldHandleApiException($request);
    }
}
```

### Custom Formatter

Create your own error formatter:

```php
use WorkDoneRight\ApiGuardian\Contracts\ErrorFormatterContract;

class CustomFormatter implements ErrorFormatterContract
{
    public function format(Throwable $exception, ?int $statusCode = null): JsonResponse
    {
        // Your custom formatting logic
    }
    
    // Implement other required methods...
}

// Use it
ApiGuardian::useFormatter(new CustomFormatter());
```

## Security

- Automatic sensitive data masking (passwords, tokens, API keys)
- PII redaction (emails, phone numbers, IP addresses)
- SQL injection attempt logging
- Configurable data retention policies
- GDPR-compliant error logging

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Credits

- [Abishek R Srikaanth](https://github.com/abishekrsrikaanth)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
