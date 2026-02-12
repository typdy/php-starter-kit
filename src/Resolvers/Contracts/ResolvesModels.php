<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Resolvers\Contracts;

use Typdy\StarterKit\Models\Contracts\Construct;

/**
 * @api
 */
interface ResolvesModels
{
    /**
     * @return list<Construct>
     */
    public function resolveMany(?string $team = null, ?string $project = null): array;

    public function resolveOne(string $team, string $project, string $blueprint): ?Construct;
}
