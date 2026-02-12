<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Storage\Helpers;

use InvalidArgumentException;
use RuntimeException;
use Typdy\StarterKit\Models\Contracts\Construct;
use Typdy\StarterKit\Parsers\Data\Document;
use Typdy\StarterKit\Parsers\Data\Resource;
use Typdy\StarterKit\Repositories\Contracts\Collection;
use Typdy\StarterKit\Resolvers\Contracts\ResolvesModels;
use Typdy\StarterKit\Typdy;
use Typdy\StarterKit\Utils\Signature;

use function array_map;
use function array_values;
use function is_array;

final readonly class DocumentTransformer
{
    public function __construct(
        private ResolvesModels $resolver,
    ) {}

    public function makeModel(Collection $repository, Resource $resource): Construct
    {
        $model = $this->resolver->resolveOne(
            $repository->getTeam(),
            $repository->getProject(),
            $resource->type,
        );

        if ($model === null) {
            throw new RuntimeException("No construct model found for blueprint '{$resource->type}'.");
        }

        $model->hydrateFromResource($resource);

        return $model;
    }

    /**
     * @return Construct|iterable<int, Construct>|null
     */
    public function transform(Collection $repository, Document $document): Construct|iterable|null
    {
        if ($document->data instanceof Resource) {
            $this->validateResourceType($repository, $document->data);

            $model = $this->makeModel($repository, $document->data);

            Signature::validate($repository, $model);

            return $model;
        }

        if (is_array($document->data)) {
            return Typdy::collect(
                array_map(
                    function (Resource $resource) use ($repository): Construct {
                        $this->validateResourceType($repository, $resource);

                        $model = $this->makeModel($repository, $resource);

                        Signature::validate($repository, $model);

                        return $model;
                    },
                    $document->data,
                )
                    |> array_values(...),
            );
        }

        return null;
    }

    private function validateResourceType(Collection $repository, Resource $resource): void
    {
        $expectedType = $repository->getBlueprint();

        if ($resource->type !== $expectedType) {
            throw new InvalidArgumentException(
                "Resource type '{$resource->type}' does not match expected type '{$expectedType}'.",
            );
        }
    }
}
