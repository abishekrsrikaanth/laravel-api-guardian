# Quick Installation Guide

This guide will help you get Laravel API Guardian up and running in your Laravel project.

## Requirements

- PHP 8.1 or higher
- Laravel 10.x or 11.x

## Installation Steps

### 1. Install the Package

```bash
composer require workdoneright/laravel-api-guardian
```

### 2. Publish Configuration (Optional)

```bash
php artisan vendor:publish --tag="api-guardian-config"
```

This will create a `config/api-guardian.php` file where you can customize all settings.

### 3. Update Exception Handler

Replace your exception handler in `app/Exceptions/Handler.php`:

**Option A: Extend the API Guardian Handler (Recommended)**

```php
<?php

namespace App\Exceptions;

use WorkDoneRight\ApiGuardian\Exceptions\Handler as ApiGuardianHandler;

class Handler extends ApiGuardianHandler
{
    //
}
```

**Option B: Keep Your Current Handler and Add API Support**

```php
<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Throwable;
use WorkDoneRight\ApiGuardian\Facades\ApiGuardian;

class Handler extends ExceptionHandler
{
    public function render($request, Throwable $e)
    {
        // Handle API requests
        if ($request->expectsJson() || $request->is('api/*')) {
            return ApiGuardian::format($e);
        }

        return parent::render($request, $e);
    }
}
```

### 4. Configure Environment Variables (Optional)

Add to your `.env` file:

```env
API_GUARDIAN_FORMAT=jsend  # Options: jsend, rfc7807, jsonapi
API_GUARDIAN_IDE=vscode    # Options: vscode, phpstorm, sublime
```

### 5. Add Middleware to Routes (Optional)

In `routes/api.php`:

```php
use Illuminate\Support\Facades\Route;

Route::middleware('api-guardian')->group(function () {
    // Your API routes here
});
```

## Verification

Test that everything is working by creating a test route:

```php
// In routes/api.php
use WorkDoneRight\ApiGuardian\Facades\ApiGuardian;

Route::get('/test-error', function () {
    ApiGuardian::notFound('This is a test error')->throw();
});
```

Then visit: `http://your-app.test/api/test-error`

You should see a properly formatted error response!

## Basic Usage Examples

### Throw a Simple Error

```php
use WorkDoneRight\ApiGuardian\Facades\ApiGuardian;

ApiGuardian::notFound('User not found')->throw();
```

### Throw an Error with Metadata

```php
ApiGuardian::notFound('User not found')
    ->meta(['user_id' => 123])
    ->suggestion('Please check if the user ID is correct')
    ->throw();
```

### Using in Controllers

```php
use WorkDoneRight\ApiGuardian\Exceptions\ApiException;

class UserController extends Controller
{
    public function show($id)
    {
        $user = User::find($id);
        
        if (!$user) {
            ApiException::notFound("User with ID {$id} not found")
                ->meta(['user_id' => $id])
                ->throw();
        }
        
        return response()->json($user);
    }
}
```

## Next Steps

- Read the [README.md](README.md) for comprehensive documentation
- Check out [USAGE_EXAMPLES.md](USAGE_EXAMPLES.md) for real-world examples
- Learn how to extend the package in [EXTENDING.md](EXTENDING.md)
- Run `php artisan errors:list` to see available commands

## Troubleshooting

### Error responses not formatted correctly

Make sure you've updated your exception handler (Step 3) and that your routes are either:
- Under the `/api` prefix, or
- Requesting JSON responses (with `Accept: application/json` header)

### Custom format not working

Verify that:
1. You've published the config file
2. The format is enabled in `config/api-guardian.php`
3. You're using the correct format name

### Middleware not working

Ensure you've added the middleware alias in your HTTP Kernel (this is done automatically by the package):

```php
protected $middlewareAliases = [
    'api-guardian' => \WorkDoneRight\ApiGuardian\Http\Middleware\FormatNegotiation::class,
];
```

## Support

If you encounter any issues:
1. Check the [README.md](README.md) documentation
2. Review [USAGE_EXAMPLES.md](USAGE_EXAMPLES.md)
3. Open an issue on GitHub

Happy coding!
