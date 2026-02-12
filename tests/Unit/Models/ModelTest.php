<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Typdy\StarterKit\Attributes\Blueprint;
use Typdy\StarterKit\Containers\Contracts\Container as TypdyContainer;
use Typdy\StarterKit\Containers\LaravelAdaptor;
use Typdy\StarterKit\Models\Attributes\Alias;
use Typdy\StarterKit\Models\Attributes\Relationship;
use Typdy\StarterKit\Models\Model;
use Typdy\StarterKit\Parsers\Data\Relation;
use Typdy\StarterKit\Parsers\Data\Resource;
use Typdy\StarterKit\Resolvers\Contracts\ResolvesModels;
use Typdy\StarterKit\Typdy;
use Typdy\StarterKit\TypdyConfig;

beforeAll(function () {
    Typdy::$config = new TypdyConfig(
        team: 'the-a-team',
        project: 'project-x',
        legacyTypes: false,
    );
});

beforeEach(function () {
    Typdy::$container = new LaravelAdaptor(Container::getInstance());
});

afterEach(function () {
    Typdy::$container = null;
});

it('serializes to array and json', function () {
    $model = new
        #[Blueprint('foo')]
        class extends Model {
            public function __construct()
            {
                $this->id = 123;
                $this->identifier = 'abc';
            }
        };

    $array = $model->toArray();

    expect($array)->toBeArray();

    $json = $model->toJson();

    expect($json)->toBeJson();
    expect(json_decode($json, associative: true, flags: JSON_THROW_ON_ERROR))->toBeArray();
});

it('returns true for isNew if id is null', function () {
    $model = new class extends Model {};

    expect($model->isNew())->toBeTrue();
});

it('returns false for isNew if id is set', function () {
    $model = new class extends Model {
        public function __construct()
        {
            $this->id = 123;
        }
    };

    expect($model->isNew())->toBeFalse();
});

it('returns global status from meta', function () {
    $model = new class extends Model {};

    $model->meta = ['global' => true];
    expect($model->isGlobal())->toBeTrue();

    $model->meta = [];
    expect($model->isGlobal())->toBeFalse();
});

it('hydrates model from a Resource', function () {
    $resource = new Resource(
        type: 'post',
        id: '42',
        attributes: ['title' => 'Another masterpiece'],
        meta: ['global' => true],
        relationships: [],
    );

    $model = new
        #[Blueprint('post')]
        class extends Model {
            public ?string $title = null;
        };

    $model->hydrateFromResource($resource);

    expect($model->id)
        ->toBe(42)
        ->and($model->resource)
        ->toBe($resource)
        ->and($model->title)
        ->toBe('Another masterpiece')
        ->and($model->meta)
        ->toBe(['global' => true]);
});

it('throws when hydrating with mismatched Resource type', function () {
    $resource = new Resource(
        type: 'page',
        id: '1',
        attributes: [],
        meta: [],
        relationships: [],
    );

    $model = new
        #[Blueprint('post')]
        class extends Model {};

    $model->hydrateFromResource($resource);
})->throws(InvalidArgumentException::class, "Cannot hydrate a 'post' with a resource of type 'page'.");

it('hydrates single relationship', function () {
    $relation = new Relation(
        name: 'related-model',
        data: new Resource(
            type: 'related',
            id: '99',
        ),
        meta: ['foo' => 'bar'],
    );

    $relation->associateIncluded([
        new Resource(
            type: 'related',
            id: '99',
            attributes: ['name' => 'rel'],
            meta: [],
            relationships: [],
        ),
    ]);

    $resource = new Resource(
        type: 'foo',
        id: '1',
        attributes: [],
        meta: [],
        relationships: ['related-model' => $relation],
    );

    Typdy::$container = mock(TypdyContainer::class);

    Typdy::$container
        ->shouldReceive('make')
        ->once()
        ->with(ResolvesModels::class, [])
        ->andReturn(new class {
            public function resolveOne(
                string $team,
                string $project,
                string $blueprint,
            ) {
                return new
                    #[Blueprint('related')]
                    class extends Model {
                        public ?string $name = null;
                    };
            }
        });

    $model = new
        #[Blueprint('foo')]
        class extends Model {
            public ?Model $relatedModel = null;
        };

    $model->hydrateFromResource($resource);

    expect($model->relatedModel)
        ->toBeInstanceOf(Model::class)
        ->and($model->relatedModel->id)
        ->toBe(99)
        ->and($model->relatedModel->name)
        ->toBe('rel');
});

it('hydrates multiple relationships', function () {
    $relatedResource1 = new Resource(
        type: 'related',
        id: '101',
        attributes: ['name' => 'rel1'],
        meta: [],
        relationships: [],
    );

    $relatedResource2 = new Resource(
        type: 'related',
        id: '102',
        attributes: ['name' => 'rel2'],
        meta: [],
        relationships: [],
    );

    $relation = new Relation(
        name: 'related-models',
        // the parser would normally set these to resource identifiers, but the
        // full resource is fine for testing
        data: [$relatedResource1, $relatedResource2],
        meta: ['foo' => 'bar'],
    );

    $relation->associateIncluded([$relatedResource1, $relatedResource2]);

    $resource = new Resource(
        type: 'foo',
        id: '1',
        attributes: [],
        meta: [],
        relationships: ['related-models' => $relation],
    );

    Typdy::$container = mock(TypdyContainer::class);

    Typdy::$container
        ->shouldReceive('make')
        ->twice()
        ->with(ResolvesModels::class, [])
        ->andReturn(new class {
            public function resolveOne(
                string $team,
                string $project,
                string $blueprint,
            ) {
                return new
                    #[Blueprint('related')]
                    class extends Model {
                        public ?string $name = null;
                    };
            }
        });

    $model = new
        #[Blueprint('foo')]
        class extends Model {
            public array $relatedModels = [];
        };

    $model->hydrateFromResource($resource);

    expect($model->relatedModels)
        ->toBeArray()
        ->and($model->relatedModels[0])
        ->toBeInstanceOf(Model::class)
        ->and($model->relatedModels[0]->name)
        ->toBe('rel1')
        ->and($model->relatedModels[1]->name)
        ->toBe('rel2');
});

it('hydrates single relationship from linkage data when included is missing', function () {
    $relation = new Relation(
        name: 'related-model',
        data: new Resource(
            type: 'related',
            id: '99',
        ),
    );

    $resource = new Resource(
        type: 'foo',
        id: '1',
        attributes: [],
        relationships: ['related-model' => $relation],
    );

    Typdy::$container = mock(TypdyContainer::class);

    Typdy::$container
        ->shouldReceive('make')
        ->once()
        ->with(ResolvesModels::class, [])
        ->andReturn(new class {
            public function resolveOne(string $team, string $project, string $blueprint)
            {
                return new
                    #[Blueprint('related')]
                    class extends Model {
                        public ?string $name = null;
                    };
            }
        });

    $model = new
        #[Blueprint('foo')]
        class extends Model {
            public ?Model $relatedModel = null;
        };

    $model->hydrateFromResource($resource);

    expect($model->relatedModel)
        ->toBeInstanceOf(Model::class)
        ->and($model->relatedModel?->id)
        ->toBe(99)
        ->and($model->relatedModel?->name)
        ->toBeNull();
});

it('hydrates many relationships by matching included where available and falling back to linkage data', function () {
    $linkageOnlyResource = new Resource(
        type: 'related',
        id: '101',
    );

    $includedMatchResource = new Resource(
        type: 'related',
        id: '102',
    );

    $includedPayload = new Resource(
        type: 'related',
        id: '102',
        attributes: ['name' => 'from-included'],
    );

    $relation = new Relation(
        name: 'related-models',
        data: [$linkageOnlyResource, $includedMatchResource],
    );

    $relation->associateIncluded([$includedPayload]);

    $resource = new Resource(
        type: 'foo',
        id: '1',
        attributes: [],
        relationships: ['related-models' => $relation],
    );

    Typdy::$container = mock(TypdyContainer::class);

    Typdy::$container
        ->shouldReceive('make')
        ->twice()
        ->with(ResolvesModels::class, [])
        ->andReturn(new class {
            public function resolveOne(string $team, string $project, string $blueprint)
            {
                return new
                    #[Blueprint('related')]
                    class extends Model {
                        public ?string $name = null;
                    };
            }
        });

    $model = new
        #[Blueprint('foo')]
        class extends Model {
            public array $relatedModels = [];
        };

    $model->hydrateFromResource($resource);

    expect($model->relatedModels)
        ->toHaveCount(2)
        ->and($model->relatedModels[0]?->id)
        ->toBe(101)
        ->and($model->relatedModels[0]?->name)
        ->toBeNull()
        ->and($model->relatedModels[1]?->id)
        ->toBe(102)
        ->and($model->relatedModels[1]?->name)
        ->toBe('from-included');
});

it('retrieves relationship meta', function () {
    $resource = new Resource(
        type: 'foo',
        id: '1',
        attributes: [],
        meta: [],
        relationships: [
            'rel' => new Relation(
                name: 'rel',
                data: null,
                meta: ['foo' => 'bar'],
            ),
        ],
    );

    $model = new
        #[Blueprint('foo')]
        class extends Model {};

    $model->hydrateFromResource($resource);

    expect($model->getRelationshipMeta('rel'))->toBe(['foo' => 'bar']);
});

it('serializes construct and relationships correctly', function () {
    $relation = new Relation(
        name: 'related-model',
        data: new Resource(
            type: 'related',
            id: '99',
        ),
        meta: ['foo' => 'bar'],
    );

    $relation->associateIncluded([
        new Resource(
            type: 'related',
            id: '99',
            attributes: ['name' => 'rel'],
            meta: [],
            relationships: [],
        ),
    ]);

    $resource = new Resource(
        type: 'foo',
        id: '1',
        attributes: [
            'identifier' => 'foo-test',
            'name' => 'Foo Test',
        ],
        meta: ['bar' => 'baz'],
        relationships: ['related-model' => $relation],
    );

    Typdy::$container = mock(TypdyContainer::class);

    Typdy::$container
        ->shouldReceive('make')
        ->once()
        ->with(ResolvesModels::class, [])
        ->andReturn(new class {
            public function resolveOne(
                string $team,
                string $project,
                string $blueprint,
            ) {
                return new
                    #[Blueprint('related')]
                    class extends Model {
                        public ?string $name = null;
                    };
            }
        });

    $model = new
        #[Blueprint('foo')]
        class extends Model {
            public ?string $name = null;

            public ?Model $relatedModel = null;
        };

    $model->hydrateFromResource($resource);

    $expected = json_encode([
        'data' => [
            'type' => 'foo',
            'id' => '1',
            'attributes' => [
                'name' => 'Foo Test',
                'identifier' => 'foo-test',
                'created' => null,
                'updated' => null,
            ],
            'relationships' => [
                'related-model' => [
                    'data' => [
                        'id' => '99',
                        'type' => 'related',
                    ],
                    'meta' => ['foo' => 'bar'],
                ],
            ],
            'meta' => ['bar' => 'baz'],
        ],
    ], JSON_THROW_ON_ERROR);

    expect($model->getSyncBody())->toBe($expected);
});

it('hydrates aliased attributes into model properties', function () {
    $resource = new Resource(
        type: 'post',
        id: '42',
        attributes: ['hero-title' => 'Aliased title'],
    );

    $model = new
        #[Blueprint('post')]
        class extends Model {
            #[Alias('hero-title')]
            public ?string $title = null;
        };

    $model->hydrateFromResource($resource);

    expect($model->title)->toBe('Aliased title');
});

it('serializes aliased properties using alias keys', function () {
    $model = new
        #[Blueprint('post')]
        class extends Model {
            #[Alias('hero-title')]
            public ?string $title = null;
        };

    $model->hydrateFromResource(new Resource(type: 'post', id: '1'));
    $model->title = 'Aliased title';

    $body = json_decode((string) $model->getSyncBody(), associative: true, flags: JSON_THROW_ON_ERROR);

    expect($body['data']['attributes'])->toHaveKey('hero-title');
    expect($body['data']['attributes'])->not->toHaveKey('title');
    expect($body['data']['attributes']['hero-title'])->toBe('Aliased title');
});

it('hydrates and serializes camel-case aliased attributes', function () {
    $resource = new Resource(
        type: 'post',
        id: '42',
        attributes: ['heroTitle' => 'Aliased title'],
    );

    $model = new
        #[Blueprint('post')]
        class extends Model {
            #[Alias('heroTitle')]
            public ?string $title = null;
        };

    $model->hydrateFromResource($resource);

    expect($model->title)->toBe('Aliased title');

    $body = json_decode((string) $model->getSyncBody(), associative: true, flags: JSON_THROW_ON_ERROR);

    expect($body['data']['attributes']['heroTitle'])->toBe('Aliased title');
});

it('hydrates one api alias into multiple model properties', function () {
    $resource = new Resource(
        type: 'post',
        id: '42',
        attributes: ['headline' => 'Shared title'],
    );

    $model = new
        #[Blueprint('post')]
        class extends Model {
            #[Alias('headline')]
            public ?string $title = null;

            #[Alias('headline')]
            public ?string $heading = null;
        };

    $model->hydrateFromResource($resource);

    expect($model->title)->toBe('Shared title');
    expect($model->heading)->toBe('Shared title');
});

it('hydrates aliased relationships into model properties', function () {
    $resource = new Resource(
        type: 'foo',
        id: '1',
        attributes: [],
        relationships: [
            'linked-entry' => new Relation(
                name: 'linked-entry',
                data: null,
            ),
        ],
    );

    $model = new
        #[Blueprint('foo')]
        class extends Model {
            #[Relationship]
            #[Alias('linked-entry')]
            public ?Model $relatedModel = null;

            public function __construct()
            {
                $this->relatedModel = new
                    #[Blueprint('related')]
                    class extends Model {};
            }
        };

    $model->hydrateFromResource($resource);

    expect($model->relatedModel)->toBeNull();
});

it('serializes aliased relationships using alias keys', function () {
    $related = new
        #[Blueprint('related')]
        class extends Model {
            public function __construct()
            {
                $this->id = 77;
            }
        };

    $model = new
        #[Blueprint('foo')]
        class extends Model {
            #[Relationship]
            #[Alias('linked-entry')]
            public ?Model $relatedModel = null;
        };

    $model->hydrateFromResource(new Resource(type: 'foo', id: '1'));
    $model->relatedModel = $related;

    $body = json_decode((string) $model->getSyncBody(), associative: true, flags: JSON_THROW_ON_ERROR);

    expect($body['data']['relationships'])->toHaveKey('linked-entry');
    expect($body['data']['relationships'])->not->toHaveKey('relatedModel');
    expect($body['data']['relationships']['linked-entry']['data'])->toBe([
        'id' => '77',
        'type' => 'related',
    ]);
});

it('supports Relationship(alias) without Alias for serialization and hydration', function () {
    $resource = new Resource(
        type: 'foo',
        id: '1',
        relationships: [
            'linked-entry' => new Relation(
                name: 'linked-entry',
                data: null,
            ),
        ],
    );

    $related = new
        #[Blueprint('related')]
        class extends Model {
            public function __construct()
            {
                $this->id = 88;
            }
        };

    $model = new
        #[Blueprint('foo')]
        class extends Model {
            #[Relationship('linked-entry')]
            public ?Model $relatedModel = null;
        };

    $model->hydrateFromResource($resource);
    $model->relatedModel = $related;

    $serialized = json_decode((string) $model->getSyncBody(), associative: true, flags: JSON_THROW_ON_ERROR);

    expect($serialized['data']['relationships']['linked-entry']['data'])->toBe([
        'id' => '88',
        'type' => 'related',
    ]);
});

it('throws when both Relationship(alias) and Alias are declared on one property', function () {
    $model = new
        #[Blueprint('foo')]
        class extends Model {
            #[Relationship('linked-entry')]
            #[Alias('linked-entry')]
            public ?Model $relatedModel = null;
        };

    $model->hydrateFromResource(new Resource(type: 'foo', id: '1'));
    $model->getSyncBody();
})->throws(
    InvalidArgumentException::class,
    "Property 'relatedModel' cannot declare both #[Relationship('...')] and #[Alias('...')].",
);

it('serializes newly assigned to-one relationships using relationship attribute', function () {
    $related = new
        #[Blueprint('related')]
        class extends Model {
            public function __construct()
            {
                $this->id = 99;
            }
        };

    $model = new
        #[Blueprint('foo')]
        class extends Model {
            #[Relationship('related-model')]
            public ?Model $relatedModel = null;
        };

    $model->hydrateFromResource(new Resource(type: 'foo', id: '1'));
    $model->relatedModel = $related;

    $body = json_decode((string) $model->getSyncBody(), associative: true, flags: JSON_THROW_ON_ERROR);

    expect($body['data']['relationships']['related-model']['data'])->toBe([
        'id' => '99',
        'type' => 'related',
    ]);

    expect($body['data']['relationships']['related-model']['meta'])->toBeEmpty();
});

it('serializes newly assigned to-many relationships and relationship meta overrides', function () {
    $relatedA = new
        #[Blueprint('related')]
        class extends Model {
            public function __construct()
            {
                $this->id = 10;
            }
        };

    $relatedB = new
        #[Blueprint('related')]
        class extends Model {
            public function __construct()
            {
                $this->id = 11;
            }
        };

    $model = new
        #[Blueprint('foo')]
        class extends Model {
            #[Relationship('related-models')]
            public array $relatedModels = [];
        };

    $model->hydrateFromResource(new Resource(type: 'foo', id: '1'));
    $model->relatedModels = [$relatedA, $relatedB];
    $model->setRelationshipMeta('related-models', ['source' => 'manual']);

    $body = json_decode((string) $model->getSyncBody(), associative: true, flags: JSON_THROW_ON_ERROR);

    expect($body['data']['relationships']['related-models']['data'])->toBe([
        ['id' => '10', 'type' => 'related'],
        ['id' => '11', 'type' => 'related'],
    ]);

    expect($body['data']['relationships']['related-models']['meta'])->toBe([
        'source' => 'manual',
    ]);
});

it('throws when assigning unsynced construct in to-many relationship payload', function () {
    $unsynced = new
        #[Blueprint('related')]
        class extends Model {};

    $model = new
        #[Blueprint('foo')]
        class extends Model {
            #[Relationship('related-models')]
            public array $relatedModels = [];
        };

    $model->hydrateFromResource(new Resource(type: 'foo', id: '1'));
    $model->relatedModels = [$unsynced];

    $model->getSyncBody();
})->throws(RuntimeException::class, 'Related constructs must be synced before they can be linked.');

it('throws when assigning unsynced construct in to-one relationship payload', function () {
    $unsynced = new
        #[Blueprint('related')]
        class extends Model {};

    $model = new
        #[Blueprint('foo')]
        class extends Model {
            #[Relationship('related-model')]
            public ?Model $relatedModel = null;
        };

    $model->hydrateFromResource(new Resource(type: 'foo', id: '1'));
    $model->relatedModel = $unsynced;

    $model->getSyncBody();
})->throws(RuntimeException::class, 'Related constructs must be synced before they can be linked.');

it('allows hydrating the same instance more than once', function () {
    $first = new Resource(
        type: 'post',
        id: '1',
        attributes: ['title' => 'First'],
    );

    $second = new Resource(
        type: 'post',
        id: '2',
        attributes: ['title' => 'Second'],
    );

    $model = new
        #[Blueprint('post')]
        class extends Model {
            public ?string $title = null;
        };

    $model->hydrateFromResource($first);
    $model->hydrateFromResource($second);

    expect($model->id)->toBe(2)->and($model->title)->toBe('Second');
});

dataset('attribute_alias_matrix', [
    'camelCase api field' => ['heroTitle'],
    'kebab-case api field' => ['hero-title'],
]);

it('supports attribute alias hydration and serialization', function (string $field) {
    $resource = new Resource(
        type: 'post',
        id: '42',
        attributes: [$field => 'Aliased title'],
    );

    $model = $field === 'heroTitle'
        ? new
            #[Blueprint('post')]
            class extends Model {
                public ?string $heroTitle = null;
            }
        : new
            #[Blueprint('post')]
            class extends Model {
                #[Alias('hero-title')]
                public ?string $heroTitle = null;
            };

    $model->hydrateFromResource($resource);

    expect($model->heroTitle)->toBe('Aliased title');

    $body = json_decode((string) $model->getSyncBody(), associative: true, flags: JSON_THROW_ON_ERROR);

    expect($body['data']['attributes'][$field])->toBe('Aliased title');
})->with('attribute_alias_matrix');

it('merges resource-derived relationships after pre-hydration relationship cache warmup', function () {
    $model = new
        #[Blueprint('foo')]
        class extends Model {
            public ?Model $relatedModel = null;
        };

    expect($model->getRelationships())->toBeEmpty();

    $resource = new Resource(
        type: 'foo',
        id: '1',
        relationships: [
            'related-model' => new Relation(
                name: 'related-model',
                data: null,
            ),
        ],
    );

    $model->hydrateFromResource($resource);

    $related = new
        #[Blueprint('related')]
        class extends Model {
            public function __construct()
            {
                $this->id = 55;
            }
        };

    $model->relatedModel = $related;

    $body = json_decode((string) $model->getSyncBody(), associative: true, flags: JSON_THROW_ON_ERROR);

    expect($body['data']['relationships'])->toHaveKey('related-model');
    expect($body['data']['relationships']['related-model']['data'])->toBe([
        'id' => '55',
        'type' => 'related',
    ]);
});
