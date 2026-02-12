<?php

declare(strict_types=1);

use Typdy\StarterKit\Api\Contracts\Client;
use Typdy\StarterKit\Containers\Contracts\Container;
use Typdy\StarterKit\Storage\Json\JsonDriver;
use Typdy\StarterKit\Tests\Unit\Repositories\Fixtures\TestApiRepo;
use Typdy\StarterKit\Tests\Unit\Repositories\Fixtures\TestDatabaseDriver;
use Typdy\StarterKit\Typdy;
use Typdy\StarterKit\TypdyConfig;

beforeEach(function () {
    Typdy::$config = new TypdyConfig(
        team: 'config-team',
        project: 'config-project',
    );

    $mockClient = mock(Client::class);
    $mockContainer = mock(Container::class);

    $mockContainer
        ->shouldReceive('make')
        ->with(Client::class)
        ->andReturn($mockClient);

    Typdy::$container = $mockContainer;
});

afterEach(function () {
    Typdy::$container = null;
});

it('throws when the driver stack is empty', function () {
    Typdy::$config = new TypdyConfig(
        team: 'config-team',
        project: 'config-project',
        drivers: [],
    );

    new TestApiRepo()->getDrivers();
})
    ->throws(RuntimeException::class, 'At least one storage driver must be defined');

it('throws when a configured driver does not implement the driver contract', function () {
    Typdy::$config = new TypdyConfig(
        team: 'config-team',
        project: 'config-project',
        drivers: ['invalid-driver'],
    );

    new TestApiRepo()->getDrivers();
})->throws(RuntimeException::class, "Storage driver 'invalid-driver' must implement the Driver interface");

it('throws when database drivers are not listed last', function () {
    Typdy::$config = new TypdyConfig(
        team: 'config-team',
        project: 'config-project',
        drivers: [
            TestDatabaseDriver::class,
            JsonDriver::class,
        ],
    );

    new TestApiRepo()->getDrivers();
})
    ->throws(RuntimeException::class, 'Database drivers must be listed last');

it('detects whether the validated stack contains a database driver', function () {
    Typdy::$config = new TypdyConfig(
        team: 'config-team',
        project: 'config-project',
        drivers: [
            JsonDriver::class,
        ],
    );

    expect(new TestApiRepo()->hasDatabaseDriver())->toBeFalse();

    Typdy::$config = new TypdyConfig(
        team: 'config-team',
        project: 'config-project',
        drivers: [
            JsonDriver::class,
            TestDatabaseDriver::class,
        ],
    );

    expect(new TestApiRepo()->hasDatabaseDriver())->toBeTrue();
});
