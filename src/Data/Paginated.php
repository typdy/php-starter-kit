<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Data;

use ArrayIterator;
use Countable;
use Iterator;
use IteratorAggregate;
use Override;
use Typdy\StarterKit\Models\Contracts\Construct;

use function count;

/**
 * @template TModel of Construct
 *
 * @implements IteratorAggregate<int, TModel>
 */
final readonly class Paginated implements Countable, IteratorAggregate
{
    /**
     * @param list<TModel> $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $perPage,
        public int $currentPage = 1,
        public int $lastPage = 1,
    ) {}

    #[Override]
    public function count(): int
    {
        return count($this->items);
    }

    /**
     * @return Iterator<int, TModel>
     */
    #[Override]
    public function getIterator(): Iterator
    {
        return new ArrayIterator($this->items);
    }
}
