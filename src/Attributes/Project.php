<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Project
{
    public function __construct(
        public string $team,
        public string $project,
    ) {}
}
