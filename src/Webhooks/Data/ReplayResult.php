<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Webhooks\Data;

final readonly class ReplayResult
{
    public function __construct(
        public bool $dispatched,
        public int $replayed = 0,
        public bool $failed = false,
    ) {}
}
