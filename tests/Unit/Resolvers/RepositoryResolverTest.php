<?php

declare(strict_types=1);

use Typdy\StarterKit\Containers\Contracts\Container;
use Typdy\StarterKit\Repositories\Contracts\Collection;
use Typdy\StarterKit\Resolvers\RepositoryResolver;
use Typdy\StarterKit\Typdy;
use Typdy\StarterKit\TypdyConfig;

beforeEach(function () {
    Typdy::$config = new TypdyConfig(
        team: 'team',
        project: 'project',
        repositoryLocations: [
            'Typdy\\StarterKit\\Tests\\Unit\\Resolvers\\Fixtures\\Repositories' => __DIR__ . '/Fixtures/Repositories',
        ],
    );

    $mockContainer = mock(Container::class);

    $mockContainer
        ->shouldReceive('make')
        ->andReturnUsing(fn ($class) => new $class());

    Typdy::$container = $mockContainer;

    $this->resolver = new RepositoryResolver();
});

it('returns null if no matching repo', function () {
    $result = $this->resolver->resolveOne('team', 'project', 'nope');

    expect($result)->toBeNull();
});

it('returns a repo matching global project', function () {
    $result = $this->resolver->resolveOne('team', 'project', 'foo');

    expect($result)->toBeInstanceOf(Collection::class);
    expect($result->getTeam())->toBe('team');
    expect($result->getProject())->toBe('project');
    expect($result->getBlueprint())->toBe('foo');
});

it('returns a repo matching repo project', function () {
    $result = $this->resolver->resolveOne('the-a-team', 'project-x', 'bar');

    expect($result)->toBeInstanceOf(Collection::class);
    expect($result->getTeam())->toBe('the-a-team');
    expect($result->getProject())->toBe('project-x');
    expect($result->getBlueprint())->toBe('bar');
});

it('returns a global repo', function () {
    $result = $this->resolver->resolveOne('the-a-team', 'project-x', 'global');

    expect($result)->toBeInstanceOf(Collection::class);
    expect($result->getTeam())->toBe('the-a-team');
    expect($result->getProject())->toBe('project-x');
    expect($result->isGlobal())->toBeTrue();
});
