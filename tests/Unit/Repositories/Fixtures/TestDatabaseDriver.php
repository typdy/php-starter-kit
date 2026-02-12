<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Tests\Unit\Repositories\Fixtures;

use Typdy\StarterKit\Models\Contracts\Construct;
use Typdy\StarterKit\Repositories\Contracts\Collection;
use Typdy\StarterKit\Repositories\Data\Request;
use Typdy\StarterKit\Storage\Contracts\DatabaseDriver;
use Typdy\StarterKit\Sync\Data\Metadata;

class TestDatabaseDriver implements DatabaseDriver
{
    public function all(Collection $repository, Request $request): ?iterable
    {
        return null;
    }

    public function delete(Collection $repository, Request $request): void {}

    public function find(Collection $repository, Request $request): ?Construct
    {
        return null;
    }

    public function getMetadata(Collection $repository, Request $request): Metadata
    {
        return new Metadata();
    }

    public function getReplayableRequests(Collection $repository, ?int $constructId): array
    {
        return [];
    }

    public function sync(
        Collection $repository,
        Request $request,
        Construct|iterable $data,
        array $meta = [],
    ): Construct|iterable|null {
        return $data;
    }
}
