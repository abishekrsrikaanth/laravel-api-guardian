# Smart Error Recovery System

The Laravel API Guardian package includes an intelligent **Error Recovery System** that automatically handles transient failures, implements circuit breaker patterns, and provides graceful degradation strategies.

## Features

### 🔄 Automatic Retry Logic
- Exponential backoff with jitter
- Configurable retry limits
- Transient error detection
- Smart error categorization

### ⚡ Circuit Breaker Pattern
- Automatic circuit breaking on repeated failures
- Half-open state testing
- Configurable thresholds
- Automatic recovery detection

### 🛡️ Graceful Degradation
- Custom fallback strategies
- Contextual error responses
- Recovery suggestions
- Service health monitoring

### 🧠 Intelligent Recovery
- Pattern-based error detection
- Context-aware suggestions
- Performance optimization
- Safety-first approach

## Configuration

Add these settings to your `.env` file:

```env
API_GUARDIAN_RECOVERY_ENABLED=true
API_GUARDIAN_MAX_RETRIES=3
API_GUARDIAN_BASE_DELAY=1000
```

### Recovery Configuration

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
    'recoverable_errors' => [
        'timeout',
        'connection',
        'rate_limit',
    ],
    'recoverable_status_codes' => [429, 502, 503, 504],
    'auto_recovery' => [
        'enabled' => true,
        'safe_methods' => ['GET', 'HEAD'],
    ],
],
```

### Circuit Breaker Configuration

```php
// config/api-guardian.php
'circuit_breaker' => [
    'failure_threshold' => 5,
    'recovery_timeout' => 60, // seconds
    'success_threshold' => 3,
    'half_open_max_calls' => 3,
    'monitor_period' => 60, // seconds
    'auto_reset' => true,
    'reset_after' => 3600, // seconds
],
```

## Usage

### Basic Recovery

```php
use WorkDoneRight\ApiGuardian\Facades\ApiGuardian;

try {
    $result = ApiGuardian::recovery()->execute('external-service', function () {
        // Your operation here
        return Http::timeout(10)->get('https://api.example.com/data');
    });
} catch (Exception $e) {
    // Handle final failure after retries
    Log::error('Operation failed after recovery attempts', [
        'error' => $e->getMessage()
    ]);
}
```

### Custom Retry Strategies

```php
use WorkDoneRight\ApiGuardian\Services\SmartErrorRecovery;

$recovery = app(SmartErrorRecovery::class);

// Register custom retry strategy
$recovery->registerRetryStrategy('payment-service', function ($attempt, $exception) {
    // Custom retry logic
    if ($attempt < 5 && $exception instanceof TimeoutException) {
        usleep(rand(100000, 500000)); // Random delay
        return true; // Continue retrying
    }
    return false; // Stop retrying
});
```

### Custom Fallback Strategies

```php
// Register custom fallback for specific services
$recovery->registerFallbackStrategy('database-service', function (Exception $e) {
    if ($e->getCode() === 2006) { // MySQL server has gone away
        return [
            'error' => 'Database temporarily unavailable',
            'message' => 'Please try again in a moment',
            'retry_after' => 30,
            'type' => 'database_error',
        ];
    }
    
    return [
        'error' => 'Service unavailable',
        'message' => 'Please try again later',
        'type' => 'generic_error',
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
    } catch (Exception $e) {
        $breaker->recordFailure();
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

## Integration with Existing Code

### Service Wrapper

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
    
    public function processPayment(array $paymentData): array
    {
        return $this->recovery->execute('payment-service', function () use ($paymentData) {
            return Http::post('https://payments.example.com/api/process', $paymentData)->json();
        }, 'process-payment');
    }
}
```

### HTTP Middleware

```php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use WorkDoneRight\ApiGuardian\Services\SmartErrorRecovery;

class ApiRecoveryMiddleware
{
    protected SmartErrorRecovery $recovery;
    
    public function __construct(SmartErrorRecovery $recovery)
    {
        $this->recovery = $recovery;
    }
    
    public function handle(Request $request, Closure $next, ?string $service = null)
    {
        if (!$this->shouldApplyRecovery($request)) {
            return $next($request);
        }
        
        $service = $service ?: 'api-request';
        
        try {
            return $this->recovery->execute($service, function () use ($next, $request) {
                return $next($request);
            });
        } catch (Exception $e) {
            return $this->handleRecoveryFailure($e, $request);
        }
    }
    
    protected function shouldApplyRecovery(Request $request): bool
    {
        return in_array($request->method(), ['GET', 'HEAD']) ||
               str_contains($request->header('User-Agent', ''), 'retry-enabled');
    }
    
    protected function handleRecoveryFailure(Exception $e, Request $request)
    {
        $suggestion = $this->recovery->generateRecoverySuggestion($e);
        
        return response()->json([
            'error' => $e->getMessage(),
            'recovery_suggestion' => $suggestion,
            'timestamp' => now()->toISOString(),
        ], $this->getStatusCode($e));
    }
}
```

### Exception Handler Integration

```php
namespace App\Exceptions;

use WorkDoneRight\ApiGuardian\Exceptions\Handler as GuardianHandler;
use WorkDoneRight\ApiGuardian\Services\SmartErrorRecovery;

class Handler extends GuardianHandler
{
    protected SmartErrorRecovery $recovery;
    
    public function __construct(RecoveryStrategyContract $recovery)
    {
        parent::__construct($recovery);
        $this->recovery = $recover;
    }
    
    public function render($request, Throwable $exception)
    {
        // Check if we can recover from this error
        if ($this->isRecoverableError($exception, $request)) {
            $recoveryResult = $this->attemptRecovery($exception, $request);
            
            if ($recoveryResult !== null) {
                return $recoveryResult;
            }
        }
        
        return parent::render($request, $exception);
    }
    
    protected function attemptRecovery(Throwable $exception, Request $request)
    {
        try {
            return $this->recovery->execute(
                $request->route()->getName() ?? 'api-request',
                function () use ($request) {
                    return app()->handle($request);
                }
            );
        } catch (Throwable $recoveryException) {
            // Recovery failed, let the parent handle it
            return null;
        }
    }
}
```

## Recovery Suggestions

The system automatically generates contextual recovery suggestions based on error patterns:

### Timeout Errors
```json
{
    "type": "timeout",
    "message": "The operation timed out. Consider increasing the timeout or optimizing the request.",
    "actions": [
        "Try again with a longer timeout",
        "Check if the request payload can be optimized",
        "Verify network connectivity"
    ]
}
```

### Connection Errors
```json
{
    "type": "connection",
    "message": "Connection to the service failed. The service may be temporarily unavailable.",
    "actions": [
        "Retry the operation in a few moments",
        "Check service status",
        "Verify network configuration"
    ]
}
```

### Rate Limit Errors
```json
{
    "type": "rate_limit",
    "message": "Rate limit exceeded. Too many requests were made in a short period.",
    "actions": [
        "Wait before retrying",
        "Implement exponential backoff",
        "Consider reducing request frequency"
    ]
}
```

## Monitoring Circuit Breakers

### Dashboard Integration

The monitoring dashboard includes circuit breaker status:

```javascript
// Fetch circuit breaker status
fetch('/api-guardian/circuit-breakers')
    .then(response => response.json())
    .then(breakers => {
        renderCircuitBreakers(breakers);
    });

// Reset a circuit breaker
fetch('/api-guardian/circuit-breakers/reset', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
        service: 'payment-service',
        operation: 'process-payment'
    })
});
```

### Programmatic Monitoring

```php
// Get all circuit breakers
$breakers = CircuitBreaker::all();

// Find problematic breakers
$problematicBreakers = CircuitBreaker::where('state', 'open')
    ->where('opened_at', '<', now()->subMinutes(30))
    ->get();

// Auto-reset old breakers
CircuitBreaker::where('state', 'open')
    ->where('next_attempt_at', '<', now())
    ->update([
        'state' => 'closed',
        'failure_count' => 0,
        'opened_at' => null,
        'next_attempt_at' => null,
    ]);
```

## Best Practices

### 1. Configure Appropriate Thresholds
```php
// For critical external services
'circuit_breaker' => [
    'failure_threshold' => 3,  // Lower threshold for critical services
    'recovery_timeout' => 30,  // Faster recovery
    'success_threshold' => 5,  // Require more successes to close
],
```

### 2. Implement Health Checks
```php
// Health check endpoint for circuit breaker monitoring
Route::get('/health/circuit-breakers', function () {
    $breakers = CircuitBreaker::all();
    $healthy = $breakers->where('state', 'closed')->count();
    $total = $breakers->count();
    
    return response()->json([
        'status' => $healthy === $total ? 'healthy' : 'degraded',
        'healthy_breakers' => $healthy,
        'total_breakers' => $total,
        'details' => $breakers
    ], $healthy === $total ? 200 : 503);
});
```

### 3. Use Specific Service Names
```php
// Good: Specific service identification
$recovery->execute('payment-gateway-stripe', $operation);

// Bad: Generic identification
$recovery->execute('service', $operation);
```

### 4. Monitor Recovery Performance
```php
// Log recovery attempts
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

## Troubleshooting

### Common Issues

1. **Infinite Retries**: Ensure transient error detection is working correctly
2. **Circuit Breaker Stuck Open**: Check `next_attempt_at` and manual reset options
3. **Performance Impact**: Monitor retry delays and circuit breaker thresholds

### Debug Mode

Enable detailed logging:

```php
// config/api-guardian.php
'recovery' => [
    'debug' => env('APP_DEBUG', false),
],
```

### Metrics

Track recovery metrics in your monitoring system:

```php
// In your recovery service
$metrics = [
    'recovery_attempts' => 0,
    'recovery_successes' => 0,
    'recovery_failures' => 0,
    'circuit_breaker_trips' => 0,
];

// Export to Prometheus or similar
// ...