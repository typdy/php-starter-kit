<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response;
use Typdy\StarterKit\Parsers\Contracts\ErrorsParser;
use Typdy\StarterKit\Parsers\Contracts\IncludedParser;
use Typdy\StarterKit\Parsers\Contracts\MetaParser;
use Typdy\StarterKit\Parsers\Contracts\ResourceParser;
use Typdy\StarterKit\Parsers\Data\Document;
use Typdy\StarterKit\Parsers\Data\Relation;
use Typdy\StarterKit\Parsers\Data\Resource;
use Typdy\StarterKit\Parsers\DocumentParser;
use Typdy\StarterKit\Parsers\Exceptions\DocumentValidationException;

beforeEach(function () {
    $this->resourceParser = mock(ResourceParser::class);
    $this->includedParser = mock(IncludedParser::class);
    $this->errorsParser = mock(ErrorsParser::class);
    $this->metaParser = mock(MetaParser::class);
});

it('parses a valid JSON:API document', function () {
    $response = new Response(200, [], '{}');

    $body = (object) [
        'data' => (object) [
            'type' => 'article',
            'id' => '1',
            'attributes' => (object) ['title' => 'Test'],
        ],
        'included' => [
            (object) [
                'type' => 'author',
                'id' => '42',
                'attributes' => (object) ['name' => 'Jane'],
            ],
        ],
        'meta' => (object) ['foo' => 'bar'],
    ];

    $resource = new Resource('article', '1', ['title' => 'Test']);
    $included = [new Resource('author', '42', ['name' => 'Jane'])];
    $meta = ['foo' => 'bar'];

    $this->resourceParser
        ->shouldReceive('parse')
        ->with($body->data)
        ->once()
        ->andReturn($resource);

    $this->includedParser
        ->shouldReceive('parse')
        ->with($body->included)
        ->once()
        ->andReturn($included);

    $this->metaParser
        ->shouldReceive('parse')
        ->with($body->meta)
        ->once()
        ->andReturn($meta);

    $parser = new DocumentParser(
        $this->resourceParser,
        $this->includedParser,
        $this->errorsParser,
        $this->metaParser,
    );

    $document = $parser->parse($body, $response);

    expect($document)->toBeInstanceOf(Document::class);
    expect($document->data)->toBe($resource);
    expect($document->included)->toBe($included);
    expect($document->meta)->toBe($meta);
});

it('throws if body is not an object', function () {
    $response = new Response(200, [], '{}');

    $parser = new DocumentParser(
        $this->resourceParser,
        $this->includedParser,
        $this->errorsParser,
        $this->metaParser,
    );

    $parser->parse('not-an-object', $response);
})->throws(
    DocumentValidationException::class,
    "Expected JSON:API document to be of type 'object', got 'string' instead.",
);

it('throws if missing top-level members', function () {
    $response = new Response(200, [], '{}');

    $parser = new DocumentParser(
        $this->resourceParser,
        $this->includedParser,
        $this->errorsParser,
        $this->metaParser,
    );

    $parser->parse((object) [], $response);
})->throws(
    DocumentValidationException::class,
    'A valid JSON:API document must contain at least one of the following top-level members',
);

it('throws if both data and errors are present', function () {
    $response = new Response(200, [], '{}');

    $parser = new DocumentParser(
        $this->resourceParser,
        $this->includedParser,
        $this->errorsParser,
        $this->metaParser,
    );

    $parser->parse(
        (object) [
            'data' => (object) [],
            'errors' => [],
        ],
        $response,
    );
})->throws(
    DocumentValidationException::class,
    "A valid JSON:API document cannot contain both the 'data' and 'errors' top-level members.",
);

it('throws if included is present without data', function () {
    $response = new Response(200, [], '{}');

    $parser = new DocumentParser(
        $this->resourceParser,
        $this->includedParser,
        $this->errorsParser,
        $this->metaParser,
    );

    $parser->parse(
        (object) [
            'included' => [],
            'meta' => (object) [],
        ],
        $response,
    );
})->throws(
    DocumentValidationException::class,
    "A valid JSON:API document cannot contain the 'included' top-level member without 'data' member.",
);

it('throws if data is not object or array', function () {
    $response = new Response(200, [], '{}');

    $parser = new DocumentParser(
        $this->resourceParser,
        $this->includedParser,
        $this->errorsParser,
        $this->metaParser,
    );

    $parser->parse(
        (object) [
            'data' => 'not-object-or-array',
        ],
        $response,
    );
})->throws(
    DocumentValidationException::class,
    "Expected 'data' member to be of type 'null', 'object' or 'array', got 'string' instead.",
);

it('associates included resources to matching relationships', function () {
    $response = new Response(200, [], '{}');

    $body = (object) [
        'data' => (object) [
            'type' => 'article',
            'id' => '1',
        ],
        'included' => [
            (object) [
                'type' => 'author',
                'id' => '42',
            ],
        ],
    ];

    $authorIdentifier = new Resource('author', '42');

    $data = new Resource(
        type: 'article',
        id: '1',
        relationships: [
            'author' => new Relation(
                name: 'author',
                data: $authorIdentifier,
            ),
        ],
    );

    $included = [new Resource('author', '42', ['name' => 'Jane'])];

    $this->resourceParser
        ->shouldReceive('parse')
        ->with($body->data)
        ->once()
        ->andReturn($data);

    $this->includedParser
        ->shouldReceive('parse')
        ->with($body->included)
        ->once()
        ->andReturn($included);

    $parser = new DocumentParser(
        $this->resourceParser,
        $this->includedParser,
        $this->errorsParser,
        $this->metaParser,
    );

    $document = $parser->parse($body, $response);

    expect($document->data)->toBeInstanceOf(Resource::class);
    expect($document->data->relationships)->toHaveKey('author');
    expect($document->data->relationships['author']->included)->toHaveCount(1);
    expect($document->data->relationships['author']->included[0]->type)->toBe('author');
    expect($document->data->relationships['author']->included[0]->id)->toBe('42');
});
