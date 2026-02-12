<?php

declare(strict_types=1);

use Typdy\StarterKit\Parsers\Contracts\MetaParser;
use Typdy\StarterKit\Parsers\Data\Error;
use Typdy\StarterKit\Parsers\ErrorsParser;
use Typdy\StarterKit\Parsers\Exceptions\ErrorsValidationException;

it('parses errors array', function () {
    $response = [
        (object) [
            'title' => 'Invalid Attribute',
            'detail' => 'Title must be at least 3 characters.',
        ],
    ];

    $metaParser = mock(MetaParser::class);

    $errors = new ErrorsParser($metaParser)->parse($response);

    expect($errors)->toBeArray();
    expect($errors[0])->toBeInstanceOf(Error::class);
    expect($errors[0]->title)->toBe($response[0]->title);
    expect($errors[0]->detail)->toBe($response[0]->detail);
});

it('parses errors with meta', function () {
    $response = [
        (object) [
            'title' => 'Invalid Attribute',
            'detail' => 'Title must be at least 3 characters.',
            'meta' => (object) [
                'timestamp' => '2024-06-01T12:00:00Z',
            ],
        ],
    ];

    $metaParserMock = mock(MetaParser::class);
    $metaParserMock
        ->shouldReceive('parse')
        ->with($response[0]->meta)
        ->once()
        ->andReturn(['timestamp' => $response[0]->meta->timestamp]);

    $errors = new ErrorsParser($metaParserMock)->parse($response);

    expect($errors[0]->meta)->toBeArray();
    expect($errors[0]->meta)->toHaveKey('timestamp');
    expect($errors[0]->meta['timestamp'])->toBe($response[0]->meta->timestamp);
});

it('throws if errors is not an array', function () {
    $metaParser = mock(MetaParser::class);

    new ErrorsParser($metaParser)->parse('not-an-array');
})->throws(ErrorsValidationException::class, "Expected JSON:API errors to be of type 'array', got 'string' instead.");

it('throws if errors array is empty', function () {
    $metaParser = mock(MetaParser::class);

    new ErrorsParser($metaParser)->parse([]);
})->throws(ErrorsValidationException::class, 'The JSON:API errors array is empty.');

it('throws if members are not objects', function () {
    $metaParser = mock(MetaParser::class);

    new ErrorsParser($metaParser)->parse(['not-an-object']);
})->throws(
    ErrorsValidationException::class,
    "Expected JSON:API error to be of type 'object', got 'string' instead, at index '0'.",
);

it('throws if member type is invalid', function () {
    $metaParser = mock(MetaParser::class);

    new ErrorsParser($metaParser)->parse([(object) ['status' => 503]]);
})->throws(
    ErrorsValidationException::class,
    "Expected JSON:API error member 'status' to be of type 'string', got 'integer' instead, at index '0'.",
);

it('throws if required members are missing', function () {
    $metaParser = mock(MetaParser::class);

    new ErrorsParser($metaParser)->parse([(object) ['foo' => 'bar']]);
})->throws(
    ErrorsValidationException::class,
    "A valid JSON:API error object must contain at least one of the following members: 'id', 'links', 'status', 'code', 'title', 'detail', 'source' or 'meta'.",
);
