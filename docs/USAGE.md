# Usage Examples

This document contains practical examples of using Laravel API Guardian in real-world scenarios.

## Table of Contents

- [Basic Error Handling](#basic-error-handling)
- [Controller Examples](#controller-examples)
- [Validation Examples](#validation-examples)
- [Authentication & Authorization](#authentication--authorization)
- [Rate Limiting](#rate-limiting)
- [Custom Business Logic Errors](#custom-business-logic-errors)
- [Format Switching](#format-switching)

## Basic Error Handling

### Simple Not Found Error

```php
use WorkDoneRight\ApiGuardian\Facades\ApiGuardian;

class UserController extends Controller
{
    public function show($id)
    {
        $user = User::find($id);
        
        if (!$user) {
            ApiGuardian::notFound("User with ID {$id} not found")->throw();
        }
        
        return response()->json($user);
    }
}
```

### Error with Metadata

```php
public function show($id)
{
    $user = User::find($id);
    
    if (!$user) {
        ApiGuardian::notFound("User not found")
            ->meta([
                'user_id' => $id,
                'attempted_at' => now()->toIso8601String(),
            ])
            ->suggestion('Please check if the user ID is correct')
            ->throw();
    }
    
    return response()->json($user);
}
```

## Controller Examples

### RESTful Controller

```php
use WorkDoneRight\ApiGuardian\Exceptions\ApiException;

class ProductController extends Controller
{
    public function index()
    {
        try {
            $products = Product::paginate(20);
            return response()->json($products);
        } catch (\Exception $e) {
            ApiException::serverError('Failed to fetch products')
                ->code('PRODUCTS_FETCH_FAILED')
                ->context(['error' => $e->getMessage()])
                ->throw();
        }
    }
    
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
        ]);
        
        $product = Product::create($validated);
        
        return response()->json($product, 201);
    }
    
    public function update(Request $request, $id)
    {
        $product = Product::find($id);
        
        if (!$product) {
            ApiException::notFound("Product not found")
                ->meta(['product_id' => $id])
                ->link('https://docs.api.com/products')
                ->throw();
        }
        
        if ($product->is_locked) {
            ApiException::make('Cannot update locked product')
                ->code('PRODUCT_LOCKED')
                ->statusCode(423)
                ->meta([
                    'product_id' => $id,
                    'locked_at' => $product->locked_at,
                    'locked_by' => $product->locked_by_user_id,
                ])
                ->suggestion('Please contact the user who locked this product')
                ->throw();
        }
        
        $validated = $request->validate([
            'name' => 'string|max:255',
            'price' => 'numeric|min:0',
            'stock' => 'integer|min:0',
        ]);
        
        $product->update($validated);
        
        return response()->json($product);
    }
}
```

## Validation Examples

### Custom Validation Error

```php
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);
        
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
        
        $product = Product::find($request->product_id);
        
        if ($product->stock < $request->quantity) {
            ApiException::make('Insufficient stock')
                ->code('INSUFFICIENT_STOCK')
                ->statusCode(400)
                ->meta([
                    'product_id' => $product->id,
                    'requested_quantity' => $request->quantity,
                    'available_stock' => $product->stock,
                ])
                ->suggestion("Only {$product->stock} units available")
                ->recoverable()
                ->throw();
        }
        
        // Process order...
    }
}
```

## Authentication & Authorization

### Unauthorized Access

```php
use WorkDoneRight\ApiGuardian\Facades\ApiGuardian;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);
        
        if (!Auth::attempt($credentials)) {
            ApiGuardian::unauthorized('Invalid credentials')
                ->suggestion('Please check your email and password')
                ->link('https://api.example.com/docs/authentication')
                ->throw();
        }
        
        $token = auth()->user()->createToken('api-token')->plainTextToken;
        
        return response()->json([
            'token' => $token,
            'user' => auth()->user(),
        ]);
    }
}
```

### Forbidden Access

```php
class AdminController extends Controller
{
    public function index()
    {
        if (!auth()->user()->isAdmin()) {
            ApiGuardian::forbidden('Administrator access required')
                ->meta(['user_role' => auth()->user()->role])
                ->suggestion('Contact your administrator for access')
                ->throw();
        }
        
        // Admin only content...
    }
}
```

## Rate Limiting

### Rate Limit Exceeded

```php
use Illuminate\Support\Facades\RateLimiter;

class ApiController extends Controller
{
    public function process(Request $request)
    {
        $key = 'api-rate-limit:' . $request->ip();
        
        if (RateLimiter::tooManyAttempts($key, 60)) {
            $seconds = RateLimiter::availableIn($key);
            
            ApiGuardian::rateLimitExceeded(
                'Too many requests. Please try again later.',
                $seconds
            )
                ->meta([
                    'limit' => 60,
                    'retry_after' => $seconds,
                    'resets_at' => now()->addSeconds($seconds)->toIso8601String(),
                ])
                ->throw();
        }
        
        RateLimiter::hit($key, 60);
        
        // Process request...
    }
}
```

## Custom Business Logic Errors

### Payment Processing

```php
class PaymentController extends Controller
{
    public function processPayment(Request $request)
    {
        $payment = Payment::find($request->payment_id);
        
        if ($payment->status === 'completed') {
            ApiException::make('Payment already processed')
                ->code('PAYMENT_ALREADY_PROCESSED')
                ->statusCode(400)
                ->meta([
                    'payment_id' => $payment->id,
                    'processed_at' => $payment->processed_at,
                    'transaction_id' => $payment->transaction_id,
                ])
                ->link('https://docs.api.com/payments/errors#already-processed')
                ->throw();
        }
        
        try {
            $result = $this->paymentGateway->charge($payment);
        } catch (PaymentGatewayException $e) {
            ApiException::make('Payment processing failed')
                ->code('PAYMENT_GATEWAY_ERROR')
                ->statusCode(502)
                ->meta([
                    'gateway_error' => $e->getMessage(),
                    'gateway_code' => $e->getCode(),
                ])
                ->category('payment_error')
                ->suggestion('Please try again or use a different payment method')
                ->throw();
        }
        
        return response()->json($result);
    }
}
```

### Subscription Management

```php
class SubscriptionController extends Controller
{
    public function upgrade(Request $request)
    {
        $user = auth()->user();
        $subscription = $user->subscription;
        
        if (!$subscription) {
            ApiException::make('No active subscription found')
                ->code('NO_ACTIVE_SUBSCRIPTION')
                ->statusCode(400)
                ->meta(['user_id' => $user->id])
                ->suggestion('Please subscribe to a plan first')
                ->link('https://api.example.com/plans')
                ->recoverable()
                ->throw();
        }
        
        if ($subscription->plan_id === $request->plan_id) {
            ApiException::make('Already on this plan')
                ->code('SAME_PLAN_SELECTED')
                ->statusCode(400)
                ->meta([
                    'current_plan' => $subscription->plan_id,
                    'requested_plan' => $request->plan_id,
                ])
                ->suggestion('Please select a different plan')
                ->recoverable()
                ->throw();
        }
        
        // Process upgrade...
    }
}
```

## Format Switching

### Using Different Formats for Different Routes

```php
// routes/api.php

use Illuminate\Support\Facades\Route;

// JSend format for standard API
Route::prefix('v1')->middleware('api-guardian:jsend')->group(function () {
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/{id}', [UserController::class, 'show']);
});

// RFC 7807 format for admin API
Route::prefix('admin')->middleware('api-guardian:rfc7807')->group(function () {
    Route::get('/users', [AdminUserController::class, 'index']);
});

// JSON:API format for mobile API
Route::prefix('mobile')->middleware('api-guardian:jsonapi')->group(function () {
    Route::get('/users', [MobileUserController::class, 'index']);
});
```

### Runtime Format Switching

```php
use WorkDoneRight\ApiGuardian\Facades\ApiGuardian;

class UserController extends Controller
{
    public function show(Request $request, $id)
    {
        // Switch format based on request
        if ($request->header('X-API-Version') === '2.0') {
            ApiGuardian::useFormatter('rfc7807');
        }
        
        $user = User::find($id);
        
        if (!$user) {
            ApiGuardian::notFound('User not found')->throw();
        }
        
        return response()->json($user);
    }
}
```

## Testing Examples

### Testing Error Responses

```php
use WorkDoneRight\ApiGuardian\Exceptions\ApiException;

test('returns not found error for missing user', function () {
    $response = $this->getJson('/api/users/999');
    
    $response->assertStatus(404)
        ->assertJson([
            'status' => 'fail',
            'code' => 'RESOURCE_NOT_FOUND',
        ]);
});

test('returns validation error for invalid input', function () {
    $response = $this->postJson('/api/users', [
        'email' => 'invalid-email',
    ]);
    
    $response->assertStatus(422)
        ->assertJsonStructure([
            'status',
            'message',
            'data' => [
                'email',
            ],
        ]);
});

test('handles custom exception correctly', function () {
    $this->withoutExceptionHandling();
    
    expect(fn () => ApiException::forbidden('Access denied')->throw())
        ->toThrow(ApiException::class);
});
```

## Advanced Examples

### Exception with Context

```php
class OrderController extends Controller
{
    public function cancel($orderId)
    {
        $order = Order::find($orderId);
        
        if (!$order) {
            ApiGuardian::notFound('Order not found')->throw();
        }
        
        if ($order->status === 'shipped') {
            ApiException::make('Cannot cancel shipped order')
                ->code('ORDER_ALREADY_SHIPPED')
                ->statusCode(400)
                ->meta([
                    'order_id' => $order->id,
                    'status' => $order->status,
                    'shipped_at' => $order->shipped_at,
                    'tracking_number' => $order->tracking_number,
                ])
                ->context([
                    'user_id' => auth()->id(),
                    'order_value' => $order->total,
                    'items_count' => $order->items->count(),
                ])
                ->suggestion('Contact customer support for return instructions')
                ->link('https://support.example.com/returns')
                ->category('business_logic_error')
                ->throw();
        }
        
        $order->cancel();
        
        return response()->json(['message' => 'Order cancelled successfully']);
    }
}
```

These examples should give you a comprehensive understanding of how to use Laravel API Guardian in various scenarios!
