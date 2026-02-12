<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface;
use Typdy\StarterKit\Containers\Contracts\Container;
use Typdy\StarterKit\Parsers\Data\Document;
use Typdy\StarterKit\Parsers\Data\Resource;
use Typdy\StarterKit\Repositories\Contracts\Collection;
use Typdy\StarterKit\Repositories\Data\Request;
use Typdy\StarterKit\Sync\Data\Metadata;
use Typdy\StarterKit\Sync\DriverPipeline;
use Typdy\StarterKit\Tests\Unit\Repositories\Fixtures\TestDatabaseDriver;
use Typdy\StarterKit\Tests\Unit\Repositories\Fixtures\TestDriver;
use Typdy\StarterKit\Tests\Unit\Repositories\Fixtures\TestModel;
use Typdy\StarterKit\Typdy;
use Typdy\StarterKit\TypdyConfig;

beforeEach(function () {
    Typdy::$config = new TypdyConfig(
        team: 'test-team',
        project: 'test-project',
    );

    $this->container = mock(Container::class);

    $this->repository = new class implements Collection {
        public function getBlueprint(): string
        {
            return 'test';
        }

        /**
         * @return list<class-string>
         */
        public function getDrivers(): array
        {
            return [
                TestDriver::class,
                TestDatabaseDriver::class,
            ];
        }

        public function getProject(): string
        {
            return 'test-project';
        }

        public function getSignature(): string
        {
            return 'test-team:test-project:test';
        }

        public function getTeam(): string
        {
            return 'test-team';
        }

        public function isGlobal(): bool
        {
            return false;
        }
    };
});

afterEach(function () {
    Typdy::$container = null;
});

it('returns the first driver hit without promotion when top driver resolves the read', function () {
    $model = new TestModel();

    $topDriver = mock(TestDriver::class);

    $topDriver
        ->shouldReceive('find')
        ->once()
        ->andReturn($model);

    $topDriver
        ->shouldReceive('getMetadata')
        ->once()
        ->andReturn(new Metadata());

    $topDriver->shouldNotReceive('sync');

    $this->container
        ->shouldReceive('make')
        ->with(TestDriver::class)
        ->once()
        ->andReturn($topDriver);

    $pipeline = new DriverPipeline($this->container);

    $result = $pipeline->read($this->repository, new Request('team', 'project', id: 1), 'find');

    expect($result->served)->toBeTrue();
    expect($result->promoted)->toBeFalse();
    expect($result->sourceDriver)->toBe(TestDriver::class);
    expect($result->data)->toBe($model);
});

it('marks read hits as stale when fetchedAt is not set', function () {
    $model = new TestModel();

    $topDriver = mock(TestDriver::class);

    $topDriver
        ->shouldReceive('find')
        ->once()
        ->andReturn($model);

    $topDriver
        ->shouldReceive('getMetadata')
        ->once()
        ->andReturn(new Metadata());

    $topDriver->shouldNotReceive('sync');

    $this->container
        ->shouldReceive('make')
        ->with(TestDriver::class)
        ->once()
        ->andReturn($topDriver);

    $pipeline = new DriverPipeline($this->container);

    $result = $pipeline->read($this->repository, new Request('team', 'project', id: 1), 'find');

    expect($result->served)->toBeTrue();
    expect($result->stale)->toBeTrue();
});

it('promotes read results when a lower-priority driver resolves the read', function () {
    $model = new TestModel();

    $request = new Request('team', 'project', id: 1);

    $topDriver = mock(TestDriver::class);

    $topDriver
        ->shouldReceive('find')
        ->once()
        ->andReturnNull();

    $topDriver
        ->shouldReceive('sync')
        ->once()
        ->with($this->repository, $request, $model, [])
        ->andReturn($model);

    $lowerDriver = mock(TestDatabaseDriver::class);

    $lowerDriver
        ->shouldReceive('find')
        ->once()
        ->andReturn($model);

    $lowerDriver
        ->shouldReceive('getMetadata')
        ->once()
        ->andReturn(new Metadata());

    $this->container
        ->shouldReceive('make')
        ->with(TestDriver::class)
        ->twice()
        ->andReturn($topDriver);

    $this->container
        ->shouldReceive('make')
        ->with(TestDatabaseDriver::class)
        ->once()
        ->andReturn($lowerDriver);

    $pipeline = new DriverPipeline($this->container);

    $result = $pipeline->read($this->repository, $request, 'find');

    expect($result->served)->toBeTrue();
    expect($result->promoted)->toBeTrue();
    expect($result->sourceDriver)->toBe(TestDatabaseDriver::class);
    expect($result->targetDrivers)->toBe([TestDriver::class]);
    expect($result->data)->toBe($model);
});

it('writes through all drivers in stack order', function () {
    $model = new TestModel();

    $document = new Document(
        response: mock(ResponseInterface::class),
        data: new Resource(type: 'test', id: '1', attributes: [
            'identifier' => 'x',
            'title' => 'xxx',
        ]),
    );

    $request = new Request('team', 'project', identifier: 'x');

    $topDriver = mock(TestDriver::class);
    $topDriver
        ->shouldReceive('sync')
        ->once()
        ->with($this->repository, $request, $model, [])
        ->andReturn($model);

    $lowerDriver = mock(TestDatabaseDriver::class);
    $lowerDriver
        ->shouldReceive('sync')
        ->once()
        ->with($this->repository, $request, $model, [])
        ->andReturn($model);

    $this->container
        ->shouldReceive('make')
        ->with(TestDriver::class)
        ->once()
        ->andReturn($topDriver);

    $this->container
        ->shouldReceive('make')
        ->with(TestDatabaseDriver::class)
        ->once()
        ->andReturn($lowerDriver);

    $pipeline = new DriverPipeline($this->container);

    $result = $pipeline->write($this->repository, $request, $model, $document);

    expect($result->served)->toBeTrue();
    expect($result->data)->toBe($model);
});

it('deletes across all drivers in stack order', function () {
    $request = new Request('team', 'project', id: 3, query: ['parameters' => ['foo' => 'bar']]);

    $topDriver = mock(TestDriver::class);
    $topDriver
        ->shouldReceive('delete')
        ->once()
        ->with($this->repository, $request);

    $lowerDriver = mock(TestDatabaseDriver::class);
    $lowerDriver
        ->shouldReceive('delete')
        ->once()
        ->with($this->repository, $request);

    $this->container
        ->shouldReceive('make')
        ->with(TestDriver::class)
        ->once()
        ->andReturn($topDriver);

    $this->container
        ->shouldReceive('make')
        ->with(TestDatabaseDriver::class)
        ->once()
        ->andReturn($lowerDriver);

    $pipeline = new DriverPipeline($this->container);

    $result = $pipeline->delete($this->repository, $request);

    expect($result->served)->toBeTrue();
});

it('does not promote read hits when promotion is disabled in config', function () {
    Typdy::$config = new TypdyConfig(
        team: 'test-team',
        project: 'test-project',
        promoteReadHits: false,
    );

    $model = new TestModel();

    $topDriver = mock(TestDriver::class);
    $topDriver
        ->shouldReceive('find')
        ->once()
        ->andReturnNull();

    $topDriver->shouldNotReceive('sync');

    $lowerDriver = mock(TestDatabaseDriver::class);
    $lowerDriver
        ->shouldReceive('find')
        ->once()
        ->andReturn($model);

    $lowerDriver
        ->shouldReceive('getMetadata')
        ->once()
        ->andReturn(new Metadata());

    $this->container
        ->shouldReceive('make')
        ->with(TestDriver::class)
        ->once()
        ->andReturn($topDriver);

    $this->container
        ->shouldReceive('make')
        ->with(TestDatabaseDriver::class)
        ->once()
        ->andReturn($lowerDriver);

    $pipeline = new DriverPipeline($this->container);

    $result = $pipeline->read($this->repository, new Request('team', 'project', id: 1), 'find');

    expect($result->served)->toBeTrue();
    expect($result->promoted)->toBeFalse();
    expect($result->targetDrivers)->toBeEmpty();
});
