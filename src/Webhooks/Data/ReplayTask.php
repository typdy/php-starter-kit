<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Webhooks\Data;

use Typdy\StarterKit\Repositories\Contracts\Collection;
use Typdy\StarterKit\Repositories\Contracts\Replayable;

final readonly class ReplayTask
{
    /**
     * @param class-string<Collection&Replayable> $repositoryClass
     * @param array<string, mixed> $options
     */
    public function __construct(
        public string $repositoryClass,
        public string $team,
        public string $project,
        public string $blueprint,
        public ?int $constructId = null,
        public array $options = [],
    ) {}
}
