<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Models\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD)]
final readonly class Relationship
{
    public function __construct(
        public ?string $alias = null,
    ) {}
}
