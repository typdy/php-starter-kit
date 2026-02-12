<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface;
use Typdy\StarterKit\Api\Contracts\Client;
use Typdy\StarterKit\Api\Enums\HttpMethod;
use Typdy\StarterKit\Api\RequestCoordinator;
use Typdy\StarterKit\Containers\Contracts\Container;
use Typdy\StarterKit\Parsers\Contracts\ResponseParser;
use Typdy\StarterKit\Parsers\Data\Document;
use Typdy\StarterKit\Repositories\Contracts\Collection;
use Typdy\StarterKit\Repositories\GlobalRepository;
use Typdy\StarterKit\Resolvers\Contracts\ResolvesRepositories;
use Typdy\StarterKit\Typdy;
use Typdy\StarterKit\TypdyConfig;

use function Typdy\StarterKit\Helpers\api;
use function Typdy\StarterKit\Helpers\grepo;
use function Typdy\StarterKit\Helpers\repo;
use function Typdy\StarterKit\Helpers\typdy;

require_once __DIR__ . '/../../../src/Helpers/functions.php';

beforeEach(function () {
    Typdy::$config = new TypdyConfig(
        team: 'config-team',
        project: 'config-project',
    );

    $this->resolverMock = mock(ResolvesRepositories::class);
    $this->clientMock = mock(Client::class);
    $this->parserMock = mock(ResponseParser::class);

    $this->containerMock = mock(Container::class);
    $this->containerMock
        ->shouldReceive('make')
        ->andReturnUsing(fn (string $abstract, array $parameters = []): mixed => match ($abstract) {
            ResolvesRepositories::class => $this->resolverMock,
            Client::class => $this->clientMock,
            ResponseParser::class => $this->parserMock,
            default => throw new LogicException("Unexpected container abstract: {$abstract}"),
        });

    Typdy::$container = $this->containerMock;
});

afterEach(function () {
    Typdy::$container = null;
});

describe('typdy helper function', function () {
    it('returns a request coordinator when blueprint is null', function () {
        $result = typdy();

        expect($result)->toBeInstanceOf(RequestCoordinator::class);
    });

    it('resolves a repository with a blueprint', function () {
        $repository = mock(Collection::class);

        $this->resolverMock
            ->shouldReceive('resolveOne')
            ->once()
            ->with('config-team', 'config-project', 'pages')
            ->andReturn($repository);

        $result = typdy('pages');

        expect($result)->toBe($repository);
    });

    it('resolves a repository with a blueprint, project and team', function () {
        $repository = mock(Collection::class);

        $this->resolverMock
            ->shouldReceive('resolveOne')
            ->once()
            ->with('test-team', 'test-project', 'pages')
            ->andReturn($repository);

        $result = typdy(blueprint: 'pages', project: 'test-project', team: 'test-team');

        expect($result)->toBe($repository);
    });

    it('throws when a repository cannot be resolved', function () {
        $this->resolverMock
            ->shouldReceive('resolveOne')
            ->once()
            ->with('config-team', 'config-project', 'missing')
            ->andReturnNull();

        typdy('missing');
    })
        ->throws(
            RuntimeException::class,
            "No repository found for team 'config-team', project 'config-project' and blueprint 'missing'.",
        );
});

describe('repo helper function', function () {
    it('resolves a repository with a blueprint', function () {
        $repository = mock(Collection::class);

        $this->resolverMock
            ->shouldReceive('resolveOne')
            ->once()
            ->with('config-team', 'config-project', 'articles')
            ->andReturn($repository);

        $result = repo('articles');

        expect($result)->toBe($repository);
    });

    it('resolves a repository with a blueprint, project and team', function () {
        $repository = mock(Collection::class);

        $this->resolverMock
            ->shouldReceive('resolveOne')
            ->once()
            ->with('test-team', 'test-project', 'pages')
            ->andReturn($repository);

        $result = repo(blueprint: 'pages', project: 'test-project', team: 'test-team');

        expect($result)->toBe($repository);
    });

    it('throws when a repository cannot be resolved', function () {
        $this->resolverMock
            ->shouldReceive('resolveOne')
            ->once()
            ->with('config-team', 'config-project', 'missing')
            ->andReturnNull();

        repo('missing');
    })
        ->throws(
            RuntimeException::class,
            "No repository found for team 'config-team', project 'config-project' and blueprint 'missing'.",
        );
});

describe('grepo helper function', function () {
    it('returns a global repository', function () {
        $globalRepository = mock(GlobalRepository::class);

        $this->resolverMock
            ->shouldReceive('resolveOne')
            ->once()
            ->with('team-x', 'project-x', 'global')
            ->andReturn($globalRepository);

        $result = grepo(project: 'project-x', team: 'team-x');

        expect($result)->toBe($globalRepository);
    });
});

describe('api helper function', function () {
    it('sends a request through request coordinator', function () {
        $response = mock(ResponseInterface::class);
        $document = new Document($response);

        $this->clientMock
            ->shouldReceive('request')
            ->once()
            ->withArgs(function ($path, $method, $body, $headers, $useMapi) {
                expect($path)->toBe('@config-team/config-project/pages?' . urlencode('filter[published]') . '=true');
                expect($method)->toBe(HttpMethod::POST);
                expect($body)->toBe(json_encode(['data' => 1]));
                expect($headers)->toBe(['X-Test' => '1']);
                expect($useMapi)->toBeTrue();

                return true;
            })
            ->andReturn($response);

        $this->parserMock
            ->shouldReceive('parse')
            ->once()
            ->with($response)
            ->andReturn($document);

        $result = api(
            path: '/pages',
            method: HttpMethod::POST,
            body: ['data' => 1],
            parameters: ['filter[published]' => 'true'],
            headers: ['X-Test' => '1'],
            useMapi: true,
        );

        expect($result)->toBe($document);
    });

    it('sends a request for a specific team and project', function () {
        $response = mock(ResponseInterface::class);
        $document = new Document($response);

        $this->clientMock
            ->shouldReceive('request')
            ->once()
            ->withArgs(function ($path) {
                expect($path)->toBe('@test-team/test-project/pages');

                return true;
            })
            ->andReturn($response);

        $this->parserMock
            ->shouldReceive('parse')
            ->once()
            ->with($response)
            ->andReturn($document);

        $result = api(
            path: '/pages',
            team: 'test-team',
            project: 'test-project',
        );

        expect($result)->toBe($document);
    });
});
