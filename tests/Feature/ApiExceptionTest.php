<?php

declare(strict_types=1);

use WorkDoneRight\ApiGuardian\Exceptions\ApiException;

it('can create an API exception with fluent interface', function () {
    $exception = ApiException::make('Test error')
        ->code('TEST_ERROR')
        ->statusCode(400)
        ->meta(['key' => 'value'])
        ->suggestion('Try this instead')
        ->link('https://docs.example.com')
        ->recoverable();

    expect($exception->getMessage())->toBe('Test error')
        ->and($exception->getErrorCode())->toBe('TEST_ERROR')
        ->and($exception->getStatusCode())->toBe(400)
        ->and($exception->getMeta())->toBe(['key' => 'value'])
        ->and($exception->getSuggestion())->toBe('Try this instead')
        ->and($exception->getLink())->toBe('https://docs.example.com')
        ->and($exception->isRecoverable())->toBeTrue();
});

it('can create a not found exception', function () {
    $exception = ApiException::notFound('User not found');

    expect($exception->getStatusCode())->toBe(404)
        ->and($exception->getErrorCode())->toBe('RESOURCE_NOT_FOUND')
        ->and($exception->getMessage())->toBe('User not found');
});

it('can create an unauthorized exception', function () {
    $exception = ApiException::unauthorized();

    expect($exception->getStatusCode())->toBe(401)
        ->and($exception->getErrorCode())->toBe('UNAUTHORIZED');
});

it('can create a validation exception', function () {
    $exception = ApiException::validationFailed();

    expect($exception->getStatusCode())->toBe(422)
        ->and($exception->getErrorCode())->toBe('VALIDATION_FAILED');
});

it('can chain multiple meta values', function () {
    $exception = ApiException::make('Test')
        ->meta(['key1' => 'value1'])
        ->meta(['key2' => 'value2']);

    expect($exception->getMeta())->toBe([
        'key1' => 'value1',
        'key2' => 'value2',
    ]);
});
