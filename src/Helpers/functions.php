<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Helpers;

use JsonException;
use Psr\Http\Client\ClientExceptionInterface;
use RuntimeException;
use Typdy\StarterKit\Api\Enums\HttpMethod;
use Typdy\StarterKit\Api\RequestCoordinator;
use Typdy\StarterKit\Parsers\Data\Document;
use Typdy\StarterKit\Parsers\Exceptions\ResponseParserException;
use Typdy\StarterKit\Repositories\Contracts\Collection;
use Typdy\StarterKit\Resolvers\Contracts\ResolvesRepositories;
use Typdy\StarterKit\Typdy;

/**
 * @return ($blueprint is null ? RequestCoordinator : Collection)
 */
function typdy(?string $blueprint = null, ?string $project = null, ?string $team = null): Collection|RequestCoordinator
{
    if ($blueprint === null) {
        return new RequestCoordinator($team, $project);
    }

    return repo($blueprint, $project, $team);
}

function repo(string $blueprint, ?string $project = null, ?string $team = null): Collection
{
    $project ??= Typdy::config()->project;
    $team ??= Typdy::config()->team;

    $repository = Typdy::container(ResolvesRepositories::class)
        ->resolveOne($team, $project, $blueprint);

    if ($repository === null) {
        throw new RuntimeException(
            "No repository found for team '{$team}', project '{$project}' and blueprint '{$blueprint}'.",
        );
    }

    return $repository;
}

function grepo(?string $project = null, ?string $team = null): Collection
{
    return repo('global', $project, $team);
}

/**
 * @param ?array<string, mixed> $body
 * @param array<string, mixed> $parameters
 * @param array<string, string> $headers
 *
 * @throws JsonException|ClientExceptionInterface
 * @throws ResponseParserException
 */
function api(
    string $path,
    HttpMethod $method = HttpMethod::GET,
    ?array $body = null,
    array $parameters = [],
    array $headers = [],
    bool $useMapi = false,
    ?string $team = null,
    ?string $project = null,
): Document {
    return new RequestCoordinator($team, $project)->request(
        path: $path,
        method: $method,
        body: $body,
        parameters: $parameters,
        headers: $headers,
        useMapi: $useMapi,
    );
}
