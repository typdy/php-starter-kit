<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Models\Concerns;

use ReflectionClass;
use ReflectionProperty;
use RuntimeException;
use Typdy\StarterKit\Models\Attributes\Relationship;
use Typdy\StarterKit\Models\Contracts\Construct;
use Typdy\StarterKit\Parsers\Data\Relation;
use Typdy\StarterKit\Parsers\Data\Resource;
use Typdy\StarterKit\Resolvers\Contracts\ResolvesModels;
use Typdy\StarterKit\Typdy;
use Typdy\StarterKit\Utils\Str;

use function array_key_exists;
use function array_keys;
use function array_map;
use function is_array;
use function property_exists;

/**
 * Add support for property-based relationships to a model. The relationship
 * must be defined as a public property and use the `Relationship` attribute.
 *
 * @api
 */
trait HasRelationships
{
    /**
     * @internal
     *
     * @var array<string, array<string, mixed>>
     */
    private array $_relationshipMetaOverrides = [];

    /**
     * @internal
     *
     * @var array<string, string>|null
     */
    private ?array $_relationships = null;

    abstract public function getProject(): string;

    abstract public function getTeam(): string;

    /**
     * @return array<string, mixed>
     */
    final public function getRelationshipMeta(string $name): array
    {
        // @mago-expect analysis:less-specific-return-statement
        return [
            // @mago-expect analysis:non-existent-property
            // @mago-expect analysis:invalid-array-element
            ...($this->resource?->relationships[$name]->meta ?? []),
            ...($this->_relationshipMetaOverrides[$name] ?? []),
        ];
    }

    /**
     * @return array<string, string>
     */
    final public function getRelationships(): array
    {
        if ($this->_relationships === null) {
            $relationships = [];

            $reflection = new ReflectionClass($this);

            foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
                $attributes = $property->getAttributes(Relationship::class);

                if ($attributes === []) {
                    continue;
                }

                $attribute = $attributes[0]->newInstance();

                $relationships[$attribute->alias ?? $property->getName()] = $property->getName();
            }

            $this->_relationships = $relationships;
        }

        $relationships = $this->_relationships;

        /** @var array<string, mixed> $resourceRelationships */
        // @mago-expect analysis:non-existent-property
        $resourceRelationships = $this->resource->relationships ?? [];

        foreach (array_keys($resourceRelationships) as $name) {
            $property = Str::camel($name);

            if (!array_key_exists($name, $relationships) && property_exists($this, $property)) {
                $relationships[$name] = $property;
            }
        }

        return $relationships;
    }

    /**
     * @param array<string, mixed> $meta
     */
    final public function setRelationshipMeta(string $name, array $meta): void
    {
        $this->_relationshipMetaOverrides[$name] = $meta;
    }

    final protected function clearRelationshipMeta(): void
    {
        $this->_relationshipMetaOverrides = [];
    }

    /**
     * @return Construct|list<Construct>|null
     */
    final protected function hydrateRelated(Relation $relation): Construct|array|null
    {
        if ($relation->data === null) {
            return null;
        }

        if (is_array($relation->data)) {
            return array_map(
                fn (Resource $resource) => $this->hydrateRelatedResource(
                    $this->resolveRelatedResource($resource, $relation->included),
                ),
                $relation->data,
            );
        }

        return $this->hydrateRelatedResource(
            $this->resolveRelatedResource($relation->data, $relation->included),
        );
    }

    final protected function hydrateRelatedResource(Resource $resource): Construct
    {
        $model = Typdy::container(ResolvesModels::class)
            ->resolveOne(
                $this->getTeam(),
                $this->getProject(),
                $resource->type,
            )
            ?->hydrateFromResource($resource);

        if ($model === null) {
            throw new RuntimeException("No model found for related construct of blueprint '{$resource->type}'.");
        }

        return $model;
    }

    /**
     * @param list<Resource> $included
     */
    final protected function resolveRelatedResource(Resource $resource, array $included): Resource
    {
        foreach ($included as $includedResource) {
            if ($includedResource->type === $resource->type && $includedResource->id === $resource->id) {
                return $includedResource;
            }
        }

        return $resource;
    }
}
