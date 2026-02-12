<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Sync\Data;

use DateTimeImmutable;

final readonly class Metadata
{
    /**
     * TODO(@piranhageorge): add fields for pagination, etc.
     *
     * @param array<array-key, mixed> $raw
     */
    public function __construct(
        public ?DateTimeImmutable $fetchedAt = null,
        public ?string $revision = null,
        public array $raw = [],
    ) {}
}
