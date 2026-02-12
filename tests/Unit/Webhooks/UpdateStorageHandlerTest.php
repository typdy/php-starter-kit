<?php

declare(strict_types=1);

use Typdy\StarterKit\Containers\Contracts\Container;
use Typdy\StarterKit\Models\Contracts\Construct;
use Typdy\StarterKit\Repositories\Contracts\Collection;
use Typdy\StarterKit\Repositories\Contracts\Replayable;
use Typdy\StarterKit\Repositories\Data\Request;
use Typdy\StarterKit\Resolvers\Contracts\ResolvesRepositories;
use Typdy\StarterKit\Storage\Contracts\DatabaseDriver;
use Typdy\StarterKit\Sync\Data\Metadata;
use Typdy\StarterKit\Typdy;
use Typdy\StarterKit\TypdyConfig;
use Typdy\StarterKit\Webhooks\Contracts\ReplayDispatcher;
use Typdy\StarterKit\Webhooks\Data\ReplayResult;
use Typdy\StarterKit\Webhooks\Handlers\UpdateStorageHandler;
use Typdy\StarterKit\Webhooks\Payload;
use Typdy\StarterKit\Webhooks\ResultSet;

beforeEach(function () {
    Typdy::$config = new TypdyConfig(
        team: 'team',
        project: 'project',
    );

    $this->container = mock(Container::class);

    Typdy::$container = $this->container;
});

afterEach(function () {
    Typdy::$container = null;
});

it('does nothing for unsupported webhook events', function () {
    $body = [
        'domain' => 'languages',
        'event' => 'update',
        'blueprint' => [
            'identifier' => 'test',
        ],
    ];

    $json = json_encode($body, JSON_THROW_ON_ERROR);

    // @mago-expect lint:no-literal-password
    $secret = 'secret';

    $payload = Payload::make(
        name: 'test',
        secret: $secret,
        body: $json,
        headers: ['Signature' => hash_hmac('sha256', $json, $secret)],
    );

    $handler = new UpdateStorageHandler();

    $results = new ResultSet();

    $handler->handle($payload, $results);

    expect($results->results)->toHaveCount(1);
    expect($results->results[0]->failed)->toBeFalse();
    expect($results->results[0]->message)->toContain('No action taken');
});

it('fails when payload does not provide a blueprint identifier', function () {
    $body = [
        'domain' => 'constructs',
        'event' => 'update',
    ];

    $json = json_encode($body, JSON_THROW_ON_ERROR);

    // @mago-expect lint:no-literal-password
    $secret = 'secret';

    $payload = Payload::make(
        name: 'test',
        secret: $secret,
        body: $json,
        headers: ['Signature' => hash_hmac('sha256', $json, $secret)],
    );

    $handler = new UpdateStorageHandler();

    $results = new ResultSet();

    $handler->handle($payload, $results);

    expect($results->results)->toHaveCount(1);
    expect($results->results[0]->failed)->toBeTrue();
    expect($results->results[0]->message)->toContain('did not include a blueprint');
});

it('skips replay for repositories that include a database driver', function () {
    $dbDriver = new class implements DatabaseDriver {
        public function all(Collection $repository, Request $request): ?iterable
        {
            return null;
        }

        public function delete(Collection $repository, Request $request): void {}

        public function find(Collection $repository, Request $request): ?Construct
        {
            return null;
        }

        public function getMetadata(
            Collection $repository,
            Request $request,
        ): Metadata {
            return new Metadata();
        }

        public function getReplayableRequests(Collection $repository, ?int $constructId): array
        {
            return [];
        }

        public function sync(
            Collection $repository,
            Request $request,
            Construct|iterable $data,
            array $meta = [],
        ): Construct|iterable|null {
            return null;
        }
    };

    $repo = new class($dbDriver::class) implements Collection, Replayable {
        public function __construct(
            private readonly string $dbDriverClass,
        ) {}

        public function getBlueprint(): string
        {
            return 'test';
        }

        public function getDrivers(): array
        {
            return [$this->dbDriverClass];
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
            return 0;
        }

        public function replayableRequests(?int $constructId = null): array
        {
            return [];
        }
    };

    $resolver = mock(ResolvesRepositories::class);
    $resolver
        ->shouldReceive('resolveMany')
        ->once()
        ->with('team', 'project')
        ->andReturn([$repo]);

    $dispatcher = mock(ReplayDispatcher::class);
    $dispatcher->shouldNotReceive('dispatch');

    $this->container
        ->shouldReceive('make')
        ->with(ResolvesRepositories::class, [])
        ->once()
        ->andReturn($resolver);

    $this->container
        ->shouldReceive('make')
        ->with(ReplayDispatcher::class, [])
        ->once()
        ->andReturn($dispatcher);

    $body = [
        'domain' => 'constructs',
        'event' => 'update',
        'construct' => ['id' => 42],
        'blueprint' => [
            'identifier' => 'test',
        ],
    ];

    $json = json_encode($body, JSON_THROW_ON_ERROR);

    // @mago-expect lint:no-literal-password
    $secret = 'secret';

    $payload = Payload::make(
        name: 'test',
        secret: $secret,
        body: $json,
        headers: ['Signature' => hash_hmac('sha256', $json, $secret)],
    );

    $handler = new UpdateStorageHandler();

    $results = new ResultSet();

    $handler->handle($payload, $results);

    expect($results->results)->toHaveCount(1);
    expect($results->results[0]->failed)->toBeFalse();
    expect($results->results[0]->message)->toContain('across 0 repositories (0 requests)');
});

it('replays matching repositories via dispatcher with passthrough options', function () {
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
            return 0;
        }

        public function replayableRequests(?int $constructId = null): array
        {
            return [];
        }
    };

    $resolver = mock(ResolvesRepositories::class);
    $resolver
        ->shouldReceive('resolveMany')
        ->once()
        ->with('team', 'project')
        ->andReturn([$repo]);

    $dispatcher = mock(ReplayDispatcher::class);
    $dispatcher
        ->shouldReceive('dispatch')
        ->once()
        ->with($repo, 42, \Mockery::on(static fn (array $options): bool => ($options['tries'] ?? null) === 5))
        ->andReturn(new ReplayResult(dispatched: true, replayed: 3));

    $this->container
        ->shouldReceive('make')
        ->with(ResolvesRepositories::class, [])
        ->once()
        ->andReturn($resolver);

    $this->container
        ->shouldReceive('make')
        ->with(ReplayDispatcher::class, [])
        ->once()
        ->andReturn($dispatcher);

    $body = [
        'domain' => 'constructs',
        'event' => 'update',
        'construct' => ['id' => 42],
        'blueprint' => [
            'identifier' => 'test',
        ],
    ];

    $json = json_encode($body, JSON_THROW_ON_ERROR);

    // @mago-expect lint:no-literal-password
    $secret = 'secret';

    $payload = Payload::make(
        name: 'test',
        secret: $secret,
        body: $json,
        headers: ['Signature' => hash_hmac('sha256', $json, $secret)],
    );

    $handler = new UpdateStorageHandler()->withOptions([
        'tries' => 5,
    ]);

    $results = new ResultSet();

    $handler->handle($payload, $results);

    expect($results->results)->toHaveCount(1);
    expect($results->results[0]->failed)->toBeFalse();
    expect($results->results[0]->message)->toContain('across 1 repositories (3 requests)');
});
