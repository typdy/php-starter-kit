<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Sync\Data;

use Typdy\StarterKit\Models\Contracts\Construct;
use Typdy\StarterKit\Storage\Contracts\Driver;

/**
 * @template TModel of Construct
 */
final readonly class Result
{
    /**
     * @param TModel|iterable<int, TModel>|null $data
     *
     * @param class-string<Driver>|null $sourceDriver the data source
     * @param list<class-string<Driver>> $targetDrivers targets of promotion
     */
    public function __construct(
        public bool $served = false,
        public bool $stale = false,
        public bool $promoted = false,
        public ?string $sourceDriver = null,
        public array $targetDrivers = [],
        public Construct|iterable|null $data = null,
    ) {}
}
