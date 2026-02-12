<?php

declare(strict_types=1);

use Typdy\StarterKit\Parsers\Contracts\ResourceParser;
use Typdy\StarterKit\Parsers\Data\Resource;
use Typdy\StarterKit\Parsers\Exceptions\IncludedValidationException;
use Typdy\StarterKit\Parsers\IncludedParser;

it('parses included resources array', function () {
    $response = [
        (object) [
            'type' => 'author',
            'id' => '42',
            'attributes' => (object) [
                'name' => 'Jane',
            ],
        ],
    ];

    $resourceParserMock = mock(ResourceParser::class);
    $resourceParserMock
        ->shouldReceive('parse')
        ->with($response)
        ->andReturn([
            new Resource(
                type: 'author',
                id: '42',
                attributes: [
                    'name' => 'Jane',
                ],
            ),
        ])
        ->once();

    $included = new IncludedParser($resourceParserMock)->parse($response);

    expect($included)->toBeArray();
    expect($included[0]->type)->toBe('author');
});

it('throws if included is not an array', function () {
    $resourceParserMock = mock(ResourceParser::class);

    new IncludedParser($resourceParserMock)->parse('not-an-array');
})->throws(
    IncludedValidationException::class,
    "Expected JSON:API included to be of type 'array', got 'string' instead.",
);
