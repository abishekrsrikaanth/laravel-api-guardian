<?php

use WorkDoneRight\ApiGuardian\Exceptions\ApiException;
use WorkDoneRight\ApiGuardian\Formatters\JSendFormatter;
use WorkDoneRight\ApiGuardian\Formatters\JsonApiFormatter;
use WorkDoneRight\ApiGuardian\Formatters\Rfc7807Formatter;

beforeEach(function () {
    config(['app.debug' => false]);
});

it('formats exception using JSend formatter', function () {
    $formatter = new JSendFormatter();
    $exception = ApiException::notFound('User not found');

    $response = $formatter->format($exception);

    expect($response->getStatusCode())->toBe(404);

    $data = $response->getData(true);

    expect($data['status'])->toBe('fail')
        ->and($data['message'])->toBe('User not found')
        ->and($data['code'])->toBe('RESOURCE_NOT_FOUND');
});

it('formats exception using RFC 7807 formatter', function () {
    $formatter = new Rfc7807Formatter();
    $exception = ApiException::notFound('User not found');

    $response = $formatter->format($exception);

    expect($response->getStatusCode())->toBe(404);

    $data = $response->getData(true);

    expect($data['status'])->toBe(404)
        ->and($data['title'])->toBe('Not Found')
        ->and($data['detail'])->toBe('User not found')
        ->and($data)->toHaveKey('type');
});

it('formats exception using JSON:API formatter', function () {
    $formatter = new JsonApiFormatter();
    $exception = ApiException::notFound('User not found');

    $response = $formatter->format($exception);

    expect($response->getStatusCode())->toBe(404);

    $data = $response->getData(true);

    expect($data)->toHaveKey('errors')
        ->and($data['errors'])->toBeArray()
        ->and($data['errors'][0]['status'])->toBe('404')
        ->and($data['errors'][0]['code'])->toBe('RESOURCE_NOT_FOUND')
        ->and($data['errors'][0]['detail'])->toBe('User not found');
});

it('includes metadata in JSend format', function () {
    $formatter = new JSendFormatter();
    $exception = ApiException::notFound('User not found')
        ->meta(['user_id' => 123]);

    $response = $formatter->format($exception);
    $data = $response->getData(true);

    expect($data['data'])->toHaveKey('user_id')
        ->and($data['data']['user_id'])->toBe(123);
});
