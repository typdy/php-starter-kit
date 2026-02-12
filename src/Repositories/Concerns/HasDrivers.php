<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Repositories\Concerns;

use Psr\Http\Message\StreamInterface;
use RuntimeException;
use Typdy\StarterKit\Api\Enums\HttpMethod;
use Typdy\StarterKit\Models\Contracts\Construct;
use Typdy\StarterKit\Parsers\Data\Document;
use Typdy\StarterKit\Repositories\Data\Request;
use Typdy\StarterKit\Storage\Contracts\DatabaseDriver;
use Typdy\StarterKit\Storage\Contracts\Driver;
use Typdy\StarterKit\Storage\Helpers\DocumentTransformer;
use Typdy\StarterKit\Sync\Contracts\DriverPipeline;
use Typdy\StarterKit\Sync\Data\Metadata;
use Typdy\StarterKit\Typdy;

use function array_values;
use function count;
use function is_subclass_of;

/**
 * Coordinates the storage drivers that will be used to fulfill requests to this
 * repository.
 *
 * @api
 *
 * @template TModel of Construct
 *
 * @mago-expect analysis:possibly-invalid-argument $this might not be a Collection
 */
trait HasDrivers
{
    /**
     * @api
     *
     * This driver stack will be used to attempt to fulfill requests to this
     * repository. If there are no driver that can fulfill the request, an API
     * call will be made to typdy. The result of this API call will then be
     * provided to the storage drivers to be persisted.
     *
     * @var list<class-string<Driver>>
     */
    public array $drivers = [];

    /**
     * @internal
     *
     * @var list<class-string<Driver>>
     */
    private array $_validDrivers = [];

    /**
     * @return DriverPipeline<TModel>
     */
    public function getDriverPipeline(): DriverPipeline
    {
        // @mago-expect analysis:invalid-return-statement
        return Typdy::container(DriverPipeline::class);
    }

    /**
     * @return list<class-string<Driver>>
     */
    final public function getDrivers(): array
    {
        if (count($this->_validDrivers) === 0) {
            $this->validateDrivers();
        }

        return $this->_validDrivers;
    }

    final public function hasDatabaseDriver(): bool
    {
        foreach ($this->getDrivers() as $driverClass) {
            if (is_subclass_of($driverClass, DatabaseDriver::class)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $parameters
     * @param string|resource|StreamInterface|null $body
     * @param array<string, mixed> $headers
     */
    abstract protected function request(
        int|string|null $id = null,
        HttpMethod $method = HttpMethod::GET,
        mixed $body = null,
        array $parameters = [],
        array $headers = [],
    ): Document;

    final protected function deleteFromDrivers(Request $request): void
    {
        $this->getDriverPipeline()->delete($this, $request);
    }

    /**
     * @return list<Request>
     */
    final protected function getReplayableRequests(?int $constructId): array
    {
        return $this->getDriverPipeline()->getReplayableRequests($this, $constructId);
    }

    /**
     * @return list{TModel|iterable<int, TModel>|null, Metadata}
     *
     * @mago-ignore analysis:all
     */
    final protected function readFromDrivers(string $method, Request $request): array
    {
        $pipeline = $this->getDriverPipeline();

        if ($this->mapi && !$this->mapiFromStorage) {
            return [
                null,
                $pipeline->getMetadata($this, $request),
            ];
        }

        return [
            $pipeline->read($this, $request, $method)->data,
            $pipeline->getMetadata($this, $request),
        ];
    }

    /**
     * @param string|resource|StreamInterface|null $body
     *
     * @return list{Document|TModel|iterable<int, TModel>|null, Metadata}
     *
     * @mago-ignore analysis:all
     *
     * TODO(@piranhageorge): mago hates this, refactor to make our overlord
     *  happy, and the one above ^^^^
     */
    final protected function syncTypdy(
        Request $request,
        HttpMethod $method = HttpMethod::GET,
        mixed $body = null,
        bool $forcePersist = false,
    ): array {
        $pipeline = $this->getDriverPipeline();

        $document = $this->request(
            id: $request->id ?? $request->identifier ?? null,
            method: $method,
            body: $body,
            parameters: $request->query['parameters'] ?? [],
            headers: $request->query['headers'] ?? [],
        );

        if ($document->failed()) {
            return [
                $document,
                $pipeline->getMetadata($this, $request, $document),
            ];
        }

        $transformed = null;

        foreach ($this->getDrivers() as $class) {
            /** @var Driver $driver */
            $driver = Typdy::container($class);

            /** @var DocumentTransformer $transformer */
            $transformer = Typdy::container(DocumentTransformer::class);

            $data = $transformer->transform($this, $document);

            if ($data === null) {
                continue;
            }

            $transformed = $data;

            break;
        }

        if ($transformed === null) {
            return [
                null,
                $pipeline->getMetadata($this, $request, $document),
            ];
        }

        // typically fetched data is only synced to drivers in delivery mode,
        // but this can be overridden using mapiToStorage, or forcing
        // persistence
        if ($forcePersist || !$this->mapi || $this->mapiToStorage) {
            return [
                $pipeline->write($this, $request, $data, $document)->data ?? $transformed,
                $pipeline->getMetadata($this, $request, $document),
            ];
        }

        return [
            $transformed,
            $pipeline->getMetadata($this, $request, $document),
        ];
    }

    final protected function validateDrivers(): void
    {
        $drivers = $this->drivers;

        if (count($drivers) === 0) {
            $drivers = array_values(Typdy::config()->drivers);
        }

        if (count($drivers) === 0) {
            throw new RuntimeException('At least one storage driver must be defined for this repository.');
        }

        $sawDatabaseDriver = false;

        foreach ($drivers as $class) {
            if (!is_subclass_of($class, Driver::class)) {
                throw new RuntimeException("Storage driver '{$class}' must implement the Driver interface.");
            }

            if (is_subclass_of($class, DatabaseDriver::class)) {
                $sawDatabaseDriver = true;

                continue;
            }

            if (is_subclass_of($class, Driver::class) && $sawDatabaseDriver) {
                throw new RuntimeException('Database drivers must be listed last in the driver stack.');
            }
        }

        $this->_validDrivers = $drivers;
    }
}
