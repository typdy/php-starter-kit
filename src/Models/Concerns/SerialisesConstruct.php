<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Models\Concerns;

use InvalidArgumentException;
use JsonException;
use RuntimeException;
use Typdy\StarterKit\Helpers\ArrayMapper;
use Typdy\StarterKit\Helpers\JsonMapper;
use Typdy\StarterKit\Models\Contracts\Aliasable;
use Typdy\StarterKit\Models\Contracts\Construct;
use Typdy\StarterKit\Models\Contracts\Relatable;
use Typdy\StarterKit\Typdy;

use function array_map;
use function array_values;
use function is_array;
use function json_encode;
use function property_exists;

/**
 * @api
 */
trait SerialisesConstruct
{
    /**
     * @param array<string, mixed> $fields
     *
     * @return array<string, mixed>
     */
    abstract public function camelFields(array $fields): array;

    abstract public function getBlueprint(): string;

    abstract public function isGlobal(): bool;

    /**
     * @param array<string, mixed> $fields
     *
     * @return array<string, mixed>
     */
    abstract public function uncamelFields(array $fields): array;

    final public function getSyncBody(): ?string
    {
        $relationships = [];

        $related = $this->maybeGetRelationships();

        foreach ($related as $relationKey => $relationProp) {
            if (!property_exists($this, $relationProp)) {
                continue;
            }

            $relationships[$relationKey] = [
                // @mago-expect analysis:string-member-selector
                'data' => $this->normaliseRelationshipData($this->$relationProp),
                'meta' => $this->maybeGetRelationshipMeta($relationKey),
            ];
        }

        /** @var array<string, mixed> $attributes */
        $attributes = new ArrayMapper($this, without: [
            'id',
            'meta',
            'resource',
            ...array_values($related),
        ])->toArray();

        $type = $this->getBlueprint();
        $id = null;
        $meta = null;

        if (Typdy::config()->legacyTypes) {
            $type = $this->isGlobal() ? 'globals' : 'constructs';
        }

        if (property_exists($this, 'id')) {
            $id = (string) $this->id;
        }

        if (property_exists($this, 'meta') && is_array($this->meta)) {
            $meta = (object) $this->meta;
        }

        $body = [
            'data' => [
                'type' => $type,
                'id' => $id,
                'attributes' => (object) ($attributes |> $this->uncamelFields(...) |> $this->maybeAliasFields(...)),
                'relationships' => (object) (
                    $relationships |> $this->uncamelFields(...) |> $this->maybeAliasFields(...)
                ),
                'meta' => $meta,
            ],
        ];

        return json_encode($body, JSON_THROW_ON_ERROR);
    }

    /**
     * @throws JsonException
     */
    public function jsonSerialize(): string
    {
        return $this->toJson();
    }

    /**
     * @return array<array-key, mixed>
     */
    public function toArray(): array
    {
        return new ArrayMapper($this, includeNonPublic: ['id'])->toArray();
    }

    /**
     * @throws JsonException
     */
    public function toJson(): string
    {
        return new JsonMapper($this->toArray())->toJson();
    }

    /**
     * @param array<string, mixed> $fields
     *
     * @return array<string, mixed>
     */
    final protected function maybeAliasFields(array $fields): array
    {
        if ($this instanceof Aliasable) {
            return $this->aliasFields($fields);
        }

        return $fields;
    }

    /**
     * @return array<string, mixed>
     */
    final protected function maybeGetRelationshipMeta(string $relationKey): array
    {
        if ($this instanceof Relatable) {
            return $this->getRelationshipMeta($relationKey);
        }

        return [];
    }

    /**
     * @return array<string, string>
     */
    final protected function maybeGetRelationships(): array
    {
        if ($this instanceof Relatable) {
            return $this->getRelationships();
        }

        return [];
    }

    /**
     * @return array<string, string>|list<array<string, string>>|null
     */
    final protected function normaliseRelationshipData(mixed $relationship): ?array
    {
        if ($relationship === null) {
            return null;
        }

        if (is_array($relationship)) {
            return array_values(
                array_map(function (mixed $construct): array {
                    if (!$construct instanceof Construct) {
                        throw new InvalidArgumentException('Relationship arrays must contain construct instances.');
                    }

                    $relationship = $this->relationToRelationship($construct);

                    if ($relationship === null) {
                        throw new RuntimeException('Related constructs must be synced before they can be linked.');
                    }

                    return $relationship;
                }, $relationship),
            );
        }

        if (!$relationship instanceof Construct) {
            throw new InvalidArgumentException(
                'Relationship values must be a construct, an array of constructs, or null.',
            );
        }

        $normalised = $this->relationToRelationship($relationship);

        if ($normalised === null) {
            throw new RuntimeException('Related constructs must be synced before they can be linked.');
        }

        return $normalised;
    }

    /**
     * @return array<string, string>|null
     */
    final protected function relationToRelationship(Construct $construct): ?array
    {
        if ($construct->id === null) {
            return null;
        }

        return [
            'id' => (string) $construct->id,
            'type' => $construct->getBlueprint(),
        ];
    }
}
