<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Webhooks\Data;

final readonly class Result
{
    public function __construct(
        public string $message,
        public bool $failed = false,
    ) {}
}
