# Error Monitoring Dashboard

The Laravel API Guardian package now includes a comprehensive **Real-time Error Monitoring Dashboard** that provides instant visibility into your API health and error patterns.

## Features

### 📊 Real-time Analytics
- Live error feed with automatic updates
- Error trend analysis with hourly distribution
- Performance impact metrics
- Top error patterns identification

### 🔍 Advanced Filtering
- Search by error message, code, or URL
- Filter by HTTP status codes
- Separate resolved/unresolved errors
- Date range filtering

### 📈 Trend Analysis
- 7-day analytics with configurable ranges
- Hourly error distribution
- Status code breakdown
- Error frequency tracking

### 🛡️ Security & Privacy
- Automatic sensitive data redaction
- PII masking for GDPR compliance
- Role-based access control
- Configurable data retention

## Installation

1. **Publish the migration:**
```bash
php artisan vendor:publish --tag="api-guardian-migrations"
```

2. **Run the migration:**
```bash
php artisan migrate
```

3. **Publish the configuration:**
```bash
php artisan vendor:publish --tag="api-guardian-config"
```

## Configuration

Add these settings to your `.env` file:

```env
API_GUARDIAN_MONITORING_ENABLED=true
API_GUARDIAN_RETENTION_DAYS=30
API_GUARDIAN_DB_CONNECTION=null  # Use default connection
```

### Dashboard Configuration

```php
// config/api-guardian.php
'monitoring' => [
    'enabled' => true,
    'retention_days' => 30,
    'live_update_interval' => 5000, // milliseconds
    'max_live_errors' => 100,
    'analytics_days' => 7,
    'dashboard_routes' => [
        'enabled' => true,
        'middleware' => ['web', 'auth'],
        'prefix' => 'api-guardian',
    ],
],
```

## Usage

### Accessing the Dashboard

Visit `/api-guardian/dashboard` in your browser (requires authentication).

### API Endpoints

The dashboard also provides JSON API endpoints for integration:

```bash
# Main dashboard data
GET /api-guardian/dashboard

# Analytics data
GET /api-guardian/analytics?days=7

# Live error feed
GET /api-guardian/errors/live?limit=50

# Search errors
GET /api-guardian/errors/search?search=keyword&status_code=404&resolved=false

# Error details
GET /api-guardian/errors/{id}

# Resolve error
POST /api-guardian/errors/{id}/resolve

# Circuit breaker status
GET /api-guardian/circuit-breakers

# Export errors
GET /api-guardian/export?format=csv&days=7
```

### Integration Examples

#### Frontend Integration

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

#### Slack Integration

```php
// In your AppServiceProvider or similar
use Illuminate\Support\Facades\Http;

Event::listen('api-guardian.error.collected', function ($error) {
    if ($error->status_code >= 500) {
        Http::post(env('SLACK_WEBHOOK_URL'), [
            'text' => "🚨 Critical API Error: {$error->message}",
            'attachments' => [[
                'fields' => [
                    ['title' => 'Error ID', 'value' => $error->error_id, 'short' => true],
                    ['title' => 'Status Code', 'value' => $error->status_code, 'short' => true],
                    ['title' => 'URL', 'value' => $error->request_url],
                ]
            ]]
        ]);
    }
});
```

## Customization

### Custom Error Collector

```php
namespace App\Services;

use WorkDoneRight\ApiGuardian\Services\ErrorCollector;

class CustomErrorCollector extends ErrorCollector
{
    protected function sanitizeRequestData(array $data): array
    {
        $data = parent::sanitizeRequestData($data);
        
        // Add your custom sanitization logic
        // For example, redact business-specific sensitive fields
        
        return $data;
    }
}
```

Register your custom collector:

```php
// AppServiceProvider.php
$this->app->singleton(
    ErrorCollectorContract::class,
    CustomErrorCollector::class
);
```

### Custom Dashboard Routes

```php
// routes/web.php
Route::middleware(['web', 'auth'])->prefix('admin/api-errors')->group(function () {
    Route::get('/', [DashboardController::class, 'index']);
    Route::get('/analytics', [DashboardController::class, 'analytics']);
    Route::get('/errors', [DashboardController::class, 'searchErrors']);
    Route::post('/errors/{error}/resolve', [DashboardController::class, 'resolveError']);
});
```

## Performance Considerations

### Database Optimization

Ensure proper indexing:

```sql
-- These are automatically created by the migration
CREATE INDEX idx_api_guardian_errors_status_created ON api_guardian_errors(status_code, created_at);
CREATE INDEX idx_api_guardian_errors_resolved_created ON api_guardian_errors(is_resolved, created_at);
CREATE INDEX idx_api_guardian_errors_code_created ON api_guardian_errors(error_code, created_at);
```

### Data Retention

Configure automatic cleanup in your console kernel:

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    $schedule->command('model:prune', [
        '--model' => \WorkDoneRight\ApiGuardian\Models\ApiError::class,
        '--days' => config('api-guardian.monitoring.retention_days', 30)
    ])->daily();
}
```

### Caching

Cache frequently accessed analytics:

```php
// In your controller
public function analytics(Request $request)
{
    $days = $request->get('days', 7);
    $cacheKey = "api-guardian.analytics.{$days}";
    
    return Cache::remember($cacheKey, 300, function () use ($days) {
        return $this->errorCollector->getAnalytics($days);
    });
}
```

## Security

### Authentication & Authorization

The dashboard routes require authentication by default. You can customize the middleware:

```php
// config/api-guardian.php
'monitoring' => [
    'dashboard_routes' => [
        'middleware' => ['web', 'auth', 'role:admin'],
    ],
],
```

### IP Restrictions

For additional security, add IP restrictions:

```php
// In your routes or middleware
Route::middleware(['auth', 'ip.restriction:192.168.1.0/24'])
    ->prefix('/api-guardian')
    ->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index']);
    });
```

## Troubleshooting

### Common Issues

1. **Missing Dashboard Routes**: Ensure `monitoring.dashboard_routes.enabled` is `true`
2. **No Error Data**: Verify the ErrorCollector is properly integrated
3. **Performance Issues**: Check database indexes and consider caching

### Debug Mode

Enable debug logging:

```php
// config/api-guardian.php
'monitoring' => [
    'debug' => env('APP_DEBUG', false),
],
```

### Logs

Monitor the logs for issues:

```bash
tail -f storage/logs/laravel.log | grep "api-guardian"
```