<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Parsers;

use Override;
use Typdy\StarterKit\Parsers\Contracts\MetaParser;
use Typdy\StarterKit\Parsers\Data\Relation;
use Typdy\StarterKit\Parsers\Data\Resource;
use Typdy\StarterKit\Parsers\Exceptions\MetaValidationException;
use Typdy\StarterKit\Parsers\Exceptions\ResourceValidationException;

use function array_key_exists;
use function array_map;
use function array_values;
use function get_object_vars;
use function gettype;
use function in_array;
use function is_array;
use function is_object;
use function is_string;
use function property_exists;

final readonly class ResourceParser implements Contracts\ResourceParser
{
    public function __construct(
        private MetaParser $metaParser,
    ) {}

    /**
     * @throws ResourceValidationException
     * @throws MetaValidationException
     */
    #[Override]
    public function parse(mixed $data): Resource|array
    {
        if (is_array($data)) {
            return array_map($this->parseResource(...), $data) |> array_values(...);
        }

        // @mago-expect analysis:mixed-argument
        return $this->parseResource($data);
    }

    /**
     * Early versions of typdy used a generic 'constructs' or 'globals' type
     * for all constructs. If we encounter such a type, we check the meta for a
     * more specific type and use that instead.
     *
     * @param array<string, mixed> $meta
     *
     * @return list{string, array<string, mixed>}
     */
    private function elevateTypeFromMeta(string $type, array $meta): array
    {
        if (
            in_array($type, ['constructs', 'globals'], strict: true)
            && array_key_exists('type', $meta)
            && is_string($meta['type'])
        ) {
            // when we encounter globals, we'll flag them for easier
            // identification later
            $meta['global'] = $type === 'globals';

            return [$meta['type'], $meta];
        }

        return [$type, $meta];
    }

    /**
     * @param object{attributes?: object, ...} $resource
     *
     * @return array<string, mixed>
     */
    private function parseAttributes(object $resource): array
    {
        if (!property_exists($resource, 'attributes')) {
            return [];
        }

        // @mago-expect analysis:mixed-argument not though
        $attributes = get_object_vars($resource->attributes);

        $normalisedAttributes = [];

        // @mago-expect analysis:mixed-assignment
        foreach ($attributes as $key => $value) {
            $normalisedAttributes[(string) $key] = $value;
        }

        return $normalisedAttributes;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws MetaValidationException
     */
    private function parseMeta(object $resource): array
    {
        if (!property_exists($resource, 'meta')) {
            return [];
        }

        return $this->metaParser->parse($resource->meta);
    }

    /**
     * @param object{
     *     data?: array<string, object>|object|null,
     *     meta?: object,
     *     ...
     * } $value
     *
     * @throws ResourceValidationException
     * @throws MetaValidationException
     */
    private function parseRelationship(object $value, string $relationship): ?Relation
    {
        if (property_exists($value, 'data')) {
            $data = null;

            if (is_array($value->data)) {
                $data = array_map(
                    /**
                     * @throws ResourceValidationException
                     * @throws MetaValidationException
                     */
                    fn (mixed $relatedResource) => $this->parseResourceIdentifier($relatedResource, $relationship),
                    $value->data,
                )
                    |> array_values(...);
            } elseif ($value->data !== null) {
                $data = $this->parseResourceIdentifier($value->data, $relationship);
            }

            return new Relation(
                $relationship,
                data: $data,
                meta: $this->parseMeta($value),
            );
        }

        return null;
    }

    /**
     * @param object{
     *     relationships?: array<string, object{
     *         data?: array<string, object>|object|null,
     *         meta?: object,
     *         ...
     *     }>,
     *     ...
     * } $resource
     *
     * @return array<string, Relation>
     *
     * @throws ResourceValidationException
     * @throws MetaValidationException
     */
    private function parseRelationships(object $resource): array
    {
        if (!property_exists($resource, 'relationships')) {
            return [];
        }

        $relationships = [];

        /**
         * @var array<string, object{
         *     data?: array<string, object>|object|null,
         *     meta?: object,
         *     ...
         * }> $resourceRelationships
         * */
        $resourceRelationships = $resource->relationships;

        foreach ($resourceRelationships as $key => $value) {
            $related = $this->parseRelationship($value, $key);

            // while valid json:api allows relationships without data, they
            // don't really serve any purpose in our context
            if ($related !== null) {
                $relationships[$key] = $related;
            }
        }

        return $relationships;
    }

    /**
     * @throws ResourceValidationException
     * @throws MetaValidationException
     */
    private function parseResource(object $resource): Resource
    {
        $validated = $this->validateResource($resource);

        $meta = $this->parseMeta($validated);

        [$type, $meta] = $this->elevateTypeFromMeta($validated->type, $meta);

        return new Resource(
            $type,
            $validated->id,
            attributes: $this->parseAttributes($validated),
            relationships: $this->parseRelationships($validated),
            meta: $meta,
        );
    }

    /**
     * @throws ResourceValidationException
     * @throws MetaValidationException
     */
    private function parseResourceIdentifier(mixed $resource, string $relationship): Resource
    {
        $validated = $this->validateResourceIdentifier($resource, 'relationships->' . $relationship);

        [$type, $meta] = $this->elevateTypeFromMeta($validated->type, $this->parseMeta($validated));

        return new Resource(
            $type,
            $validated->id,
            meta: $meta,
        );
    }

    /**
     * @return object{
     *     type: string,
     *     id: string,
     *     attributes?: object,
     *     relationships?: array<string, object{
     *         data?: array<string, object>|object|null,
     *         meta?: object,
     *         ...
     *     }>,
     *     meta?: object,
     * }
     *
     * @throws ResourceValidationException
     */
    private function validateResource(mixed $resource): object
    {
        $validated = $this->validateResourceIdentifier($resource);

        // attributes validation
        if (property_exists($validated, 'attributes')) {
            if (!is_object($validated->attributes)) {
                ResourceValidationException::invalidMemberType(
                    'attributes',
                    'object',
                    gettype($validated->attributes),
                );
            }

            foreach ([
                'type',
                'id',
                'relationships',
                'links',
            ] as $prohibitedMember) {
                if (!property_exists($validated->attributes, $prohibitedMember)) {
                    continue;
                }

                ResourceValidationException::prohibitedMember($prohibitedMember, 'attributes');
            }
        }

        // relationships validation
        if (property_exists($validated, 'relationships')) {
            if (!is_object($validated->relationships)) {
                ResourceValidationException::invalidMemberType(
                    'relationships',
                    'object',
                    gettype($validated->relationships),
                );
            }

            foreach (['type', 'id'] as $prohibitedMember) {
                if (!property_exists($validated->relationships, $prohibitedMember)) {
                    continue;
                }

                ResourceValidationException::prohibitedMember($prohibitedMember, 'relationships');
            }

            // @mago-expect analysis:mixed-assignment
            foreach (get_object_vars($validated->relationships) as $name => $relationship) {
                if (
                    property_exists($validated, 'attributes')
                    && is_object($validated->attributes)
                    && property_exists($validated->attributes, (string) $name)
                ) {
                    ResourceValidationException::conflictingMember((string) $name, 'attributes', 'relationships');
                }

                if (!is_object($relationship)) {
                    ResourceValidationException::invalidMemberType(
                        'relationships->' . $name,
                        'object',
                        gettype($relationship),
                    );
                }

                if (
                    !property_exists($relationship, 'data')
                    && !property_exists($relationship, 'links')
                    && !property_exists($relationship, 'meta')
                ) {
                    ResourceValidationException::missingRelationshipMembers('relationships->' . $name);
                }
            }
        }

        // @mago-expect analysis:mixed-return-statement
        return $resource;
    }

    /**
     * @return object{
     *     type: string,
     *     id: string,
     *     ...
     * }
     *
     * @throws ResourceValidationException
     */
    private function validateResourceIdentifier(mixed $resource, string $path = ''): object
    {
        if (!is_object($resource)) {
            ResourceValidationException::invalidResourceType(gettype($resource), $path);
        }

        if (!property_exists($resource, 'type')) {
            ResourceValidationException::missingRequiredMember('type', $path);
        }

        if (!property_exists($resource, 'id')) {
            ResourceValidationException::missingRequiredMember('id', $path);
        }

        if (!is_string($resource->type) || $resource->type === '') {
            ResourceValidationException::invalidMemberType(
                'type',
                'string',
                $resource->type === '' ? 'empty string' : gettype($resource->type),
                $path,
            );
        }

        if (!is_string($resource->id) || $resource->id === '') {
            ResourceValidationException::invalidMemberType(
                'id',
                'string',
                $resource->id === '' ? 'empty string' : gettype($resource->id),
                $path,
            );
        }

        // @mago-expect analysis:invalid-return-statement
        return $resource;
    }
}
