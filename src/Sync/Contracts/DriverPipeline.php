<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Sync\Contracts;

use Typdy\StarterKit\Models\Contracts\Construct;
use Typdy\StarterKit\Parsers\Data\Document;
use Typdy\StarterKit\Repositories\Contracts\Collection;
use Typdy\StarterKit\Repositories\Data\Request;
use Typdy\StarterKit\Storage\Contracts\Driver;
use Typdy\StarterKit\Sync\Data\Metadata;
use Typdy\StarterKit\Sync\Data\Result;

/**
 * @api
 *
 * @template TModel of Construct
 */
interface DriverPipeline
{
    /**
     * @return Result<TModel>
     */
    public function delete(Collection $repository, Request $request): Result;

    public function getMetadata(Collection $repository, Request $request, ?Document $document = null): Metadata;

    /**
     * @return list<Request>
     */
    public function getReplayableRequests(Collection $repository, ?int $constructId): array;

    /**
     * @param TModel|iterable<int, TModel> $data
     * @param array<array-key, mixed> $meta
     * @param class-string<Driver> $sourceDriver
     * @param list<class-string<Driver>> $higherPriorityDrivers
     *
     * @return Result<TModel>
     */
    public function promote(
        Collection $repository,
        Request $request,
        Construct|iterable $data,
        array $meta,
        string $sourceDriver,
        array $higherPriorityDrivers,
    ): Result;

    /**
     * @return Result<TModel>
     */
    public function read(Collection $repository, Request $request, string $method): Result;

    /**
     * @param TModel|iterable<int, TModel> $data
     *
     * @return Result<TModel>
     */
    public function write(
        Collection $repository,
        Request $request,
        Construct|iterable $data,
        Document $document,
    ): Result;
}
