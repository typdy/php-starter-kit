<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Typdy\StarterKit\Parsers\Contracts\DocumentParser;
use Typdy\StarterKit\Parsers\Data\Document;
use Typdy\StarterKit\Parsers\Data\Error;
use Typdy\StarterKit\Parsers\Data\Resource;
use Typdy\StarterKit\Parsers\Exceptions\DecodingException;
use Typdy\StarterKit\Parsers\ResponseParser;

beforeEach(function () {
    $this->documentParserMock = mock(DocumentParser::class);
});

it('parses a valid single resource document', function () {
    $parser = new ResponseParser($this->documentParserMock, 'article');

    $body = (object) [
        'data' => (object) [
            'type' => 'article',
            'id' => '1',
            'attributes' => (object) ['title' => 'JSON:API paints my bikeshed!'],
        ],
    ];

    $response = new Response(200, [], json_encode($body));

    $this->documentParserMock
        ->shouldReceive('parse')
        ->withArgs(function ($actualBody, $actualResponse) use ($body, $response) {
            expect($actualBody)->toEqual($body);
            expect($actualResponse)->toBe($response);

            return true;
        })
        ->once()
        ->andReturn(new Document($response, new Resource(
            type: 'article',
            id: '1',
            attributes: ['title' => 'JSON:API paints my bikeshed!'],
        )));

    $document = $parser->parse($response);

    expect($document)->toBeInstanceOf(Document::class);

    expect($document->data)->toBeInstanceOf(Resource::class);
    expect($document->data->type)->toBe('article');
});

it('parses a valid resource collection document', function () {
    $parser = new ResponseParser($this->documentParserMock, 'article');

    $body = (object) [
        'data' => [
            (object) [
                'type' => 'article',
                'id' => '1',
                'attributes' => (object) ['title' => 'First'],
            ],
            (object) [
                'type' => 'article',
                'id' => '2',
                'attributes' => (object) ['title' => 'Second'],
            ],
        ],
    ];

    $response = new Response(200, [], json_encode($body));

    $this->documentParserMock
        ->shouldReceive('parse')
        ->withArgs(function ($actualBody, $actualResponse) use ($body, $response) {
            expect($actualBody)->toEqual($body);
            expect($actualResponse)->toBe($response);

            return true;
        })
        ->once()
        ->andReturn(new Document($response, [
            new Resource(
                type: 'article',
                id: '1',
                attributes: ['title' => 'First'],
            ),
            new Resource(
                type: 'article',
                id: '2',
                attributes: ['title' => 'Second'],
            ),
        ]));

    $document = $parser->parse($response);

    expect($document)->toBeInstanceOf(Document::class);

    expect($document->data)->toBeArray();
    expect($document->data[0]->type)->toBe('article');
});

it('parses a valid meta-only document', function () {
    $body = (object) [
        'meta' => (object) [
            'total' => 100,
        ],
    ];

    $parser = new ResponseParser($this->documentParserMock, mixedType: true);

    $response = new Response(200, [], json_encode($body));

    $this->documentParserMock
        ->shouldReceive('parse')
        ->withArgs(function ($actualBody, $actualResponse) use ($body, $response) {
            expect($actualBody)->toEqual($body);
            expect($actualResponse)->toBe($response);

            return true;
        })
        ->once()
        ->andReturn(new Document($response, meta: ['total' => 100]));

    $document = $parser->parse($response);

    expect($document)->toBeInstanceOf(Document::class);

    expect($document->meta)->toBeArray();
    expect($document->meta['total'])->toBe(100);
});

it('parses a valid erroneous document', function () {
    $body = (object) [
        'errors' => [
            (object) [
                'status' => '400',
                'title' => 'Bad Request',
            ],
        ],
    ];

    $parser = new ResponseParser($this->documentParserMock, 'article');

    $response = new Response(400, [], json_encode($body));

    $this->documentParserMock
        ->shouldReceive('parse')
        ->withArgs(function ($actualBody, $actualResponse) use ($body, $response) {
            expect($actualBody)->toEqual($body);
            expect($actualResponse)->toBe($response);

            return true;
        })
        ->once()
        ->andReturn(new Document($response, errors: [
            new Error(
                status: '400',
                title: 'Bad Request',
            ),
        ]));

    $document = $parser->parse($response);

    expect($document)->toBeInstanceOf(Document::class);

    expect($document->errors)->toBeArray();
    expect($document->errors[0]->status)->toBe('400');
});

it('throws if neither expectedType nor mixedType is set', function () {
    $parser = new ResponseParser($this->documentParserMock);
})->throws(InvalidArgumentException::class);

it('returns an empty document if response body is empty', function () {
    $parser = new ResponseParser($this->documentParserMock, 'article');
    $response = new Response(204, [], '');

    $this->documentParserMock->shouldNotReceive('parse');

    $document = $parser->parse($response);

    expect($document)->toBeInstanceOf(Document::class);
    expect($document->response)->toBe($response);
    expect($document->data)->toBeNull();
    expect($document->errors)->toBeEmpty();
});

it('throws if the response type does not match expected type', function () {
    $body = (object) [
        'data' => (object) [
            'type' => 'comment',
            'id' => '1',
        ],
    ];

    $parser = new ResponseParser($this->documentParserMock, 'article');

    $response = new Response(200, [], json_encode($body));

    $this->documentParserMock
        ->shouldReceive('parse')
        ->withArgs(function ($actualBody, $actualResponse) use ($body, $response) {
            expect($actualBody)->toEqual($body);
            expect($actualResponse)->toBe($response);

            return true;
        })
        ->once()
        ->andReturn(new Document($response, new Resource(
            type: 'comment',
            id: '1',
        )));

    $parser->parse($response);
})->throws(InvalidArgumentException::class);

it('does not throw if mixedType is true', function () {
    $body = (object) [
        'data' => (object) [
            'type' => 'comment',
            'id' => '1',
        ],
    ];

    $parser = new ResponseParser($this->documentParserMock, mixedType: true);

    $response = new Response(200, [], json_encode($body));

    $this->documentParserMock
        ->shouldReceive('parse')
        ->withArgs(function ($actualBody, $actualResponse) use ($body, $response) {
            expect($actualBody)->toEqual($body);
            expect($actualResponse)->toBe($response);

            return true;
        })
        ->once()
        ->andReturn(new Document($response, new Resource(
            type: 'comment',
            id: '1',
        )));

    $document = $parser->parse($response);

    expect($document)->toBeInstanceOf(Document::class);

    expect($document->data)->toBeInstanceOf(Resource::class);
    expect($document->data->type)->toBe('comment');
});

it('throws on invalid JSON', function () {
    $parser = new ResponseParser($this->documentParserMock, 'article');

    $response = new Response(200, [], '{invalid json');

    $parser->parse($response);
})->throws(DecodingException::class);

it('parses a response with unknown body size when content exists', function () {
    $parser = new ResponseParser($this->documentParserMock, 'article');

    $body = (object) [
        'data' => (object) [
            'type' => 'article',
            'id' => '1',
        ],
    ];

    $json = json_encode($body);

    $streamMock = mock(StreamInterface::class);
    $streamMock
        ->shouldReceive('getSize')
        ->andReturn(null);

    $streamMock
        ->shouldReceive('isSeekable')
        ->andReturn(true);

    $streamMock
        ->shouldReceive('tell')
        ->andReturn(0);

    $streamMock
        ->shouldReceive('read')
        ->with(1)
        ->andReturn('{');

    $streamMock
        ->shouldReceive('seek')
        ->with(0)
        ->andReturnNull();

    $streamMock
        ->shouldReceive('__toString')
        ->andReturn($json);

    $responseMock = mock(ResponseInterface::class);
    $responseMock
        ->shouldReceive('getBody')
        ->andReturn($streamMock);

    $this->documentParserMock
        ->shouldReceive('parse')
        ->withArgs(function ($actualBody, $actualResponse) use ($body, $responseMock) {
            expect($actualBody)->toEqual($body);
            expect($actualResponse)->toBe($responseMock);

            return true;
        })
        ->once()
        ->andReturn(new Document($responseMock, new Resource(
            type: 'article',
            id: '1',
        )));

    $document = $parser->parse($responseMock);

    expect($document)->toBeInstanceOf(Document::class);
    expect($document->data)->toBeInstanceOf(Resource::class);
    expect($document->data->type)->toBe('article');
});

it('throws an exception or non-object documents', function () {
    $response = new Response(200, [], '[]');

    $this->documentParserMock->shouldNotReceive('parse');

    $parser = new ResponseParser($this->documentParserMock, mixedType: true);

    $document = $parser->parse($response);
})->throws(DecodingException::class, 'Document body must be a JSON object.');
