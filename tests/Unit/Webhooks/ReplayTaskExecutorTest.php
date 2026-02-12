<?php

declare(strict_types=1);

use Typdy\StarterKit\Containers\Contracts\Container;
use Typdy\StarterKit\Repositories\Contracts\Collection;
use Typdy\StarterKit\Repositories\Contracts\Replayable;
use Typdy\StarterKit\Resolvers\Contracts\ResolvesRepositories;
use Typdy\StarterKit\Typdy;
use Typdy\StarterKit\TypdyConfig;
use Typdy\StarterKit\Webhooks\Data\ReplayTask;
use Typdy\StarterKit\Webhooks\ReplayTaskExecutor;

beforeEach(function () {
    Typdy::$config = new TypdyConfig(team: 'team', project: 'project');

    $this->container = mock(Container::class);

    Typdy::$container = $this->container;
});

afterEach(function () {
    Typdy::$container = null;
});

it('executes a replay task against the resolved repository', function () {
    $repo = new class implements Collection, Replayable {
        public function getBlueprint(): string
        {
            return 'test';
        }

        public function getDrivers(): array
        {
            return [];
        }

        public function getProject(): string
        {
            return 'project';
        }

        public function getSignature(): string
        {
            return 'team:project:test';
        }

        public function getTeam(): string
        {
            return 'team';
        }

        public function isGlobal(): bool
        {
            return false;
        }

        public function replay(?int $constructId = null, array $options = []): int
        {
            expect($constructId)->toBe(9);
            expect($options['tries'] ?? null)->toBe(4);

            return 6;
        }

        public function replayableRequests(?int $constructId = null): array
        {
            return [];
        }
    };

    $resolver = mock(ResolvesRepositories::class);
    $resolver
        ->shouldReceive('resolveOne')
        ->once()
        ->with('team', 'project', 'test')
        ->andReturn($repo);

    $this->container
        ->shouldReceive('make')
        ->with(ResolvesRepositories::class, [])
        ->once()
        ->andReturn($resolver);

    $executor = new ReplayTaskExecutor();

    $result = $executor->execute(new ReplayTask(
        repositoryClass: $repo::class,
        team: 'team',
        project: 'project',
        blueprint: 'test',
        constructId: 9,
        options: ['tries' => 4],
    ));

    expect($result->dispatched)->toBeTrue();
    expect($result->failed)->toBeFalse();
    expect($result->replayed)->toBe(6);
});
