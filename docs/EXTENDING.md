# Extending Laravel API Guardian

This guide shows you how to extend and customize Laravel API Guardian to fit your specific needs.

## Table of Contents

- [Creating Custom Exceptions](#creating-custom-exceptions)
- [Custom Error Formatters](#custom-error-formatters)
- [Custom Error Reporters](#custom-error-reporters)
- [Custom Context Builders](#custom-context-builders)
- [Extending the Exception Handler](#extending-the-exception-handler)

## Creating Custom Exceptions

### Simple Custom Exception

Create your own exception by extending `ApiException`:

```php
namespace App\Exceptions;

use WorkDoneRight\ApiGuardian\Exceptions\ApiException;

class PaymentException extends ApiException
{
    protected string $errorCode = 'PAYMENT_ERROR';
    protected int $statusCode = 402;

    public static function insufficientFunds(float $required, float $available): self
    {
        return static::make('Insufficient funds')
            ->code('INSUFFICIENT_FUNDS')
            ->meta([
                'required_amount' => $required,
                'available_balance' => $available,
                'shortfall' => $required - $available,
            ])
            ->suggestion('Please add funds to your account')
            ->recoverable();
    }

    public static function invalidPaymentMethod(string $method): self
    {
        return static::make("Invalid payment method: {$method}")
            ->code('INVALID_PAYMENT_METHOD')
            ->meta([
                'provided_method' => $method,
                'accepted_methods' => ['visa', 'mastercard', 'amex'],
            ])
            ->suggestion('Please use a valid payment method');
    }
}
```

### Usage

```php
// In your controller
if ($user->balance < $requiredAmount) {
    PaymentException::insufficientFunds($requiredAmount, $user->balance)->throw();
}
```

### Domain-Specific Exception

```php
namespace App\Exceptions;

use WorkDoneRight\ApiGuardian\Exceptions\ApiException;

class SubscriptionException extends ApiException
{
    public static function expired(Subscription $subscription): self
    {
        return static::make('Subscription has expired')
            ->code('SUBSCRIPTION_EXPIRED')
            ->statusCode(403)
            ->meta([
                'subscription_id' => $subscription->id,
                'expired_at' => $subscription->expired_at,
                'plan' => $subscription->plan->name,
            ])
            ->suggestion('Please renew your subscription to continue')
            ->link('https://api.example.com/subscriptions/renew')
            ->category('subscription_error');
    }

    public static function planLimitReached(string $feature, int $limit): self
    {
        return static::make("Plan limit reached for {$feature}")
            ->code('PLAN_LIMIT_REACHED')
            ->statusCode(403)
            ->meta([
                'feature' => $feature,
                'current_limit' => $limit,
            ])
            ->suggestion('Upgrade your plan to increase limits')
            ->link('https://api.example.com/plans/upgrade')
            ->recoverable();
    }
}
```

## Custom Error Formatters

### Creating a Custom Formatter

Implement the `ErrorFormatterContract` interface:

```php
namespace App\Formatters;

use Illuminate\Http\JsonResponse;
use Throwable;
use WorkDoneRight\ApiGuardian\Contracts\ErrorFormatterContract;

class CustomApiFormatter implements ErrorFormatterContract
{
    public function format(Throwable $exception, ?int $statusCode = null): JsonResponse
    {
        $statusCode = $statusCode ?? $this->getStatusCode($exception);
        $response = $this->buildErrorResponse($exception, $statusCode);

        return response()->json($response, $statusCode);
    }

    public function buildErrorResponse(Throwable $exception, int $statusCode): array
    {
        return [
            'success' => false,
            'error' => [
                'message' => $exception->getMessage(),
                'code' => $this->getErrorCode($exception),
                'status' => $statusCode,
                'timestamp' => now()->toIso8601String(),
            ],
        ];
    }

    public function getStatusCode(Throwable $exception): int
    {
        if (method_exists($exception, 'getStatusCode')) {
            return $exception->getStatusCode();
        }

        return 500;
    }

    protected function getErrorCode(Throwable $exception): string
    {
        if (method_exists($exception, 'getErrorCode')) {
            return $exception->getErrorCode();
        }

        return 'INTERNAL_ERROR';
    }
}
```

### Register Custom Formatter

In your `config/api-guardian.php`:

```php
'formats' => [
    'custom' => [
        'enabled' => true,
        'formatter' => \App\Formatters\CustomApiFormatter::class,
    ],
],
```

### Use Custom Formatter

```php
use WorkDoneRight\ApiGuardian\Facades\ApiGuardian;

// In your controller or service provider
ApiGuardian::useFormatter(new CustomApiFormatter());

// Or by name if registered in config
ApiGuardian::useFormatter('custom');
```

## Custom Error Reporters

### Creating a Custom Reporter

```php
namespace App\Reporters;

use Throwable;

class SlackErrorReporter
{
    protected string $webhookUrl;

    public function __construct(string $webhookUrl)
    {
        $this->webhookUrl = $webhookUrl;
    }

    public function report(Throwable $exception): void
    {
        if (!$this->shouldReport($exception)) {
            return;
        }

        $payload = $this->buildPayload($exception);

        try {
            Http::post($this->webhookUrl, $payload);
        } catch (\Exception $e) {
            // Silently fail - don't let reporting errors break the app
        }
    }

    protected function shouldReport(Throwable $exception): bool
    {
        // Only report 5xx errors
        if (method_exists($exception, 'getStatusCode')) {
            return $exception->getStatusCode() >= 500;
        }

        return true;
    }

    protected function buildPayload(Throwable $exception): array
    {
        return [
            'text' => 'API Error Occurred',
            'blocks' => [
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => "*Error:* {$exception->getMessage()}",
                    ],
                ],
                [
                    'type' => 'section',
                    'fields' => [
                        [
                            'type' => 'mrkdwn',
                            'text' => "*File:*\n{$exception->getFile()}",
                        ],
                        [
                            'type' => 'mrkdwn',
                            'text' => "*Line:*\n{$exception->getLine()}",
                        ],
                    ],
                ],
            ],
        ];
    }
}
```

### Register in Service Provider

```php
namespace App\Providers;

use App\Reporters\SlackErrorReporter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->app->singleton(SlackErrorReporter::class, function ($app) {
            return new SlackErrorReporter(config('services.slack.error_webhook'));
        });
    }
}
```

### Use in Exception Handler

```php
namespace App\Exceptions;

use App\Reporters\SlackErrorReporter;
use WorkDoneRight\ApiGuardian\Exceptions\Handler as ApiGuardianHandler;

class Handler extends ApiGuardianHandler
{
    public function report(Throwable $e): void
    {
        parent::report($e);

        // Report to Slack
        app(SlackErrorReporter::class)->report($e);
    }
}
```

## Custom Context Builders

### Creating a Custom Context Builder

```php
namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Throwable;

class CustomErrorContext
{
    public static function build(Throwable $exception): array
    {
        $context = [];

        // Add custom application state
        $context['app_version'] = config('app.version');
        $context['environment'] = app()->environment();

        // Add custom user context
        if (auth()->check()) {
            $context['user'] = [
                'id' => auth()->id(),
                'email' => auth()->user()->email,
                'plan' => auth()->user()->subscription?->plan->name,
                'tenant_id' => auth()->user()->tenant_id,
            ];
        }

        // Add feature flags
        $context['feature_flags'] = static::getActiveFeatureFlags();

        // Add recent user actions
        $context['breadcrumbs'] = static::getBreadcrumbs();

        // Add system metrics
        $context['metrics'] = [
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
            'execution_time' => microtime(true) - LARAVEL_START,
        ];

        return $context;
    }

    protected static function getActiveFeatureFlags(): array
    {
        // Get enabled feature flags for the current user
        return Cache::remember('feature_flags:' . auth()->id(), 300, function () {
            return FeatureFlag::active()->pluck('name')->toArray();
        });
    }

    protected static function getBreadcrumbs(): array
    {
        // Get recent actions from session or cache
        return session('user_breadcrumbs', []);
    }
}
```

### Using Custom Context in Exceptions

```php
use App\Support\CustomErrorContext;

ApiException::make('Error occurred')
    ->context(CustomErrorContext::build($exception))
    ->throw();
```

## Extending the Exception Handler

### Advanced Exception Handler

```php
namespace App\Exceptions;

use Illuminate\Http\Request;
use Throwable;
use WorkDoneRight\ApiGuardian\Exceptions\Handler as ApiGuardianHandler;
use WorkDoneRight\ApiGuardian\Facades\ApiGuardian;

class Handler extends ApiGuardianHandler
{
    /**
     * Custom logic to determine if request should be handled as API
     */
    protected function shouldHandleApiException(Request $request): bool
    {
        // Handle GraphQL requests
        if ($request->is('graphql')) {
            return true;
        }

        // Handle mobile app requests
        if ($request->header('X-App-Type') === 'mobile') {
            return true;
        }

        return parent::shouldHandleApiException($request);
    }

    /**
     * Add custom headers to error responses
     */
    protected function renderApiException(Throwable $exception): JsonResponse
    {
        $response = parent::renderApiException($exception);

        // Add custom headers
        $response->header('X-Error-ID', $this->getErrorId($exception));
        $response->header('X-Request-ID', request()->header('X-Request-ID'));

        // Add rate limit headers if applicable
        if ($this->isRateLimitException($exception)) {
            $response->header('X-RateLimit-Limit', 60);
            $response->header('X-RateLimit-Remaining', 0);
            $response->header('Retry-After', $exception->getMeta()['retry_after'] ?? 60);
        }

        return $response;
    }

    /**
     * Report exceptions to multiple services
     */
    public function report(Throwable $e): void
    {
        parent::report($e);

        // Custom reporting logic
        if ($this->shouldReportToDatadog($e)) {
            $this->reportToDatadog($e);
        }

        if ($this->shouldNotifyTeam($e)) {
            $this->notifyTeam($e);
        }
    }

    protected function shouldReportToDatadog(Throwable $exception): bool
    {
        return app()->environment('production') && 
               method_exists($exception, 'getStatusCode') &&
               $exception->getStatusCode() >= 500;
    }

    protected function reportToDatadog(Throwable $exception): void
    {
        // Implement Datadog reporting
    }

    protected function shouldNotifyTeam(Throwable $exception): bool
    {
        return method_exists($exception, 'getCategory') &&
               $exception->getCategory() === 'critical';
    }

    protected function notifyTeam(Throwable $exception): void
    {
        // Send notification to team (Slack, PagerDuty, etc.)
    }

    protected function getErrorId(Throwable $exception): string
    {
        if (method_exists($exception, 'getContext')) {
            $context = $exception->getContext();
            return $context['error_id'] ?? '';
        }

        return '';
    }

    protected function isRateLimitException(Throwable $exception): bool
    {
        return method_exists($exception, 'getStatusCode') &&
               $exception->getStatusCode() === 429;
    }
}
```

## Advanced: Custom Middleware for Error Context

### Context Collection Middleware

```php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CollectErrorContext
{
    public function handle(Request $request, Closure $next)
    {
        // Start breadcrumb collection
        session()->push('user_breadcrumbs', [
            'timestamp' => now()->toIso8601String(),
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'ip' => $request->ip(),
        ]);

        // Limit breadcrumbs to last 10
        $breadcrumbs = session('user_breadcrumbs', []);
        if (count($breadcrumbs) > 10) {
            session(['user_breadcrumbs' => array_slice($breadcrumbs, -10)]);
        }

        return $next($request);
    }
}
```

### Register Middleware

```php
// In app/Http/Kernel.php
protected $middlewareGroups = [
    'api' => [
        \App\Http\Middleware\CollectErrorContext::class,
        // ... other middleware
    ],
];
```

These examples should give you a solid foundation for extending Laravel API Guardian to meet your specific needs!
