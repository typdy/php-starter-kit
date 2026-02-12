<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Contracts;

/**
 * @api
 */
interface DecollectCallback
{
    /**
     * @param iterable<array-key, mixed> $data
     *
     * @return array<array-key, mixed>
     */
    public function __invoke(iterable $data = []): array;
}
