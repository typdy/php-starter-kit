<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Models\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Alias
{
    public function __construct(
        public string $name,
    ) {}
}
