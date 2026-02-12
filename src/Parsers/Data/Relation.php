<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Parsers\Data;

use Typdy\StarterKit\Helpers\ArrayMapper;
use Typdy\StarterKit\Utils\Arr;

use function array_key_exists;
use function array_map;
use function array_values;
use function in_array;
use function is_array;

final class Relation
{
    /**
     * @var list<Resource>
     */
    public private(set) array $included = [];

    /**
     * @param Resource|list<Resource>|null $data
     *
     * @param array<string, mixed> $meta
     */
    public function __construct(
        public readonly string $name,
        public readonly Resource|array|null $data = null,
        public readonly array $meta = [],
    ) {}

    /**
     * @param array{
     *     name: string,
     *     data?: array{
     *         type: string,
     *         id: string,
     *         attributes?: array<string, mixed>,
     *         meta?: array<string, mixed>,
     *         relationships?: array<string, array<string, mixed>>,
     *     }|array<array-key, array{
     *         type: string,
     *         id: string,
     *         attributes?: array<string, mixed>,
     *         meta?: array<string, mixed>,
     *         relationships?: array<string, array<string, mixed>>,
     *     }>|null,
     *     meta?: array<string, mixed>,
     *     included?: array<array-key, array{
     *         type: string,
     *         id: string,
     *         attributes?: array<string, mixed>,
     *         meta?: array<string, mixed>,
     *         relationships?: array<string, array<string, mixed>>,
     *     }>,
     * } $array
     */
    public static function fromArray(array $array): self
    {
        $data = null;

        if (array_key_exists('data', $array) && is_array($array['data'])) {
            if (array_key_exists('type', $array['data'])) {
                /**
                 * @var array{
                 *     type: string,
                 *     id: string,
                 *     attributes?: array<string, mixed>,
                 *     meta?: array<string, mixed>,
                 *     relationships?: array<string, array<string, mixed>>,
                 * } $resource
                 */
                $resource = $array['data'];

                $data = Resource::fromArray($resource);
            } else {
                /**
                 * @var array<array-key, array{
                 *     type: string,
                 *     id: string,
                 *     attributes?: array<string, mixed>,
                 *     meta?: array<string, mixed>,
                 *     relationships?: array<string, array<string, mixed>>,
                 * }> $resources
                 */
                $resources = $array['data'];

                $data = array_map(Resource::fromArray(...), $resources) |> array_values(...);
            }
        }

        $relation = new self(
            name: $array['name'],
            data: $data,
            meta: $array['meta'] ?? [],
        );

        if ($data === null) {
            return $relation;
        }

        $included = array_map(Resource::fromArray(...), $array['included'] ?? []) |> array_values(...);

        $relation->associateIncluded($included);

        return $relation;
    }

    /**
     * @param list<Resource> $included
     */
    public function associateIncluded(array $included): void
    {
        if ($this->data === null) {
            return;
        }

        /** @var list<Resource> $data */
        $data = Arr::wrap($this->data);

        $knownTypes = array_map(
            static fn (Resource $resource): string => $resource->type,
            $data,
        );

        $knownIds = array_map(
            static fn (Resource $resource): string => $resource->id,
            $data,
        );

        foreach ($included as $resource) {
            if (
                !(
                    in_array($resource->type, $knownTypes, strict: true)
                    && in_array($resource->id, $knownIds, strict: true)
                )
            ) {
                continue;
            }

            $this->included[] = $resource;
        }
    }

    /**
     * @return array<array-key, mixed>
     */
    public function toArray(): array
    {
        return new ArrayMapper($this)->toArray();
    }
}
