<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Error Format
    |--------------------------------------------------------------------------
    |
    | The default format to use for API error responses. Available formats:
    | 'jsend', 'rfc7807', 'jsonapi', 'graphql', 'custom'
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
        'graphql' => [
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
        'include_error_codes' => true,
        'include_debug_info' => env('APP_DEBUG', false),
        'include_trace' => env('APP_DEBUG', false),
        'include_queries' => env('APP_DEBUG', false),
        'include_memory' => env('APP_DEBUG', false),
        'include_suggestions' => true,
        'include_examples' => true,
        'nested_path_support' => true,
        'max_errors_per_field' => 3,
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
        'sanitize_request_data' => true,
        'mask_sensitive_data' => true,
        'allowed_ips' => explode(',', (string) env('API_GUARDIAN_ALLOWED_IPS', '*')),
        'mask_patterns' => [
            'password',
            'token',
            'secret',
            'api_key',
            'credit_card',
            'ssn',
        ],
        'sensitive_keys' => [
            'password',
            'password_confirmation',
            'secret',
            'token',
            'api_key',
            'access_token',
            'refresh_token',
            'private_key',
        ],
        'sensitive_headers' => [
            'authorization',
            'x-api-key',
            'password',
            'secret',
            'token',
            'cookie',
            'x-csrf-token',
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
        'enabled' => env('API_GUARDIAN_PERFORMANCE_ENABLED', true),
        'cache_errors' => false,
        'cache_duration' => 3600, // seconds
        'lazy_load_context' => true,
        'slow_query_threshold' => env('API_GUARDIAN_SLOW_QUERY_THRESHOLD', 1000), // milliseconds
        'memory_threshold' => env('API_GUARDIAN_MEMORY_THRESHOLD', 128), // MB
        'cpu_threshold' => env('API_GUARDIAN_CPU_THRESHOLD', 80), // percentage
        'async_reporting' => false,
    ],
    /*
    |--------------------------------------------------------------------------
    | UI Framework Configuration
    |--------------------------------------------------------------------------
    |
    | Choose which UI framework(s) to enable for the dashboard.
    | Multiple frameworks can be enabled simultaneously with different routes.
    |
    | Supported: 'livewire', 'inertia-vue', 'inertia-react'
    |
    */

    'ui' => [
        'default' => env('API_GUARDIAN_DEFAULT_UI', 'livewire'),

        'frameworks' => [
            'livewire' => [
                'enabled' => env('API_GUARDIAN_LIVEWIRE_ENABLED', true),
                'route_prefix' => env('API_GUARDIAN_LIVEWIRE_PREFIX', 'api-guardian'),
                'middleware' => ['web', 'auth'],
            ],

            'inertia-vue' => [
                'enabled' => env('API_GUARDIAN_INERTIA_VUE_ENABLED', false),
                'route_prefix' => env('API_GUARDIAN_INERTIA_VUE_PREFIX', 'api-guardian/vue'),
                'middleware' => ['web', 'auth'],
            ],

            'inertia-react' => [
                'enabled' => env('API_GUARDIAN_INERTIA_REACT_ENABLED', false),
                'route_prefix' => env('API_GUARDIAN_INERTIA_REACT_PREFIX', 'api-guardian/react'),
                'middleware' => ['web', 'auth'],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Dashboard Configuration
    |--------------------------------------------------------------------------
    |
    | General dashboard settings that apply to all UI frameworks.
    |
    */

    'dashboard' => [
        'enabled' => env('API_GUARDIAN_DASHBOARD_ENABLED', true),
        'title' => env('API_GUARDIAN_DASHBOARD_TITLE', 'API Guardian'),
        'pagination' => env('API_GUARDIAN_PAGINATION', 25),
        'refresh_interval' => env('API_GUARDIAN_REFRESH_INTERVAL', 5000), // milliseconds
        'date_format' => env('API_GUARDIAN_DATE_FORMAT', 'Y-m-d H:i:s'),
        'timezone' => env('API_GUARDIAN_TIMEZONE', 'UTC'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Real-time Updates
    |--------------------------------------------------------------------------
    |
    | Configure real-time error monitoring updates.
    | Works with Pusher, Laravel Echo, or polling.
    |
    */

    'realtime' => [
        'enabled' => env('API_GUARDIAN_REALTIME_ENABLED', false),
        'driver' => env('API_GUARDIAN_REALTIME_DRIVER', 'pusher'), // pusher, redis, polling
        'polling_interval' => env('API_GUARDIAN_POLLING_INTERVAL', 5000), // milliseconds (fallback)
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring Configuration
    |--------------------------------------------------------------------------
    |
    | Core monitoring and alerting configuration.
    |
    */

    'monitoring' => [
        'enabled' => env('API_GUARDIAN_MONITORING_ENABLED', true),

        'error_collection' => [
            'auto_discover' => true,
            'include_stack_traces' => true,
            'include_request_data' => true,
            'max_stack_trace_depth' => 50,
        ],

        'alerting' => [
            'enabled' => env('API_GUARDIAN_ALERTING_ENABLED', false),
            'channels' => [
                'slack' => [
                    'enabled' => false,
                    'webhook_url' => env('SLACK_WEBHOOK_URL'),
                    'channel' => '#api-alerts',
                ],
                'email' => [
                    'enabled' => false,
                    'recipients' => explode(',', (string) env('API_GUARDIAN_ALERT_EMAILS', '')),
                ],
                'discord' => [
                    'enabled' => false,
                    'webhook_url' => env('DISCORD_WEBHOOK_URL'),
                ],
            ],
            'thresholds' => [
                'error_rate' => 5, // errors per minute
                'downtime_threshold' => 300, // seconds
            ],
        ],

        'health_checks' => [
            'enabled' => env('API_GUARDIAN_HEALTH_CHECKS_ENABLED', true),
            'interval' => env('API_GUARDIAN_HEALTH_CHECK_INTERVAL', 60), // seconds
            'timeout' => env('API_GUARDIAN_HEALTH_CHECK_TIMEOUT', 10), // seconds
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Circuit Breaker
    |--------------------------------------------------------------------------
    |
    | Circuit breaker configuration for failover and resilience.
    |
    */

    'circuit_breaker' => [
        'enabled' => env('API_GUARDIAN_CIRCUIT_BREAKER_ENABLED', true),
        'failure_threshold' => env('API_GUARDIAN_FAILURE_THRESHOLD', 5),
        'recovery_timeout' => env('API_GUARDIAN_RECOVERY_TIMEOUT', 60), // seconds
        'monitoring_period' => env('API_GUARDIAN_MONITORING_PERIOD', 300), // seconds
        'auto_reset' => env('API_GUARDIAN_AUTO_RESET_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    |
    | Configure how and where data is stored.
    |
    */

    'storage' => [
        'connection' => env('API_GUARDIAN_DB_CONNECTION', 'default'),
        'retention_period' => env('API_GUARDIAN_RETENTION_PERIOD', 30), // days
        'cleanup_interval' => env('API_GUARDIAN_CLEANUP_INTERVAL', 'daily'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Recovery Configuration
    |--------------------------------------------------------------------------
    |
    | Smart error recovery and retry logic configuration.
    |
    */

    'recovery' => [
        'enabled' => env('API_GUARDIAN_RECOVERY_ENABLED', true),
        'max_retries' => env('API_GUARDIAN_MAX_RETRIES', 3),
        'base_delay' => env('API_GUARDIAN_BASE_DELAY', 1000), // milliseconds
        'max_delay' => env('API_GUARDIAN_MAX_DELAY', 10000), // milliseconds
        'backoff_multiplier' => env('API_GUARDIAN_BACKOFF_MULTIPLIER', 2.0),
        'transient_error_patterns' => [
            '/timeout/i',
            '/connection/i',
            '/network/i',
            '/temporary/i',
            '/temporarily/i',
            '/503/',
            '/502/',
            '/504/',
            '/429/',
        ],
        'transient_status_codes' => [429, 502, 503, 504],
    ],

    /*
    |--------------------------------------------------------------------------
    | API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for API endpoints and authentication.
    |
    */

    'api' => [
        'enabled' => env('API_GUARDIAN_API_ENABLED', true),
        'prefix' => env('API_GUARDIAN_API_PREFIX', 'api/api-guardian'),
        'middleware' => [], // Additional middleware for API routes (base 'api' is always applied)
        'version' => env('API_GUARDIAN_API_VERSION', 'v1'),
        'rate_limiting' => [
            'enabled' => env('API_GUARDIAN_API_RATE_LIMIT_ENABLED', true),
            'requests_per_minute' => env('API_GUARDIAN_API_RATE_LIMIT', 60),
        ],
        'authentication' => [
            'type' => env('API_GUARDIAN_AUTH_TYPE', 'sanctum'),
            'required' => env('API_GUARDIAN_AUTH_REQUIRED', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Integrations
    |--------------------------------------------------------------------------
    |
    | Third-party service integrations.
    |
    */

    'integrations' => [
        'sentry' => [
            'enabled' => env('API_GUARDIAN_SENTRY_ENABLED', false),
            'dsn' => env('SENTRY_LARAVEL_DSN'),
        ],
        'new_relic' => [
            'enabled' => env('API_GUARDIAN_NEW_RELIC_ENABLED', false),
            'app_name' => env('NEW_RELIC_APP_NAME'),
            'license_key' => env('NEW_RELIC_LICENSE_KEY'),
        ],
        'datadog' => [
            'enabled' => env('API_GUARDIAN_DATADOG_ENABLED', false),
            'api_key' => env('DATADOG_API_KEY'),
            'app_key' => env('DATADOG_APP_KEY'),
        ],
    ],

];
