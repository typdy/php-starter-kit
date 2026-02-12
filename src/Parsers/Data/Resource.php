<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Parsers\Data;

use Typdy\StarterKit\Helpers\ArrayMapper;

final readonly class Resource
{
    /**
     * @param array<string, mixed> $attributes
     * @param array<string, mixed> $meta
     * @param array<string, Relation> $relationships
     */
    public function __construct(
        public string $type,
        public string $id,
        public array $attributes = [],
        public array $meta = [],
        public array $relationships = [],
    ) {}

    /**
     * @param array{
     *     type: string,
     *     id: string,
     *     attributes?: array<string, mixed>,
     *     meta?: array<string, mixed>,
     *     relationships?: array<string, array<string, mixed>>,
     * } $array
     */
    public static function fromArray(array $array): self
    {
        return new self(
            type: $array['type'],
            id: $array['id'],
            attributes: $array['attributes'] ?? [],
            meta: $array['meta'] ?? [],
            // @mago-expect analysis:invalid-argument relation array shape does not match
            relationships: array_map(Relation::fromArray(...), $array['relationships'] ?? []),
        );
    }

    /**
     * @return array<array-key, mixed>
     */
    public function toArray(): array
    {
        return new ArrayMapper($this)->toArray();
    }
}
