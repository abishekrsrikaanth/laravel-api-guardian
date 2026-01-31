<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Error Format
    |--------------------------------------------------------------------------
    |
    | The default format to use for API error responses. Available formats:
    | 'jsend', 'rfc7807', 'jsonapi', 'custom'
    |
    */
    'default_format' => env('API_GUARDIAN_FORMAT', 'jsend'),

    /*
    |--------------------------------------------------------------------------
    | Format Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how each format should structure error responses.
    |
    */
    'formats' => [
        'jsend' => [
            'enabled' => true,
            'include_trace' => env('APP_DEBUG', false),
        ],
        'rfc7807' => [
            'enabled' => true,
            'include_trace' => env('APP_DEBUG', false),
            'type_url_prefix' => 'https://api.example.com/errors/',
        ],
        'jsonapi' => [
            'enabled' => true,
            'include_trace' => env('APP_DEBUG', false),
        ],
        'custom' => [
            'enabled' => false,
            'formatter' => null, // Set to your custom formatter class
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Error Context
    |--------------------------------------------------------------------------
    |
    | Configure what context information should be included in error responses.
    |
    */
    'context' => [
        'include_error_id' => true,
        'include_timestamp' => true,
        'include_request_id' => true,
        'include_user_info' => false,
        'include_trace' => env('APP_DEBUG', false),
        'include_queries' => env('APP_DEBUG', false),
        'include_memory' => env('APP_DEBUG', false),
        'include_suggestions' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Development Mode
    |--------------------------------------------------------------------------
    |
    | Enhanced debugging information for development environments.
    |
    */
    'development' => [
        'enabled' => env('APP_DEBUG', false),
        'include_file_path' => true,
        'include_line_number' => true,
        'include_exception_chain' => true,
        'include_request_dump' => false,
        'clickable_paths' => true,
        'ide' => env('API_GUARDIAN_IDE', 'vscode'), // vscode, phpstorm, sublime
    ],

    /*
    |--------------------------------------------------------------------------
    | Production Mode
    |--------------------------------------------------------------------------
    |
    | Security and privacy settings for production environments.
    |
    */
    'production' => [
        'hide_exception_message' => false,
        'generic_message' => 'An error occurred while processing your request.',
        'sanitize_sql' => true,
        'mask_sensitive_data' => true,
        'breadcrumb_count' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation Errors
    |--------------------------------------------------------------------------
    |
    | Enhanced validation error responses.
    |
    */
    'validation' => [
        'include_error_codes' => true,
        'include_suggestions' => true,
        'include_examples' => true,
        'nested_path_support' => true,
        'max_errors_per_field' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Localization
    |--------------------------------------------------------------------------
    |
    | Multi-language error message support.
    |
    */
    'localization' => [
        'enabled' => true,
        'fallback_locale' => 'en',
        'detect_from_header' => true,
        'header_name' => 'Accept-Language',
    ],

    /*
    |--------------------------------------------------------------------------
    | Error Reporting
    |--------------------------------------------------------------------------
    |
    | Integration with monitoring and logging services.
    |
    */
    'reporting' => [
        'enabled' => true,
        'drivers' => [
            'log' => [
                'enabled' => true,
                'channel' => env('API_GUARDIAN_LOG_CHANNEL', 'stack'),
            ],
            'sentry' => [
                'enabled' => false,
                'dsn' => env('SENTRY_LARAVEL_DSN'),
            ],
            'webhook' => [
                'enabled' => false,
                'url' => env('API_GUARDIAN_WEBHOOK_URL'),
                'critical_only' => true,
            ],
        ],
        'throttle' => [
            'enabled' => true,
            'max_similar_errors' => 10,
            'time_window' => 60, // seconds
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Security
    |--------------------------------------------------------------------------
    |
    | Security and data protection settings.
    |
    */
    'security' => [
        'mask_patterns' => [
            'password',
            'token',
            'secret',
            'api_key',
            'credit_card',
            'ssn',
        ],
        'pii_redaction' => [
            'enabled' => true,
            'patterns' => [
                'email' => '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/',
                'phone' => '/\+?[1-9]\d{1,14}/',
                'ip' => '/\b(?:\d{1,3}\.){3}\d{1,3}\b/',
            ],
        ],
        'log_suspicious_attempts' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP Status Code Mapping
    |--------------------------------------------------------------------------
    |
    | Map exception types to HTTP status codes.
    |
    */
    'status_codes' => [
        'default' => 500,
        'validation' => 422,
        'authentication' => 401,
        'authorization' => 403,
        'not_found' => 404,
        'rate_limit' => 429,
        'maintenance' => 503,
    ],

    /*
    |--------------------------------------------------------------------------
    | Error Categories
    |--------------------------------------------------------------------------
    |
    | Categorize errors for better organization and filtering.
    |
    */
    'categories' => [
        'client_error' => [400, 401, 403, 404, 422, 429],
        'server_error' => [500, 502, 503, 504],
        'critical' => [500, 502],
        'recoverable' => [400, 422, 429],
    ],

    /*
    |--------------------------------------------------------------------------
    | Documentation
    |--------------------------------------------------------------------------
    |
    | Automatic error documentation generation settings.
    |
    */
    'documentation' => [
        'enabled' => true,
        'output_path' => storage_path('docs/errors'),
        'include_examples' => true,
        'formats' => ['markdown', 'html', 'openapi'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Testing
    |--------------------------------------------------------------------------
    |
    | Settings for testing error scenarios.
    |
    */
    'testing' => [
        'enabled' => env('APP_ENV') === 'testing',
        'factory_namespace' => 'WorkDoneRight\\ApiGuardian\\Tests\\Factories',
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance
    |--------------------------------------------------------------------------
    |
    | Performance optimization settings.
    |
    */
    'performance' => [
        'cache_errors' => false,
        'cache_duration' => 3600, // seconds
        'lazy_load_context' => true,
        'async_reporting' => false,
    ],

];
