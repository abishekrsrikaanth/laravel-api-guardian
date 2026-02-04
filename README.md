# Laravel API Guardian

[![Latest Version on Packagist](https://img.shields.io/packagist/v/workdoneright/laravel-api-guardian.svg?style=flat-square)](https://packagist.org/packages/workdoneright/laravel-api-guardian)
[![Total Downloads](https://img.shields.io/packagist/dt/workdoneright/laravel-api-guardian.svg?style=flat-square)](https://packagist.org/packages/workdoneright/laravel-api-guardian)

Advanced API error handling for Laravel with multiple format support, smart debugging, and developer-friendly features.

## Features

- 🎯 **Multiple Error Formats**: JSend, RFC 7807, JSON:API, GraphQL out of the box
- 🔧 **Developer-Friendly**: Smart error context, suggestions, and documentation links
- 🐛 **Enhanced Debugging**: Stack traces, query logs, clickable file paths for development
- 🔒 **Production-Safe**: Automatic sensitive data masking and PII redaction
- 🌍 **Multi-Language Support**: Localized error messages
- 📊 **Real-time Monitoring Dashboard**: Live error tracking, analytics, and trend analysis
- 🔄 **Smart Error Recovery**: Automatic retry logic with exponential backoff
- ⚡ **Circuit Breaker Pattern**: Prevents cascading failures with automatic recovery
- 📈 **Error Analytics**: Trend analysis, performance impact metrics, and insights
- 🛡️ **Graceful Degradation**: Custom fallback strategies and contextual recovery suggestions
- 🎨 **Fluent API**: Clean, expressive exception creation
- 📝 **Auto-Documentation**: Generate error documentation in multiple formats
- ✅ **Validation Enhancement**: Better validation error responses with suggestions
- 🧪 **Testing Utilities**: Helpers for testing error scenarios

## Feature Quick Reference

| Feature | Quick Start | Documentation |
|---------|-------------|----------------|
| **Error Monitoring Dashboard** | `GET /api-guardian/dashboard` | [Full Guide →](docs/MONITORING.md) |
| **Smart Error Recovery** | `ApiGuardian::recovery()->execute()` | [Full Guide →](docs/RECOVERY.md) |
| **Circuit Breaker** | `CircuitBreaker::getOrCreate('service')` | [Full Guide →](docs/RECOVERY.md) |
| **Error Analytics** | `$collector->getAnalytics(7)` | [Full Guide →](docs/MONITORING.md) |
| **Custom Fallbacks** | `$recovery->registerFallbackStrategy()` | [Full Guide →](docs/RECOVERY.md) |

## Installation

You can install the package via composer:

```bash
composer require workdoneright/laravel-api-guardian
```

Publish the configuration and migration files:

```bash
php artisan vendor:publish --tag="api-guardian-config"
php artisan vendor:publish --tag="api-guardian-migrations"
```

Run the migration:

```bash
php artisan migrate
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

### Recovery-Enhanced Exceptions

```php
// Create recoverable exceptions with automatic retry support
ApiException::make('Temporary service issue')
    ->code('SERVICE_UNAVAILABLE')
    ->statusCode(503)
    ->recoverable(true)
    ->throw();

// Get recovery suggestions for any exception
$exception = new \Exception('Connection timeout');
$suggestions = ApiException::fromThrowable($exception)->getRecoverySuggestions();
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

### Using Smart Error Recovery

```php
// Basic recovery with automatic retries
try {
    $result = ApiGuardian::recovery()->execute('external-api', function () {
        return Http::timeout(10)->get('https://api.example.com/data')->json();
    });
    
    return $result;
} catch (Exception $e) {
    // Recovery failed, handle the error
    return response()->json(['error' => 'Service unavailable'], 503);
}

// With custom fallback strategy
ApiGuardian::recovery()->registerFallbackStrategy('payment-service', function ($e) {
    return ['error' => 'Payment temporarily unavailable', 'retry_after' => 30];
});
```

### Monitoring Errors

After setting up the monitoring dashboard:

```php
// Access error analytics programmatically
use WorkDoneRight\ApiGuardian\Services\ErrorCollector;

$collector = app(ErrorCollector::class);
$analytics = $collector->getAnalytics(7); // Last 7 days
$liveErrors = $collector->getLiveErrors(10); // Last 10 errors

// Example: Get recent critical errors
$criticalErrors = \WorkDoneRight\ApiGuardian\Models\ApiError::where('status_code', '>=', 500)
    ->recent(24) // Last 24 hours
    ->unresolved()
    ->orderBy('created_at', 'desc')
    ->get();
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

### GraphQL

```json
{
  "errors": [
    {
      "message": "User not found",
      "locations": [{"line": 5, "column": 10}],
      "path": ["user", "email"],
      "extensions": {
        "code": "RESOURCE_NOT_FOUND",
        "category": "not_found",
        "errorId": "err_abc123xyz",
        "timestamp": "2026-01-31T12:00:00Z",
        "suggestion": "Check if the user ID is correct"
      }
    }
  ],
  "data": null
}
```

[Read the full GraphQL documentation →](internal-docs/GRAPHQL.md)

## Configuration

The configuration file provides extensive customization options:

```php
// config/api-guardian.php

return [
    // Default error format: jsend, rfc7807, jsonapi, graphql, custom
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

## Real-time Error Monitoring Dashboard

The package includes a comprehensive real-time monitoring dashboard for tracking API errors, analytics, and trends.

### Dashboard Setup

After running migrations, you can access the dashboard at `/api-guardian/dashboard` (requires authentication).

### Environment Configuration

Add these settings to your `.env` file:

```env
API_GUARDIAN_MONITORING_ENABLED=true
API_GUARDIAN_RETENTION_DAYS=30
API_GUARDIAN_DB_CONNECTION=null  # Use default connection
```

### Dashboard API Endpoints

```bash
# Main dashboard data (analytics + live errors + circuit breakers)
GET /api-guardian/dashboard

# Analytics data with custom time range
GET /api-guardian/analytics?days=7

# Live error feed with limit
GET /api-guardian/errors/live?limit=50

# Advanced error search and filtering
GET /api-guardian/errors/search?search=keyword&status_code=404&resolved=false

# Get specific error details
GET /api-guardian/errors/{id}

# Mark error as resolved
POST /api-guardian/errors/{id}/resolve

# Circuit breaker status
GET /api-guardian/circuit-breakers

# Reset circuit breaker
POST /api-guardian/circuit-breakers/reset

# Export errors (CSV or JSON)
GET /api-guardian/export?format=csv&days=7
```

### Search and Filter Examples

```javascript
// Search errors by keyword
fetch('/api-guardian/errors/search?search=database')

// Filter by status code
fetch('/api-guardian/errors/search?status_code=404')

// Filter resolved/unresolved errors
fetch('/api-guardian/errors/search?resolved=false')

// Combine filters
fetch('/api-guardian/errors/search?search=timeout&status_code=500&resolved=true')
```

### Frontend Integration

```javascript
// Live error updates
const pollErrors = async () => {
    const response = await fetch('/api-guardian/errors/live');
    const errors = await response.json();
    updateErrorFeed(errors);
};

// Analytics dashboard
const loadAnalytics = async (days = 7) => {
    const response = await fetch(`/api-guardian/analytics?days=${days}`);
    const data = await response.json();
    renderCharts(data);
};

// Error resolution
const resolveError = async (errorId) => {
    await fetch(`/api-guardian/errors/${errorId}/resolve`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' }
    });
    refreshErrorList();
};
```

### Error Analytics

The dashboard provides comprehensive analytics:

- **Total Errors**: Overall error count in time range
- **Unique Errors**: Distinct error instances
- **Unresolved Errors**: Active error issues
- **Top Errors**: Most frequent error patterns
- **Status Distribution**: Errors by HTTP status codes
- **Trend Data**: Hourly distribution over time

### Security Features

- **Sensitive Data Redaction**: Passwords, tokens, API keys automatically masked
- **PII Protection**: Email addresses, phone numbers, IP addresses redacted
- **Access Control**: Dashboard routes protected by authentication middleware
- **Data Retention**: Configurable automatic cleanup of old error data

## Smart Error Recovery System

Automatically handle transient failures with intelligent retry logic and circuit breaker patterns.

### Basic Recovery Usage

```php
use WorkDoneRight\ApiGuardian\Facades\ApiGuardian;

try {
    $result = ApiGuardian::recovery()->execute('external-service', function () {
        // Your operation here
        return Http::timeout(10)->get('https://api.example.com/data');
    });
    
    return $result;
} catch (Exception $e) {
    // Handle final failure after all retries
    Log::error('Operation failed after recovery attempts', [
        'error' => $e->getMessage()
    ]);
}
```

### Service Wrapper Pattern

```php
namespace App\Services;

use WorkDoneRight\ApiGuardian\Services\SmartErrorRecovery;

class ApiService
{
    protected SmartErrorRecovery $recovery;
    
    public function __construct(SmartErrorRecovery $recovery)
    {
        $this->recovery = $recovery;
    }
    
    public function fetchUserData(int $userId): array
    {
        return $this->recovery->execute('user-service', function () use ($userId) {
            return Http::timeout(5)->get("https://users.example.com/api/users/{$userId}")->json();
        }, "fetch-user-{$userId}");
    }
}
```

### Custom Fallback Strategies

```php
use WorkDoneRight\ApiGuardian\Services\SmartErrorRecovery;

$recovery = app(SmartErrorRecovery::class);

// Register custom fallback for specific service
$recovery->registerFallbackStrategy('payment-gateway', function (Exception $e) {
    if ($e->getMessage() === 'Service temporarily unavailable') {
        return [
            'error' => 'Payment service temporarily unavailable',
            'message' => 'Please try again in a few moments',
            'retry_after' => 30,
            'type' => 'payment_error',
        ];
    }
    
    return [
        'error' => 'Payment processing failed',
        'message' => 'Please contact support or try alternative payment method',
        'type' => 'payment_error',
    ];
});
```

### Circuit Breaker Usage

```php
use WorkDoneRight\ApiGuardian\Models\CircuitBreaker;

// Get or create circuit breaker
$breaker = CircuitBreaker::getOrCreate('payment-gateway', 'process-payment');

// Manual circuit breaker operations
if ($breaker->canAttempt()) {
    try {
        $result = $paymentGateway->process($payment);
        $breaker->recordSuccess();
        return $result;
    } catch (Exception $e) {
        $breaker->recordFailure();
        
        if ($breaker->isOpen()) {
            return response()->json([
                'error' => 'Service temporarily unavailable',
                'retry_after' => $breaker->next_attempt_at->diffInSeconds(now())
            ], 503);
        }
        
        throw $e;
    }
} else {
    // Circuit is open, use fallback
    return $this->fallbackResponse();
}

// Check circuit breaker state
if ($breaker->isOpen()) {
    $retryAfter = $breaker->next_attempt_at->diffInSeconds(now());
    return response()->json([
        'error' => 'Service temporarily unavailable',
        'retry_after' => $retryAfter
    ], 503);
}
```

### Recovery Configuration

Add recovery settings to your `.env` file:

```env
API_GUARDIAN_RECOVERY_ENABLED=true
API_GUARDIAN_MAX_RETRIES=3
API_GUARDIAN_BASE_DELAY=1000
```

### Recovery Configuration Options

```php
// config/api-guardian.php
'recovery' => [
    'enabled' => true,
    'max_retries' => 3,
    'base_delay' => 1000, // milliseconds
    'backoff_multiplier' => 2.0,
    'transient_error_patterns' => [
        '/timeout/i',
        '/connection/i',
        '/network/i',
        '/temporary/i',
        '/503/',
        '/502/',
        '/504/',
        '/429/',
    ],
    'transient_status_codes' => [429, 502, 503, 504],
    'auto_recovery' => [
        'enabled' => true,
        'safe_methods' => ['GET', 'HEAD'],
    ],
],

'circuit_breaker' => [
    'failure_threshold' => 5,
    'recovery_timeout' => 60, // seconds
    'success_threshold' => 3,
    'half_open_max_calls' => 3,
    'auto_reset' => true,
    'reset_after' => 3600, // seconds
],
```

### Recovery Suggestions

The system automatically generates contextual recovery suggestions:

```php
use WorkDoneRight\ApiGuardian\Services\SmartErrorRecovery;

$recovery = app(SmartErrorRecovery::class);

// Get recovery suggestions for any exception
$suggestions = $recovery->generateRecoverySuggestion($exception);

// Example output for timeout error:
[
    'type' => 'timeout',
    'message' => 'The operation timed out. Consider increasing the timeout or optimizing the request.',
    'actions' => [
        'Try again with a longer timeout',
        'Check if the request payload can be optimized',
        'Verify network connectivity'
    ]
]
```

### Health Check Integration

```php
// Add to routes/web.php or routes/api.php
Route::get('/health/api-guardian', function () {
    $breakers = \WorkDoneRight\ApiGuardian\Models\CircuitBreaker::all();
    $healthy = $breakers->where('state', 'closed')->count();
    $total = $breakers->count();
    
    return response()->json([
        'status' => $healthy === $total ? 'healthy' : 'degraded',
        'healthy_breakers' => $healthy,
        'total_breakers' => $total,
        'details' => $breakers->map(function ($breaker) {
            return [
                'service' => $breaker->service,
                'state' => $breaker->state,
                'can_attempt' => $breaker->canAttempt(),
            ];
        })
    ], $healthy === $total ? 200 : 503);
});
```

### Monitoring Recovery Performance

```php
// In AppServiceProvider or similar
Event::listen('api-guardian.recovery.attempted', function ($service, $attempt, $maxAttempts) {
    Log::info("Recovery attempt", [
        'service' => $service,
        'attempt' => $attempt,
        'max_attempts' => $maxAttempts
    ]);
});

Event::listen('api-guardian.recovery.succeeded', function ($service, $attempts) {
    Log::info("Recovery succeeded", [
        'service' => $service,
        'attempts_used' => $attempts
    ]);
});
```

## Frontend Stack Support

The API Guardian package supports multiple frontend ecosystems, allowing you to choose the best stack for your application:

### 🎯 **Livewire 4 + Flux (Recommended)**
Real-time server-rendered components with reactive UI and minimal JavaScript.

#### Installation
```bash
# Install additional dependencies
npm install @livewire/livewire
composer require livewire/livewire
```

#### Usage Example
```php
// routes/web.php
Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/api-guardian/dashboard', function () {
        return view('api-guardian::dashboard');
    });
});
```

```blade
{{-- resources/views/api-guardian/dashboard.blade.php --}}
<x-app-layout>
    <livewire:api-guardian.dashboard />
</x-app-layout>
```

#### Key Features
- ✅ Real-time error feed with automatic updates
- ✅ Interactive charts and analytics
- ✅ Circuit breaker status monitoring
- ✅ Modal-based error details
- ✅ Search and filtering capabilities

---

### ⚡ **Inertia.js + Vue 3**
Single Page Application experience with client-side navigation and component-based architecture.

#### Installation
```bash
npm install @inertiajs/vue3 vue@latest
npm install vue-chartjs chart.js
```

#### Usage Example
```php
// routes/web.php
Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/api-guardian/dashboard', [InertiaDashboardController::class, 'index'])->name('api-guardian.dashboard');
});
```

```vue
{{-- resources/js/pages/ApiGuardian/Dashboard.vue --}}
<script setup>
import { Head } from '@inertiajs/vue3'
import Dashboard from '../../components/Dashboard.vue'
</script>

<template>
  <Head>
    <title>API Guardian Dashboard</title>
  </Head>
  <Dashboard />
</template>
```

#### Key Features
- ✅ SPA navigation with browser history
- ✅ Client-side state management with composables
- ✅ Real-time updates with EventSource/WebSockets
- ✅ Advanced data visualization
- ✅ Smooth transitions and animations

---

### 🎨 **Vue 3 (SPA)**
Traditional Single Page Application with Vue Router and Pinia for complete frontend control.

#### Installation
```bash
npm install vue@latest vue-router@4 pinia
npm install vue-chartjs chart.js axios
```

#### Setup Example
```javascript
// resources/js/app.js
import { createApp } from 'vue'
import { createRouter, createWebHistory } from 'vue-router'
import { createPinia } from 'pinia'
import App from './App.vue'

const app = createApp(App)

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/api-guardian/dashboard', component: () => import('./views/Dashboard.vue') },
    { path: '/api-guardian/analytics', component: () => import('./views/Analytics.vue') },
  ]
})

const pinia = createPinia()

app.use(router)
app.use(pinia)
app.mount('#app')
```

#### Key Features
- ✅ Complete frontend control
- ✅ Advanced state management with Pinia
- ✅ Client-side routing and navigation
- ✅ Custom animations and transitions
- ✅ Integration with any backend framework

---

## 📊 **API Endpoints**

All frontend stacks share the same REST API endpoints:

```bash
# Dashboard data
GET /api-guardian/dashboard

# Analytics with filtering
GET /api-guardian/analytics?days=7

# Error listing with search/filters
GET /api-guardian/errors/search?search=keyword&status_code=404&resolved=false

# Error details
GET /api-guardian/errors/{id}

# Resolve error
POST /api-guardian/errors/{id}/resolve

# Circuit breaker status
GET /api-guardian/circuit-breakers
POST /api-guardian/circuit-breakers/reset

# Export data
GET /api-guardian/export?format=csv&days=7
```

## 🔧 **Frontend Integration Examples**

### Real-time Updates
```javascript
// Livewire (Flux components)
<x-app-layout>
    <livewire:api-guardian.error-feed wire:poll.5000ms />
</x-app-layout>

// Inertia.js + Vue (EventSource)
const { startRealTimeUpdates } = useApiGuardian()

onMounted(() => {
  startRealTimeUpdates()
})

// Vue SPA (WebSockets)
import io from 'socket.io-client'

const socket = io('http://localhost:6001')
socket.on('new-error', (error) => {
  store.commit('addError', error)
})
```

### Error Management
```php
// Livewire (Flux)
<livewire:api-guardian.error-detail 
    :error="$error" 
    @error-resolved="$refreshData" 
/>

// Inertia.js + Vue
<template>
  <ErrorDetailModal 
    v-if="showErrorModal" 
    :error="selectedError" 
    @close="hideErrorModal" 
    @resolve="resolveError" 
  />
</template>
```

### Analytics Dashboard
```php
// Livewire (Flux)
<flux:card>
    <livewire:api-guardian.analytics />
</flux:card>

// Inertia.js + Vue
<AnalyticsChart :data="analytics?.trend_data" />
<TopErrors :errors="analytics?.top_errors" :total-errors="analytics?.total_errors" />

// Vue SPA (Chart.js)
<template>
  <canvas ref="errorChart"></canvas>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { Chart } from 'chart.js'

const errorChart = ref(null)

onMounted(() => {
  new Chart(errorChart.value, {
    type: 'line',
    data: chartData,
    options: {
      responsive: true,
      scales: {
        y: { beginAtZero: true }
      }
    }
  })
})
</script>
```

## Security

- Automatic sensitive data masking (passwords, tokens, API keys)
- PII redaction (emails, phone numbers, IP addresses)
- SQL injection attempt logging
- Configurable data retention policies
- GDPR-compliant error logging

## Documentation

- 📖 [Installation Guide](docs/INSTALL.md) - Detailed setup instructions
- 📚 [Usage Guide](docs/USAGE.md) - Complete usage examples
- 🔄 [Smart Error Recovery](docs/RECOVERY.md) - Retry logic and circuit breakers
- 📊 [Monitoring Dashboard](docs/MONITORING.md) - Real-time error tracking
- 🔧 [Extending Guide](docs/EXTENDING.md) - Custom formatters and strategies
- 🗺️ **[Roadmap](docs/ROADMAP.md) - Future features and version plans**
- ✨ [Recent Enhancements](internal-docs/ENHANCEMENTS.md) - Latest improvements

## Changelog

Please see [CHANGELOG](docs/CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](docs/CONTRIBUTING.md) for details.

## Credits

- [Abishek R Srikaanth](https://github.com/abishekrsrikaanth)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](docs/LICENSE.md) for more information.
