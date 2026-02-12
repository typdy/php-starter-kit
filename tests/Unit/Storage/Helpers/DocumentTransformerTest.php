<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response;
use Typdy\StarterKit\Attributes\Blueprint;
use Typdy\StarterKit\Models\Model;
use Typdy\StarterKit\Parsers\Data\Document;
use Typdy\StarterKit\Parsers\Data\Resource;
use Typdy\StarterKit\Repositories\Contracts\Collection;
use Typdy\StarterKit\Resolvers\Contracts\ResolvesModels;
use Typdy\StarterKit\Storage\Helpers\DocumentTransformer;
use Typdy\StarterKit\Typdy;
use Typdy\StarterKit\TypdyConfig;

beforeEach(function () {
    Typdy::$config = new TypdyConfig(
        team: 'team',
        project: 'project',
        privateStoragePath: sys_get_temp_dir(),
    );

    $this->makeRepository = fn () => mock(Collection::class)
        ->shouldReceive('getTeam')
        ->andReturn('team')
        ->getMock()
        ->shouldReceive('getProject')
        ->andReturn('project')
        ->getMock()
        ->shouldReceive('getBlueprint')
        ->andReturn('test')
        ->getMock()
        ->shouldReceive('getSignature')
        ->andReturn('team:project:test')
        ->getMock();

    $this->makeResolver = function () {
        $resolver = mock(ResolvesModels::class);

        $resolver
            ->shouldReceive('resolveOne')
            ->andReturnUsing(
                fn () => new
                    #[Blueprint('test')]
                    class extends Model {
                        public ?string $title = null;
                    },
            );

        return $resolver;
    };
});

it('transforms single-resource documents into models', function () {
    $transformer = new DocumentTransformer(($this->makeResolver)());

    $document = new Document(
        response: new Response(200),
        data: new Resource(
            type: 'test',
            id: '10',
            attributes: ['title' => 'Single'],
        ),
    );

    $model = $transformer->transform(($this->makeRepository)(), $document);

    expect($model)->toBeInstanceOf(Model::class);
    expect($model->id)->toBe(10);
    expect($model->title)->toBe('Single');
});

it('transforms resource arrays in documents into model collections', function () {
    $transformer = new DocumentTransformer(($this->makeResolver)());

    $document = new Document(
        response: new Response(200),
        data: [
            new Resource(type: 'test', id: '1', attributes: ['title' => 'One']),
            new Resource(type: 'test', id: '2', attributes: ['title' => 'Two']),
        ],
    );

    $models = $transformer->transform(($this->makeRepository)(), $document);

    expect($models)->toBeIterable();
    expect(array_values((array) $models))->toHaveCount(2);
});

it('throws when a resource type does not match the repository blueprint', function () {
    $transformer = new DocumentTransformer(($this->makeResolver)());

    $repository = mock(Collection::class)
        ->shouldReceive('getBlueprint')
        ->andReturn('expected')
        ->getMock();

    $document = new Document(
        response: new Response(200),
        data: new Resource(type: 'other', id: '1', attributes: ['title' => 'Wrong']),
    );

    $transformer->transform($repository, $document);
})->throws(InvalidArgumentException::class, "Resource type 'other' does not match expected type 'expected'.");

it('throws when no model can be resolved for a resource type', function () {
    $resolver = mock(ResolvesModels::class)
        ->shouldReceive('resolveOne')
        ->andReturn(null)
        ->getMock();

    $transformer = new DocumentTransformer($resolver);

    $repository = mock(Collection::class)
        ->shouldReceive('getTeam')
        ->andReturn('team')
        ->getMock()
        ->shouldReceive('getProject')
        ->andReturn('project')
        ->getMock()
        ->shouldReceive('getBlueprint')
        ->andReturn('test')
        ->shouldReceive('getSignature')
        ->andReturn('team:project:test')
        ->getMock();

    $document = new Document(
        response: new Response(200),
        data: new Resource(type: 'test', id: '1', attributes: ['title' => 'Missing model']),
    );

    $transformer->transform($repository, $document);
})->throws(RuntimeException::class, "No construct model found for blueprint 'test'.");

it('returns null when document data is null', function () {
    $transformer = new DocumentTransformer(($this->makeResolver)());

    $document = new Document(response: new Response(200), data: null);

    $result = $transformer->transform(mock(Collection::class), $document);

    expect($result)->toBeNull();
});
