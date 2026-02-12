<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Storage\Contracts;

use Typdy\StarterKit\Models\Contracts\Construct;
use Typdy\StarterKit\Repositories\Contracts\Collection;
use Typdy\StarterKit\Repositories\Data\Request;
use Typdy\StarterKit\Sync\Data\Metadata;

/**
 * @api
 */
interface Driver
{
    /**
     * @return iterable<int, Construct>|null Returns iterable with native collection.
     */
    public function all(Collection $repository, Request $request): ?iterable;

    public function delete(Collection $repository, Request $request): void;

    public function find(Collection $repository, Request $request): ?Construct;

    public function getMetadata(Collection $repository, Request $request): Metadata;

    /**
     * @return list<Request>
     */
    public function getReplayableRequests(Collection $repository, ?int $constructId): array;

    /**
     * @param Construct|iterable<int, Construct> $data
     * @param array<array-key, mixed> $meta
     *
     * @return Construct|iterable<int, Construct>|null Returns iterable with native collection.
     */
    public function sync(
        Collection $repository,
        Request $request,
        Construct|iterable $data,
        array $meta = [],
    ): Construct|iterable|null;
}
