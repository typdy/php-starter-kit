<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Parsers\Data;

final readonly class Error
{
    /**
     * @param array<string, mixed> $meta
     */
    public function __construct(
        public ?string $id = null,
        public ?string $status = null,
        public ?string $code = null,
        public ?string $title = null,
        public ?string $detail = null,
        public array $meta = [],
    ) {}
}
