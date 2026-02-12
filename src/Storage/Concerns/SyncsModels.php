<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Storage\Concerns;

use Generator;
use Typdy\StarterKit\Models\Contracts\Construct;
use Typdy\StarterKit\Repositories\Contracts\Collection;
use Typdy\StarterKit\Repositories\Data\Request;
use Typdy\StarterKit\Storage\Helpers\ContravariantQueryDiscovery;
use Typdy\StarterKit\Typdy;
use Typdy\StarterKit\Utils\Signature;

use function array_filter;
use function array_values;
use function is_iterable;
use function iterator_to_array;

/**
 * @api
 */
trait SyncsModels
{
    /**
     * @param Construct|iterable<int, Construct> $data
     * @param array<array-key, mixed> $meta
     */
    public function sync(
        Collection $repository,
        Request $request,
        Construct|iterable $data,
        array $meta = [],
    ): Construct|iterable|null {
        if ($data instanceof Generator) {
            $data = iterator_to_array($data);
        }

        if (is_iterable($data)) {
            // @mago-expect analysis:less-specific-argument false positive,
            //  generator conversion causes it to appear less specific
            return $this->syncMany($repository, $request, $data, $meta);
        }

        return $this->syncOne($repository, $request, $data, $meta);
    }

    /**
     * @param iterable<int, Construct> $models
     * @param array<array-key, mixed> $meta
     */
    abstract protected function writeCollectionToStorage(
        Collection $repository,
        Request $request,
        iterable $models,
        array $meta,
    ): void;

    /**
     * @param array<array-key, mixed> $meta
     */
    abstract protected function writeConstructToStorage(
        Collection $repository,
        Request $request,
        Construct $model,
        array $meta,
    ): void;

    /**
     * @return list<Request>
     */
    protected function getBroaderRequestVariants(Request $request): array
    {
        return new ContravariantQueryDiscovery()->discover($request) |> array_filter(...) |> array_values(...);
    }

    /**
     * @param iterable<int, Construct> $models
     * @param array<array-key, mixed> $meta
     *
     * @return iterable<int, Construct>
     */
    protected function syncMany(
        Collection $repository,
        Request $request,
        iterable $models,
        array $meta,
    ): iterable {
        foreach ($models as $model) {
            Signature::validate($repository, $model);
        }

        foreach ($models as $model) {
            $this->writeConstructToStorage($repository, $request, $model, $meta);
        }

        $broaderRequests = $this->getBroaderRequestVariants($request);

        foreach ($broaderRequests as $broaderRequest) {
            foreach ($models as $model) {
                $this->writeConstructToStorage($repository, $broaderRequest, $model, $meta);
            }
        }

        // write the collection last to ensure it is not invalidated by the individual wtires
        $this->writeCollectionToStorage($repository, $request, $models, $meta);

        return Typdy::collect($models);
    }

    /**
     * @param array<array-key, mixed> $meta
     */
    protected function syncOne(
        Collection $repository,
        Request $request,
        Construct $model,
        array $meta,
    ): Construct {
        Signature::validate($repository, $model);

        $this->writeConstructToStorage($repository, $request, $model, $meta);

        $broaderRequests = $this->getBroaderRequestVariants($request);

        foreach ($broaderRequests as $broaderRequest) {
            $this->writeConstructToStorage($repository, $broaderRequest, $model, $meta);
        }

        return $model;
    }
}
