<?php

declare(strict_types=1);

use Typdy\StarterKit\Api\Contracts\Client;
use Typdy\StarterKit\Containers\Contracts\Container;
use Typdy\StarterKit\Tests\Unit\Repositories\Fixtures\TestMapiRepo;
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

it('switches to a management-api endpoint', function () {
    $repo = new TestMapiRepo();

    expect($repo->mapi()->getEndpoint())->toContain('@test-team/test-project/constructs/test');
});

it('switches to a delivery-api endpoint', function () {
    $repo = new TestMapiRepo();

    expect($repo->api()->getEndpoint())->toContain('@test-team/test-project/tests');
});
