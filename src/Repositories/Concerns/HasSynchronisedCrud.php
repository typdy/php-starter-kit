<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Repositories\Concerns;

use JsonException;
use LogicException;
use Psr\Http\Client\ClientExceptionInterface;
use RuntimeException;
use Typdy\StarterKit\Api\Enums\HttpMethod;
use Typdy\StarterKit\Data\Paginated;
use Typdy\StarterKit\Models\Contracts\Construct;
use Typdy\StarterKit\Parsers\Data\Document;
use Typdy\StarterKit\Parsers\Exceptions\DecodingException;
use Typdy\StarterKit\Parsers\Exceptions\ResponseParserException;
use Typdy\StarterKit\Repositories\Data\Request;
use Typdy\StarterKit\Repositories\Exceptions\HttpNotFoundException;
use Typdy\StarterKit\Sync\Data\Metadata;
use Typdy\StarterKit\Typdy;

use function ceil;
use function count;
use function is_bool;
use function is_iterable;
use function max;

/**
 * Performs CRUD operations, coordinating between the API and the local drivers.
 *
 * @api
 *
 * @template TModel of Construct
 */
trait HasSynchronisedCrud
{
    /**
     * @use HasDrivers<TModel>
     */
    use HasDrivers;

    use HasMacros;
    use SendsApiRequests;
    use SwitchesEndpoints;

    /**
     * @param array<string, mixed> $request
     *
     * @return iterable<int, TModel>|Document
     */
    final public function all(array $request = []): iterable|Document
    {
        $request = $this->prepareRequest($request, defaults: [
            'parameters' => [
                'all' => true,
            ],
        ]);

        [$models, $metadata] = $this->readFromDrivers('all', $request);

        if ($models === null && !$this->hasDatabaseDriver()) {
            [$models, $metadata] = $this->syncTypdy($request);
        }

        $this->resetMapi();

        /** @var iterable<int,TModel>|Document $models */
        $models ??= Typdy::collect();

        if ($models instanceof Construct) {
            throw new LogicException(
                'Driver returned a single model instead of an iterable collection for the paginate method.',
            );
        }

        return $models;
    }

    /**
     * @param array<string, mixed> $request
     *
     * @throws JsonException
     * @throws ClientExceptionInterface
     * @throws ResponseParserException
     * @throws DecodingException
     * @throws HttpNotFoundException
     *
     * @mago-expect analysis:mixed-argument
     */
    final public function delete(int|string $id, array $request = []): void
    {
        $this->mapi();

        $request = $this->prepareRequest($request, id: $id);

        // TODO(@piranhageorge): add retry for when typdy is down
        $document = $this->request(
            id: $id,
            parameters: $request->query['parameters'] ?? [],
            method: HttpMethod::DELETE,
            headers: $request->query['headers'] ?? [],
        );

        if ($document->failed()) {
            $this->resetMapi();

            return;
        }

        $this->deleteFromDrivers($request);

        $this->resetMapi();
    }

    /**
     * @param array<string, mixed> $request
     *
     * @return TModel|Document|null
     */
    final public function find(int|string $id, array $request = []): Construct|Document|null
    {
        $request = $this->prepareRequest($request, id: $id);

        [$model, $metadata] = $this->readFromDrivers('find', $request);

        if ($model === null && !$this->hasDatabaseDriver()) {
            [$model, $metadata] = $this->syncTypdy($request);
        }

        if ($model instanceof Document && $model->wasNotFound()) {
            return null;
        }

        $this->resetMapi();

        if (is_iterable($model)) {
            throw new LogicException(
                'Driver returned an iterable collection instead of a single model for the find method.',
            );
        }

        return $model;
    }

    /**
     * @param array<string, mixed> $request
     *
     * @return TModel|Document
     *
     * @throws HttpNotFoundException
     */
    final public function findOrFail(int|string $id, array $request = []): Construct|Document
    {
        $model = $this->find($id, $request);

        if ($model === null) {
            $this->throwNotFoundException();
        }

        return $model;
    }

    /**
     * @param array<string, mixed> $request
     *
     * @return iterable<int, TModel>|Document
     */
    final public function paginate(int $perPage = 15, array $request = []): iterable|Document|null
    {
        $request = $this->prepareRequest($request, defaults: [
            'parameters' => [
                'all' => false,
                'page[number]' => $this->resolvePageNumber(),
                'page[size]' => $perPage,
            ],
        ]);

        [$models, $metadata] = $this->readFromDrivers('all', $request);

        if ($models === null && !$this->hasDatabaseDriver()) {
            [$models, $metadata] = $this->syncTypdy($request);
        }

        $this->resetMapi();

        /** @var iterable<int,TModel>|Document $models */
        $models ??= Typdy::collect();

        if ($models instanceof Construct) {
            throw new LogicException(
                'Driver returned a single model instead of an iterable collection for the paginate method.',
            );
        }

        if (!is_iterable($models)) {
            return $models;
        }

        return Typdy::paginate($this->buildPaginated($models, $request, $metadata), $request);
    }

    /**
     * @param array<string, mixed> $options
     */
    final public function replay(?int $constructId = null, array $options = []): int
    {
        $requests = $this->replayableRequests($constructId);

        $replayed = 0;
        $forcePersist = is_bool($options['forcePersist'] ?? null) ? $options['forcePersist'] : true;

        foreach ($requests as $request) {
            [$result] = $this->syncTypdy($request, forcePersist: $forcePersist);

            if ($result instanceof Document && $result->failed()) {
                continue;
            }

            $replayed++;
        }

        return $replayed;
    }

    /**
     * @return list<Request>
     */
    final public function replayableRequests(?int $constructId = null): array
    {
        return $this->getReplayableRequests($constructId);
    }

    /**
     * @param TModel $model
     * @param array<string, mixed> $request
     *
     * @return TModel|Document
     */
    final public function save(Construct $model, array $request = []): Construct|Document
    {
        $this->mapi();

        $id = $model->isNew() ? null : $model->id;

        $request = $this->prepareRequest($request, id: $id, defaults: [
            'parameters' => $model->getSyncParameters(),
            'headers' => $model->getSyncHeaders(),
        ]);

        // TODO(@piranhageorge): add retry for when typdy is down
        [$result, $metadata] = $this->syncTypdy(
            $request,
            method: $model->isNew()
                ? HttpMethod::POST
                : HttpMethod::PATCH,
            body: $model->getSyncBody(),
            forcePersist: true,
        );

        $this->resetMapi();

        if (!($result instanceof Construct || $result instanceof Document)) {
            throw new RuntimeException('Save operation returned an unexpected result type.');
        }

        return $result;
    }

    /**
     * @param iterable<int, TModel> $models
     *
     * @return Paginated<TModel>
     */
    private function buildPaginated(iterable $models, Request $request, Metadata $metadata): Paginated
    {
        if ($models instanceof Paginated) {
            // @mago-expect analysis:never-return Paginated is iterable and
            //  could be returned from a Driver
            return $models;
        }

        /** @var list<TModel> $items */
        $items = Typdy::decollect($models);

        /** @var array<string, mixed> $parameters */
        $parameters = $request->query['parameters'] ?? [];

        $total = (int) max(0, $metadata->raw['total'] ?? count($items));
        $perPage = (int) max(1, $metadata->raw['perPage'] ?? $parameters['page[size]'] ?? 15);
        $currentPage = (int) max(1, $metadata->raw['currentPage'] ?? $parameters['page[number]'] ?? 1);
        $lastPage = (int) ($metadata->raw['lastPage'] ?? max(1, ceil($total / $perPage)));

        return new Paginated(
            items: $items,
            total: $total,
            perPage: $perPage,
            currentPage: $currentPage,
            lastPage: $lastPage,
        );
    }
}
