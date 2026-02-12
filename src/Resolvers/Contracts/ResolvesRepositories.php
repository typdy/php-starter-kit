<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Resolvers\Contracts;

use Typdy\StarterKit\Repositories\Contracts\Collection;

/**
 * @api
 */
interface ResolvesRepositories
{
    /**
     * @return list<Collection>
     */
    public function resolveMany(?string $team = null, ?string $project = null): array;

    public function resolveOne(string $team, string $project, string $blueprint): ?Collection;
}
