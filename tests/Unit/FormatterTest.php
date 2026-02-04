<?php

declare(strict_types=1);

use Illuminate\Support\MessageBag;
use Illuminate\Validation\ValidationException;
use WorkDoneRight\ApiGuardian\Exceptions\ApiException;
use WorkDoneRight\ApiGuardian\Formatters\GraphQLFormatter;
use WorkDoneRight\ApiGuardian\Formatters\JSendFormatter;
use WorkDoneRight\ApiGuardian\Formatters\JsonApiFormatter;
use WorkDoneRight\ApiGuardian\Formatters\Rfc7807Formatter;

describe('JSendFormatter', function () {
    it('formats error response correctly', function () {
        $formatter = resolve(JSendFormatter::class);
        $exception = ApiException::make('Resource not found')
            ->code('RESOURCE_NOT_FOUND')
            ->statusCode(404);

        $response = $formatter->format($exception, 404);
        $data = $response->getData(true);

        expect($data)->toHaveKey('status')
            ->and($data['status'])->toBe('fail')
            ->and($data)->toHaveKey('message')
            ->and($data['message'])->toBe('Resource not found')
            ->and($data)->toHaveKey('code')
            ->and($data['code'])->toBe('RESOURCE_NOT_FOUND');
    });

    it('uses fail status for 4xx errors', function () {
        $formatter = resolve(JSendFormatter::class);
        $exception = ApiException::make('Bad request')->statusCode(400);

        $response = $formatter->format($exception, 400);
        $data = $response->getData(true);

        expect($data['status'])->toBe('fail');
    });

    it('uses error status for 5xx errors', function () {
        $formatter = resolve(JSendFormatter::class);
        $exception = ApiException::make('Server error')->statusCode(500);

        $response = $formatter->format($exception, 500);
        $data = $response->getData(true);

        expect($data['status'])->toBe('error');
    });

    it('includes metadata when provided', function () {
        $formatter = resolve(JSendFormatter::class);
        $exception = ApiException::make('Error')
            ->meta(['key' => 'value', 'count' => 42]);

        $response = $formatter->format($exception, 500);
        $data = $response->getData(true);

        expect($data)->toHaveKey('data')
            ->and($data['data'])->toHaveKey('key')
            ->and($data['data']['key'])->toBe('value')
            ->and($data['data']['count'])->toBe(42);
    });

    it('includes suggestion when provided', function () {
        config(['api-guardian.context.include_suggestions' => true]);

        $formatter = resolve(JSendFormatter::class);
        $exception = ApiException::make('Error')
            ->suggestion('Try again later');

        $response = $formatter->format($exception, 500);
        $data = $response->getData(true);

        expect($data['data'])->toHaveKey('suggestion')
            ->and($data['data']['suggestion'])->toBe('Try again later');
    });

    it('formats validation errors correctly', function () {
        $formatter = resolve(JSendFormatter::class);

        $messageBag = new MessageBag([
            'email' => ['The email field is required.'],
            'password' => ['The password must be at least 8 characters.'],
        ]);

        $exception = ValidationException::withMessages([
            'email' => 'The email field is required.',
            'password' => 'The password must be at least 8 characters.',
        ]);

        $response = $formatter->format($exception, 422);
        $data = $response->getData(true);

        expect($data['status'])->toBe('fail')
            ->and($data)->toHaveKey('data')
            ->and($data['data'])->toHaveKey('email')
            ->and($data['data'])->toHaveKey('password');
    });
});

describe('RFC7807Formatter', function () {
    it('formats error response according to RFC 7807', function () {
        $formatter = resolve(Rfc7807Formatter::class);
        $exception = ApiException::make('Resource not found')
            ->code('RESOURCE_NOT_FOUND')
            ->statusCode(404);

        $response = $formatter->format($exception, 404);
        $data = $response->getData(true);

        expect($data)->toHaveKey('type')
            ->and($data)->toHaveKey('title')
            ->and($data)->toHaveKey('status')
            ->and($data['status'])->toBe(404)
            ->and($data)->toHaveKey('detail')
            ->and($data['detail'])->toBe('Resource not found');
    });

    it('includes instance identifier', function () {
        config(['api-guardian.context.include_error_id' => true]);

        $formatter = resolve(Rfc7807Formatter::class);
        $exception = ApiException::make('Error');

        $response = $formatter->format($exception, 500);
        $data = $response->getData(true);

        expect($data)->toHaveKey('instance');
    });

    it('includes suggestion in problem details', function () {
        config(['api-guardian.context.include_suggestions' => true]);

        $formatter = resolve(Rfc7807Formatter::class);
        $exception = ApiException::make('Error')
            ->suggestion('Check your input');

        $response = $formatter->format($exception, 400);
        $data = $response->getData(true);

        expect($data)->toHaveKey('suggestion')
            ->and($data['suggestion'])->toBe('Check your input');
    });
});

describe('JsonApiFormatter', function () {
    it('formats error response according to JSON:API spec', function () {
        $formatter = resolve(JsonApiFormatter::class);
        $exception = ApiException::make('Resource not found')
            ->code('RESOURCE_NOT_FOUND')
            ->statusCode(404);

        $response = $formatter->format($exception, 404);
        $data = $response->getData(true);

        expect($data)->toHaveKey('errors')
            ->and($data['errors'])->toBeArray()
            ->and(count($data['errors']))->toBe(1)
            ->and($data['errors'][0])->toHaveKey('status')
            ->and($data['errors'][0]['status'])->toBe('404')
            ->and($data['errors'][0])->toHaveKey('code')
            ->and($data['errors'][0]['code'])->toBe('RESOURCE_NOT_FOUND')
            ->and($data['errors'][0])->toHaveKey('title')
            ->and($data['errors'][0])->toHaveKey('detail')
            ->and($data['errors'][0]['detail'])->toBe('Resource not found');
    });

    it('includes error id in errors array', function () {
        config(['api-guardian.context.include_error_id' => true]);

        $formatter = resolve(JsonApiFormatter::class);
        $exception = ApiException::make('Error');

        $response = $formatter->format($exception, 500);
        $data = $response->getData(true);

        expect($data['errors'][0])->toHaveKey('id');
    });

    it('includes meta information', function () {
        $formatter = resolve(JsonApiFormatter::class);
        $exception = ApiException::make('Error')
            ->meta(['additional' => 'data']);

        $response = $formatter->format($exception, 500);
        $data = $response->getData(true);

        expect($data['errors'][0])->toHaveKey('meta')
            ->and($data['errors'][0]['meta'])->toHaveKey('additional')
            ->and($data['errors'][0]['meta']['additional'])->toBe('data');
    });

    it('includes suggestion in meta', function () {
        config(['api-guardian.context.include_suggestions' => true]);

        $formatter = resolve(JsonApiFormatter::class);
        $exception = ApiException::make('Error')
            ->suggestion('Try this instead');

        $response = $formatter->format($exception, 400);
        $data = $response->getData(true);

        expect($data['errors'][0]['meta'])->toHaveKey('suggestion')
            ->and($data['errors'][0]['meta']['suggestion'])->toBe('Try this instead');
    });

    it('formats multiple validation errors', function () {
        $formatter = resolve(JsonApiFormatter::class);

        $exception = ValidationException::withMessages([
            'email' => 'The email field is required.',
            'password' => 'The password must be at least 8 characters.',
        ]);

        $response = $formatter->format($exception, 422);
        $data = $response->getData(true);

        expect($data['errors'])->toBeArray()
            ->and(count($data['errors']))->toBe(2);
    });
});

describe('All Formatters', function () {
    it('return correct HTTP status codes', function () {
        $formatters = [
            resolve(JSendFormatter::class),
            resolve(Rfc7807Formatter::class),
            resolve(JsonApiFormatter::class),
            resolve(GraphQLFormatter::class),
        ];

        foreach ($formatters as $formatter) {
            $exception = ApiException::make('Test')->statusCode(404);
            $response = $formatter->format($exception, 404);

            expect($response->getStatusCode())->toBe(404);
        }
    });

    it('handle null status codes gracefully', function () {
        $formatters = [
            resolve(JSendFormatter::class),
            resolve(Rfc7807Formatter::class),
            resolve(JsonApiFormatter::class),
            resolve(GraphQLFormatter::class),
        ];

        foreach ($formatters as $formatter) {
            $exception = new Exception('Test error');
            $response = $formatter->format($exception, null);

            expect($response->getStatusCode())->toBe(500); // Default to 500
        }
    });
});

describe('GraphQLFormatter', function () {
    it('formats error response according to GraphQL spec', function () {
        $formatter = resolve(GraphQLFormatter::class);
        $exception = ApiException::make('Resource not found')
            ->code('RESOURCE_NOT_FOUND')
            ->statusCode(404);

        $response = $formatter->format($exception, 404);
        $data = $response->getData(true);

        expect($data)->toHaveKey('errors')
            ->and($data)->toHaveKey('data')
            ->and($data['data'])->toBeNull()
            ->and($data['errors'])->toBeArray()
            ->and(count($data['errors']))->toBe(1)
            ->and($data['errors'][0])->toHaveKey('message')
            ->and($data['errors'][0]['message'])->toBe('Resource not found')
            ->and($data['errors'][0])->toHaveKey('extensions')
            ->and($data['errors'][0]['extensions'])->toHaveKey('code')
            ->and($data['errors'][0]['extensions']['code'])->toBe('RESOURCE_NOT_FOUND')
            ->and($data['errors'][0]['extensions'])->toHaveKey('category')
            ->and($data['errors'][0]['extensions']['category'])->toBe('not_found');
    });

    it('includes locations when provided in metadata', function () {
        $formatter = resolve(GraphQLFormatter::class);
        $exception = ApiException::make('Invalid field')
            ->meta(['line' => 5, 'column' => 10]);

        $response = $formatter->format($exception, 400);
        $data = $response->getData(true);

        expect($data['errors'][0])->toHaveKey('locations')
            ->and($data['errors'][0]['locations'])->toBeArray()
            ->and($data['errors'][0]['locations'][0])->toHaveKey('line')
            ->and($data['errors'][0]['locations'][0]['line'])->toBe(5)
            ->and($data['errors'][0]['locations'][0])->toHaveKey('column')
            ->and($data['errors'][0]['locations'][0]['column'])->toBe(10);
    });

    it('includes path when provided in metadata', function () {
        $formatter = resolve(GraphQLFormatter::class);
        $exception = ApiException::make('Field error')
            ->meta(['path' => ['user', 'email']]);

        $response = $formatter->format($exception, 400);
        $data = $response->getData(true);

        expect($data['errors'][0])->toHaveKey('path')
            ->and($data['errors'][0]['path'])->toBe(['user', 'email']);
    });

    it('categorizes authentication errors correctly', function () {
        $formatter = resolve(GraphQLFormatter::class);
        $exception = ApiException::make('Unauthorized')->statusCode(401);

        $response = $formatter->format($exception, 401);
        $data = $response->getData(true);

        expect($data['errors'][0]['extensions']['category'])->toBe('authentication');
    });

    it('categorizes authorization errors correctly', function () {
        $formatter = resolve(GraphQLFormatter::class);
        $exception = ApiException::make('Forbidden')->statusCode(403);

        $response = $formatter->format($exception, 403);
        $data = $response->getData(true);

        expect($data['errors'][0]['extensions']['category'])->toBe('authorization');
    });

    it('categorizes internal errors correctly', function () {
        $formatter = resolve(GraphQLFormatter::class);
        $exception = ApiException::make('Server error')->statusCode(500);

        $response = $formatter->format($exception, 500);
        $data = $response->getData(true);

        expect($data['errors'][0]['extensions']['category'])->toBe('internal');
    });

    it('formats validation errors as multiple GraphQL errors', function () {
        $formatter = resolve(GraphQLFormatter::class);

        $exception = ValidationException::withMessages([
            'email' => 'The email field is required.',
            'password' => 'The password must be at least 8 characters.',
        ]);

        $response = $formatter->format($exception, 422);
        $data = $response->getData(true);

        expect($data['errors'])->toBeArray()
            ->and(count($data['errors']))->toBe(2)
            ->and($data['errors'][0]['extensions']['category'])->toBe('validation')
            ->and($data['errors'][0]['extensions'])->toHaveKey('field')
            ->and($data['errors'][0]['extensions']['validation'])->toBeTrue();
    });

    it('includes error ID in extensions', function () {
        config(['api-guardian.context.include_error_id' => true]);

        $formatter = resolve(GraphQLFormatter::class);
        $exception = ApiException::make('Error');

        $response = $formatter->format($exception, 500);
        $data = $response->getData(true);

        expect($data['errors'][0]['extensions'])->toHaveKey('errorId');
    });

    it('includes timestamp in extensions', function () {
        config(['api-guardian.context.include_timestamp' => true]);

        $formatter = resolve(GraphQLFormatter::class);
        $exception = ApiException::make('Error');

        $response = $formatter->format($exception, 500);
        $data = $response->getData(true);

        expect($data['errors'][0]['extensions'])->toHaveKey('timestamp');
    });

    it('includes suggestion in extensions', function () {
        config(['api-guardian.context.include_suggestions' => true]);

        $formatter = resolve(GraphQLFormatter::class);
        $exception = ApiException::make('Error')
            ->suggestion('Try this instead');

        $response = $formatter->format($exception, 400);
        $data = $response->getData(true);

        expect($data['errors'][0]['extensions'])->toHaveKey('suggestion')
            ->and($data['errors'][0]['extensions']['suggestion'])->toBe('Try this instead');
    });

    it('includes documentation link in extensions', function () {
        $formatter = resolve(GraphQLFormatter::class);
        $exception = ApiException::make('Error')
            ->link('https://docs.example.com/errors');

        $response = $formatter->format($exception, 400);
        $data = $response->getData(true);

        expect($data['errors'][0]['extensions'])->toHaveKey('documentation')
            ->and($data['errors'][0]['extensions']['documentation'])->toBe('https://docs.example.com/errors');
    });

    it('includes debug information in extensions when enabled', function () {
        config(['app.debug' => true]);
        config(['api-guardian.context.include_debug_info' => true]);

        $formatter = resolve(GraphQLFormatter::class);
        $exception = ApiException::make('Error');

        $response = $formatter->format($exception, 500);
        $data = $response->getData(true);

        expect($data['errors'][0]['extensions'])->toHaveKey('debug')
            ->and($data['errors'][0]['extensions']['debug'])->toHaveKey('exception')
            ->and($data['errors'][0]['extensions']['debug'])->toHaveKey('file')
            ->and($data['errors'][0]['extensions']['debug'])->toHaveKey('line');
    });

    it('includes additional metadata in extensions', function () {
        $formatter = resolve(GraphQLFormatter::class);
        $exception = ApiException::make('Error')
            ->meta(['customField' => 'customValue', 'count' => 42]);

        $response = $formatter->format($exception, 400);
        $data = $response->getData(true);

        expect($data['errors'][0]['extensions'])->toHaveKey('customField')
            ->and($data['errors'][0]['extensions']['customField'])->toBe('customValue')
            ->and($data['errors'][0]['extensions']['count'])->toBe(42);
    });
});
