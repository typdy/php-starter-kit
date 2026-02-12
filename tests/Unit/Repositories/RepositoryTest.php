<?php

declare(strict_types=1);

use Typdy\StarterKit\Api\Contracts\Client;
use Typdy\StarterKit\Attributes\Collection as CollectionAttribute;
use Typdy\StarterKit\Attributes\Project;
use Typdy\StarterKit\Containers\Contracts\Container;
use Typdy\StarterKit\Repositories\Repository;
use Typdy\StarterKit\Tests\Unit\Repositories\Fixtures\TestApiRepo;
use Typdy\StarterKit\Typdy;
use Typdy\StarterKit\TypdyConfig;

beforeEach(function () {
    Typdy::$config = new TypdyConfig(
        team: 'test-team',
        project: 'test-project',
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

it('can be instantiated and has a client', function () {
    $repo = new TestApiRepo();

    expect($repo->client)->toBeInstanceOf(Client::class);
});

it('is not a globals repository', function () {
    $repo = new TestApiRepo();

    expect($repo->isGlobal())->toBeFalse();
});

it('throws if a blueprint is not set', function () {
    $repo = new class extends Repository {};

    $repo->getBlueprint();
})->throws(LogicException::class, 'Blueprint attribute not found');

it('guesses the collection when not provided', function () {
    $repo = new TestApiRepo();

    expect($repo->getCollection())->toBe('tests');
});

it('returns the collection when provided', function () {
    $repo = new
        #[CollectionAttribute('testing')]
        class extends Repository {};

    expect($repo->getCollection())->toBe('testing');
});

it('falls back to globally configured team and project', function () {
    $repo = new TestApiRepo();

    expect($repo->getTeam())->toBe('test-team');
    expect($repo->getProject())->toBe('test-project');
});

it('overrides team and project when set', function () {
    $repo = new
        #[Project('the-a-team', 'project-x')]
        class extends Repository {};

    expect($repo->getTeam())->toBe('the-a-team');
    expect($repo->getProject())->toBe('project-x');
});

it('generates a signature', function () {
    $repo = new TestApiRepo();

    expect($repo->getSignature())->toBe('test-team:test-project:test');
});

it('throws if the blueprint is missing for signatures', function () {
    $repo = new class extends Repository {};

    $repo->getSignature();
})->throws(LogicException::class, 'Blueprint attribute not found');
