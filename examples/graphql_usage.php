<?php

declare(strict_types=1);

/**
 * GraphQL Error Format - Usage Examples
 *
 * This file demonstrates how to use the GraphQL error format
 * with Laravel API Guardian.
 */

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use WorkDoneRight\ApiGuardian\Exceptions\ApiException;
use WorkDoneRight\ApiGuardian\Facades\ApiGuardian;

// Example 1: Basic GraphQL Error
Route::get('/api/graphql/example1', function () {
    // Configure to use GraphQL format
    ApiGuardian::useFormatter('graphql');

    // Throw a simple error
    throw ApiException::notFound('User not found')
        ->code('USER_NOT_FOUND');

    // Response:
    // {
    //   "errors": [{
    //     "message": "User not found",
    //     "extensions": {
    //       "code": "USER_NOT_FOUND",
    //       "category": "not_found"
    //     }
    //   }],
    //   "data": null
    // }
});

// Example 2: GraphQL Error with Query Location
Route::get('/api/graphql/example2', function () {
    ApiGuardian::useFormatter('graphql');

    throw ApiException::make('Cannot query field "invalidField" on type "User"')
        ->code('GRAPHQL_VALIDATION_FAILED')
        ->meta([
            'line' => 5,
            'column' => 10,
        ]);

    // Response includes locations:
    // {
    //   "errors": [{
    //     "message": "Cannot query field \"invalidField\" on type \"User\"",
    //     "locations": [{"line": 5, "column": 10}],
    //     "extensions": {
    //       "code": "GRAPHQL_VALIDATION_FAILED",
    //       "category": "client"
    //     }
    //   }],
    //   "data": null
    // }
});

// Example 3: GraphQL Error with Field Path
Route::get('/api/graphql/example3', function () {
    ApiGuardian::useFormatter('graphql');

    throw ApiException::make('Email must be unique')
        ->code('VALIDATION_ERROR')
        ->meta([
            'path' => ['user', 'email'],
        ])
        ->suggestion('Use a different email address');

    // Response includes path:
    // {
    //   "errors": [{
    //     "message": "Email must be unique",
    //     "path": ["user", "email"],
    //     "extensions": {
    //       "code": "VALIDATION_ERROR",
    //       "category": "client",
    //       "suggestion": "Use a different email address"
    //     }
    //   }],
    //   "data": null
    // }
});

// Example 4: Validation Errors (Multiple)
Route::post('/api/graphql/example4', function (Request $request) {
    ApiGuardian::useFormatter('graphql');

    $request->validate([
        'email' => 'required|email',
        'password' => 'required|min:8',
        'name' => 'required',
    ]);

    // If validation fails, response will have multiple errors:
    // {
    //   "errors": [
    //     {
    //       "message": "The email field is required.",
    //       "extensions": {
    //         "code": "FIELD_REQUIRED",
    //         "category": "validation",
    //         "field": "email",
    //         "validation": true
    //       }
    //     },
    //     {
    //       "message": "The password field is required.",
    //       "extensions": {
    //         "code": "FIELD_REQUIRED",
    //         "category": "validation",
    //         "field": "password",
    //         "validation": true
    //       }
    //     }
    //   ],
    //   "data": null
    // }
});

// Example 5: Enhanced Error with All Features
Route::get('/api/graphql/example5', function () {
    ApiGuardian::useFormatter('graphql');

    throw ApiException::make('Payment processing failed')
        ->code('PAYMENT_FAILED')
        ->statusCode(402)
        ->suggestion('Please check your payment method and try again')
        ->link('https://docs.example.com/payments')
        ->meta([
            'paymentId' => 'pay_123456',
            'amount' => 99.99,
            'currency' => 'USD',
            'path' => ['createOrder', 'payment'],
        ]);

    // Full featured response:
    // {
    //   "errors": [{
    //     "message": "Payment processing failed",
    //     "path": ["createOrder", "payment"],
    //     "extensions": {
    //       "code": "PAYMENT_FAILED",
    //       "category": "client",
    //       "paymentId": "pay_123456",
    //       "amount": 99.99,
    //       "currency": "USD",
    //       "suggestion": "Please check your payment method and try again",
    //       "documentation": "https://docs.example.com/payments"
    //     }
    //   }],
    //   "data": null
    // }
});

// Example 6: Authentication Error
Route::get('/api/graphql/example6', function () {
    ApiGuardian::useFormatter('graphql');

    throw ApiException::unauthorized('Invalid or expired token')
        ->code('INVALID_TOKEN')
        ->suggestion('Please login again');

    // Response with authentication category:
    // {
    //   "errors": [{
    //     "message": "Invalid or expired token",
    //     "extensions": {
    //       "code": "INVALID_TOKEN",
    //       "category": "authentication",
    //       "suggestion": "Please login again"
    //     }
    //   }],
    //   "data": null
    // }
});

// Example 7: Rate Limiting Error
Route::get('/api/graphql/example7', function () {
    ApiGuardian::useFormatter('graphql');

    throw ApiException::make('Rate limit exceeded')
        ->code('RATE_LIMIT_EXCEEDED')
        ->statusCode(429)
        ->meta([
            'limit' => 100,
            'remaining' => 0,
            'resetAt' => now()->addMinutes(15)->toIso8601String(),
        ])
        ->suggestion('Please wait before making more requests');

    // Response with rate_limit category:
    // {
    //   "errors": [{
    //     "message": "Rate limit exceeded",
    //     "extensions": {
    //       "code": "RATE_LIMIT_EXCEEDED",
    //       "category": "rate_limit",
    //       "limit": 100,
    //       "remaining": 0,
    //       "resetAt": "2026-02-04T06:00:00+00:00",
    //       "suggestion": "Please wait before making more requests"
    //     }
    //   }],
    //   "data": null
    // }
});

// Example 8: Global Configuration
// In config/api-guardian.php:
/*
return [
    'default_format' => 'graphql',  // Use GraphQL format by default

    'context' => [
        'include_error_id' => true,
        'include_timestamp' => true,
        'include_suggestions' => true,
    ],
];
*/

// Now all errors will use GraphQL format automatically:
Route::get('/api/user/{id}', function ($id) {
    // No need to call ApiGuardian::useFormatter('graphql')
    // It's already configured globally

    throw ApiException::notFound('User not found');
});
