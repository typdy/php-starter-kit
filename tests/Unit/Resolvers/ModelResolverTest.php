<?php

declare(strict_types=1);

use Typdy\StarterKit\Containers\Contracts\Container;
use Typdy\StarterKit\Models\Contracts\Construct;
use Typdy\StarterKit\Resolvers\ModelResolver;
use Typdy\StarterKit\Typdy;
use Typdy\StarterKit\TypdyConfig;

beforeEach(function () {
    Typdy::$config = new TypdyConfig(
        team: 'team',
        project: 'project',
        modelLocations: [
            'Typdy\\StarterKit\\Tests\\Unit\\Resolvers\\Fixtures\\Models' => __DIR__ . '/Fixtures/Models',
        ],
    );

    $mockContainer = mock(Container::class);
    $mockContainer
        ->shouldReceive('make')
        ->andReturnUsing(fn ($class) => new $class());

    Typdy::$container = $mockContainer;

    $this->resolver = new ModelResolver();
});

it('returns null if no matching construct', function () {
    $result = $this->resolver->resolveOne('team', 'project', 'nope');

    expect($result)->toBeNull();
});

it('returns a construct matching global project', function () {
    $result = $this->resolver->resolveOne('team', 'project', 'foo');

    expect($result)->toBeInstanceOf(Construct::class);
    expect($result->getTeam())->toBe('team');
    expect($result->getProject())->toBe('project');
    expect($result->getBlueprint())->toBe('foo');
});

it('returns a construct matching model project', function () {
    $result = $this->resolver->resolveOne('the-a-team', 'project-x', 'bar');

    expect($result)->toBeInstanceOf(Construct::class);
    expect($result->getTeam())->toBe('the-a-team');
    expect($result->getProject())->toBe('project-x');
    expect($result->getBlueprint())->toBe('bar');
});
