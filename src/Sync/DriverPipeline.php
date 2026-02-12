<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Sync;

use DateTimeImmutable;
use Override;
use Typdy\StarterKit\Containers\Contracts\Container;
use Typdy\StarterKit\Models\Contracts\Construct;
use Typdy\StarterKit\Parsers\Data\Document;
use Typdy\StarterKit\Repositories\Contracts\Collection;
use Typdy\StarterKit\Repositories\Data\Request;
use Typdy\StarterKit\Sync\Contracts\DriverPipeline as Contract;
use Typdy\StarterKit\Sync\Data\Metadata;
use Typdy\StarterKit\Sync\Data\Result;
use Typdy\StarterKit\Typdy;

use function array_slice;
use function max;

/**
 * @template TModel of Construct
 *
 * @implements Contract<TModel>
 */
final readonly class DriverPipeline implements Contract
{
    private Container $container;

    public function __construct(?Container $container = null)
    {
        $this->container = $container ?? Typdy::container();
    }

    #[Override]
    public function delete(Collection $repository, Request $request): Result
    {
        foreach ($repository->getDrivers() as $class) {
            $driver = $this->container->make($class);

            $driver->delete($repository, $request);
        }

        return new Result(served: true);
    }

    #[Override]
    public function getMetadata(Collection $repository, Request $request, ?Document $document = null): Metadata
    {
        $class = $repository->getDrivers()[0] ?? null;

        if ($class === null) {
            return new Metadata(raw: $document->meta ?? []);
        }

        $driver = $this->container->make($class);

        return $driver->getMetadata($repository, $request);
    }

    #[Override]
    public function getReplayableRequests(Collection $repository, ?int $constructId): array
    {
        $requests = [];

        foreach ($repository->getDrivers() as $class) {
            $driver = $this->container->make($class);

            $requests = [
                ...$requests,
                ...$driver->getReplayableRequests($repository, $constructId),
            ];
        }

        return $requests;
    }

    #[Override]
    public function promote(
        Collection $repository,
        Request $request,
        Construct|iterable $data,
        array $meta,
        string $sourceDriver,
        array $higherPriorityDrivers,
    ): Result {
        $promoted = false;

        foreach ($higherPriorityDrivers as $class) {
            if ($class === $sourceDriver) {
                continue;
            }

            $driver = $this->container->make($class);

            $result = $driver->sync($repository, $request, $data, $meta);

            if ($result !== null) {
                $promoted = true;
            }
        }

        return new Result(
            served: true,
            promoted: $promoted,
            data: $data,
            sourceDriver: $sourceDriver,
            targetDrivers: $higherPriorityDrivers,
        );
    }

    #[Override]
    public function read(Collection $repository, Request $request, string $method): Result
    {
        $drivers = $repository->getDrivers();

        foreach ($drivers as $index => $class) {
            $driver = $this->container->make($class);

            /** @var TModel|iterable<int, TModel>|null $data */
            // @mago-expect analysis:string-member-selector
            $data = $driver->$method($repository, $request);

            if ($data === null) {
                continue;
            }

            $promoted = false;
            $targetDrivers = [];

            $metadata = $driver->getMetadata($repository, $request);

            if (Typdy::config()->promoteReadHits) {
                $targetDrivers = array_slice(array: $drivers, offset: 0, length: $index);

                $promoted = $this->promote(
                    repository: $repository,
                    request: $request,
                    data: $data,
                    meta: $metadata->raw,
                    sourceDriver: $class,
                    higherPriorityDrivers: $targetDrivers,
                )->promoted;
            }

            return new Result(
                served: true,
                stale: $this->isStale($metadata),
                promoted: $promoted,
                sourceDriver: $class,
                targetDrivers: $targetDrivers,
                data: $data,
            );
        }

        return new Result();
    }

    #[Override]
    public function write(
        Collection $repository,
        Request $request,
        Construct|iterable $data,
        Document $document,
    ): Result {
        $written = false;

        foreach ($repository->getDrivers() as $class) {
            $driver = $this->container->make($class);

            $result = $driver->sync($repository, $request, $data, $document->meta);

            if ($result === null) {
                continue;
            }

            $written = true;
        }

        return new Result(served: $written, data: $data);
    }

    private function isStale(Metadata $metadata): bool
    {
        if ($metadata->fetchedAt === null) {
            return true;
        }

        $maxAgeDays = max(1, Typdy::config()->maxCacheAgeDays);
        $maxAgeSeconds = $maxAgeDays * 86_400;

        $ageSeconds = max(0, new DateTimeImmutable()->getTimestamp() - $metadata->fetchedAt->getTimestamp());

        return $ageSeconds > $maxAgeSeconds;
    }
}
