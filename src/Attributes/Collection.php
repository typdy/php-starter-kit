<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Collection
{
    public function __construct(
        public string $collection,
    ) {}
}
