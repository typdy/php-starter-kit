<?php

declare(strict_types=1);

use Typdy\StarterKit\Parsers\Contracts\MetaParser;
use Typdy\StarterKit\Parsers\Data\Relation;
use Typdy\StarterKit\Parsers\Data\Resource;
use Typdy\StarterKit\Parsers\Exceptions\ResourceValidationException;
use Typdy\StarterKit\Parsers\ResourceParser;

beforeEach(function () {
    $this->metaParser = mock(MetaParser::class);
});

it('parses a single resource', function () {
    $resource = new ResourceParser($this->metaParser)->parse((object) [
        'type' => 'article',
        'id' => '1',
        'attributes' => (object) [
            'title' => 'Something interesting',
        ],
    ]);

    expect($resource->attributes)->toHaveKey('title');
    expect($resource->attributes['title'])->toBe('Something interesting');
});

it('parses a resource array', function () {
    $response = [
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
    ];

    $resources = new ResourceParser($this->metaParser)->parse($response);

    expect($resources)->toBeArray();
    expect($resources[0]->attributes)->toHaveKey('title');
    expect($resources[1]->attributes)->toHaveKey('title');
    expect($resources[0]->attributes['title'])->toBe('First');
    expect($resources[1]->attributes['title'])->toBe('Second');
});

it('parses resources with meta', function () {
    $response = [
        (object) [
            'type' => 'article',
            'id' => '1',
            'attributes' => (object) ['title' => 'First'],
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

    $resources = new ResourceParser($metaParserMock)->parse($response);

    expect($resources[0]->meta)->toBeArray();
    expect($resources[0]->meta)->toHaveKey('timestamp');
    expect($resources[0]->meta['timestamp'])->toBe($response[0]->meta->timestamp);
});

it('parses resources with relationships', function () {
    $response = (object) [
        'type' => 'article',
        'id' => '1',
        'relationships' => (object) [
            'tags' => (object) [
                'data' => [
                    (object) [
                        'type' => 'tag',
                        'id' => '3',
                    ],
                    (object) [
                        'type' => 'tag',
                        'id' => '4',
                    ],
                ],
            ],
            'related-categories' => (object) [
                'data' => (object) [
                    'type' => 'category',
                    'id' => '5',
                    'meta' => (object) [
                        'some-key' => 'someValue',
                    ],
                ],
                'meta' => (object) [
                    'relation-meta-key' => 'relationMetaValue',
                ],
            ],
        ],
    ];

    $metaParserMock = mock(MetaParser::class);

    $metaParserMock
        ->shouldReceive('parse')
        ->with($response->relationships->{'related-categories'}->meta)
        ->once()
        ->andReturn(['relation-meta-key' => 'relationMetaValue']);

    $metaParserMock
        ->shouldReceive('parse')
        ->with($response->relationships->{'related-categories'}->data->meta)
        ->once()
        ->andReturn(['some-key' => 'someValue']);

    $resource = new ResourceParser($metaParserMock)->parse($response);

    expect($resource->relationships)->toBeArray();
    expect($resource->relationships)->toHaveKey('tags');
    expect($resource->relationships)->toHaveKey('related-categories');

    expect($resource->relationships['tags'])->toBeInstanceOf(Relation::class);
    expect($resource->relationships['tags']->name)->toBe('tags');

    expect($resource->relationships['tags']->data)->toBeArray();
    expect($resource->relationships['tags']->data[0])->toBeInstanceOf(Resource::class);
    expect($resource->relationships['tags']->data[0]->type)->toBe('tag');
    expect($resource->relationships['tags']->data[0]->id)->toBe('3');

    expect($resource->relationships['tags']->data[1])->toBeInstanceOf(Resource::class);
    expect($resource->relationships['tags']->data[1]->type)->toBe('tag');
    expect($resource->relationships['tags']->data[1]->id)->toBe('4');

    expect($resource->relationships['related-categories'])->toBeInstanceOf(Relation::class);
    expect($resource->relationships['related-categories']->name)->toBe('related-categories');

    expect($resource->relationships['related-categories']->meta)->toBeArray();
    expect($resource->relationships['related-categories']->meta)->toHaveKey('relation-meta-key');

    expect($resource->relationships['related-categories']->data)->toBeInstanceOf(Resource::class);
    expect($resource->relationships['related-categories']->data->type)->toBe('category');
    expect($resource->relationships['related-categories']->data->id)->toBe('5');
    expect($resource->relationships['related-categories']->data->meta)->toBeArray();
    expect($resource->relationships['related-categories']->data->meta)->toHaveKey('some-key');
});

it('elevates legacy construct resource types', function () {
    $response = (object) [
        'type' => 'constructs',
        'id' => '1',
        'meta' => (object) [
            'type' => 'page',
        ],
    ];

    $metaParserMock = mock(MetaParser::class);

    $metaParserMock
        ->shouldReceive('parse')
        ->with($response->meta)
        ->once()
        ->andReturn(['type' => 'page']);

    $resource = new ResourceParser($metaParserMock)->parse($response);

    expect($resource->type)->toBe('page');
});

it('elevates legacy global resource types', function () {
    $response = (object) [
        'type' => 'globals',
        'id' => '1',
        'meta' => (object) [
            'type' => 'header',
        ],
    ];

    $metaParserMock = mock(MetaParser::class);

    $metaParserMock
        ->shouldReceive('parse')
        ->with($response->meta)
        ->once()
        ->andReturn(['type' => 'header']);

    $resource = new ResourceParser($metaParserMock)->parse($response);

    expect($resource->type)->toBe('header');
});

it('skips elevation if meta type is missing', function () {
    $response = (object) [
        'type' => 'constructs',
        'id' => '1',
        'meta' => (object) [
            'someKey' => 'someValue',
        ],
    ];

    $metaParserMock = mock(MetaParser::class);

    $metaParserMock
        ->shouldReceive('parse')
        ->with($response->meta)
        ->once()
        ->andReturn(['someKey' => 'someValue']);

    $resource = new ResourceParser($metaParserMock)->parse($response);

    expect($resource->type)->toBe('constructs');
});

it('throws when a related resource is not an object', function () {
    $resource = new ResourceParser($this->metaParser)->parse((object) [
        'type' => 'article',
        'id' => '1',
        'relationships' => (object) [
            'author' => (object) [
                'data' => 'not-an-object',
            ],
        ],
    ]);
})->throws(ResourceValidationException::class);

it('throws when a resource is missing it\'s id', function () {
    new ResourceParser($this->metaParser)->parse((object) [
        'type' => 'article',
    ]);
})->throws(ResourceValidationException::class);

it('throws when a resource is missing it\'s type', function () {
    new ResourceParser($this->metaParser)->parse((object) [
        'id' => '1',
    ]);
})->throws(ResourceValidationException::class);

it('throws when a related resource is missing it\'s id', function () {
    new ResourceParser($this->metaParser)->parse((object) [
        'type' => 'article',
        'id' => '1',
        'relationships' => (object) [
            'author' => (object) [
                'data' => (object) [
                    'type' => 'author',
                ],
            ],
        ],
    ]);
})->throws(ResourceValidationException::class);

it('throws when a related resource is missing it\'s type', function () {
    new ResourceParser($this->metaParser)->parse((object) [
        'type' => 'article',
        'id' => '1',
        'relationships' => (object) [
            'author' => (object) [
                'data' => (object) [
                    'id' => '42',
                ],
            ],
        ],
    ]);
})->throws(ResourceValidationException::class);

it('throws when resource type is not a string', function () {
    new ResourceParser($this->metaParser)->parse((object) [
        'type' => 123,
        'id' => '1',
    ]);
})->throws(ResourceValidationException::class);

it('throws when resource id is not a string', function () {
    new ResourceParser($this->metaParser)->parse((object) [
        'type' => 'article',
        'id' => 123,
    ]);
})->throws(ResourceValidationException::class);

it('throws when related resource type is not a string', function () {
    new ResourceParser($this->metaParser)->parse((object) [
        'type' => 'article',
        'id' => '1',
        'relationships' => (object) [
            'author' => (object) [
                'data' => (object) [
                    'type' => 123,
                    'id' => '42',
                ],
            ],
        ],
    ]);
})->throws(ResourceValidationException::class);

it('throws when related resource id is not a string', function () {
    new ResourceParser($this->metaParser)->parse((object) [
        'type' => 'article',
        'id' => '1',
        'relationships' => (object) [
            'author' => (object) [
                'data' => (object) [
                    'type' => 'author',
                    'id' => 123,
                ],
            ],
        ],
    ]);
})->throws(ResourceValidationException::class);

it('throws when attributes is not an object', function () {
    new ResourceParser($this->metaParser)->parse((object) [
        'type' => 'article',
        'id' => '1',
        'attributes' => 'not-an-object',
    ]);
})->throws(ResourceValidationException::class);

it('throws on prohibited members in attributes', function () {
    new ResourceParser($this->metaParser)->parse((object) [
        'type' => 'article',
        'id' => '1',
        'attributes' => [
            'type' => 'should-not-be-here',
        ],
    ]);
})->throws(ResourceValidationException::class);

it('throws when relationships is not an object', function () {
    new ResourceParser($this->metaParser)->parse((object) [
        'type' => 'article',
        'id' => '1',
        'relationships' => 'not-an-object',
    ]);
})->throws(ResourceValidationException::class);

it('throws on prohibited members in relationships', function () {
    new ResourceParser($this->metaParser)->parse((object) [
        'type' => 'article',
        'id' => '1',
        'relationships' => [
            'type' => 'should-not-be-here',
        ],
    ]);
})->throws(ResourceValidationException::class);

it('throws on conflicting members in attributes and relationships', function () {
    new ResourceParser($this->metaParser)->parse((object) [
        'type' => 'article',
        'id' => '1',
        'attributes' => [
            'author' => 'Jane',
        ],
        'relationships' => [
            'author' => [
                'data' => [
                    'type' => 'author',
                    'id' => '42',
                ],
            ],
        ],
    ]);
})->throws(ResourceValidationException::class);

it('throws when a relationship is not an object', function () {
    new ResourceParser($this->metaParser)->parse((object) [
        'type' => 'article',
        'id' => '1',
        'relationships' => [
            'author' => 'not-an-object',
        ],
    ]);
})->throws(ResourceValidationException::class);

it('throws when a relationship data is missing', function () {
    new ResourceParser($this->metaParser)->parse((object) [
        'type' => 'article',
        'id' => '1',
        'relationships' => [
            'author' => [],
        ],
    ]);
})->throws(ResourceValidationException::class);

it('parses to-one relationships with null data', function () {
    $resource = new ResourceParser($this->metaParser)->parse((object) [
        'type' => 'article',
        'id' => '1',
        'relationships' => (object) [
            'author' => (object) [
                'data' => null,
            ],
        ],
    ]);

    expect($resource->relationships)->toHaveKey('author');
    expect($resource->relationships['author'])->toBeInstanceOf(Relation::class);
    expect($resource->relationships['author']->data)->toBeNull();
});
