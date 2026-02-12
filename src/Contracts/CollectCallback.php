<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Contracts;

/**
 * @api
 */
interface CollectCallback
{
    /**
     * @param iterable<array-key, mixed> $data
     *
     * @return iterable<array-key, mixed>
     */
    public function __invoke(iterable $data = []): iterable;
}
