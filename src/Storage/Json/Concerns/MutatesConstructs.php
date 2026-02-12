<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Storage\Json\Concerns;

use Typdy\StarterKit\Models\Contracts\Construct;
use Typdy\StarterKit\Parsers\Data\Resource;

use function is_array;

/**
 * @api
 */
trait MutatesConstructs
{
    /**
     * @param array<array-key, mixed> $payload
     */
    private function isCompleteCollectionPayload(array $payload): bool
    {
        // @mago-expect analysis:mixed-assignment
        $all = $payload['__request']['query']['parameters']['all'] ?? null;

        return $all === true || $all === 1 || $all === '1' || $all === 'true';
    }

    /**
     * @param-out array<array-key, mixed> $payload
     *
     * @return array{bool, bool}
     */
    private function mutatePayloadByContext(array &$payload, Construct $model): array
    {
        $mutated = false;
        $invalidate = false;

        if ($model->resource === null) {
            return [$mutated, $invalidate];
        }

        $mutated = $this->mutatePayloadConstruct($payload, $model->resource);

        if ($mutated) {
            return [$mutated, $invalidate];
        }

        [$mutated, $invalidate] = $this->mutatePayloadConstructs($payload, $model->resource);

        $mutated = $this->mutatePayloadIncluded($payload, $model->resource) || $mutated;

        return [$mutated, $invalidate];
    }

    /**
     * @param-out array<array-key, mixed> $payload
     *
     * @return bool
     */
    private function mutatePayloadConstruct(&$payload, Resource $resource): bool
    {
        $resource = $resource->toArray();

        if (is_array($payload['construct'] ?? null) && $this->resourceMatches($payload['construct'], $resource)) {
            $payload['construct'] = $resource;

            return true;
        }

        return false;
    }

    /**
     * @param-out array<array-key, mixed> $payload
     *
     * @return array{bool, bool}
     */
    private function mutatePayloadConstructs(&$payload, Resource $resource): array
    {
        $mutated = false;
        $invalidate = false;
        $matchesConstructs = false;

        if (!is_array($payload['constructs'] ?? null)) {
            return [$mutated, $invalidate];
        }

        $resource = $resource->toArray();

        // @mago-expect analysis:mixed-assignment
        foreach ($payload['constructs'] as $index => $construct) {
            if (!is_array($construct) || !$this->resourceMatches($construct, $resource)) {
                continue;
            }

            $matchesConstructs = true;

            if (!$this->isCompleteCollectionPayload($payload)) {
                return [$mutated, $invalidate = true];
            }

            $payload['constructs'][$index] = $resource;
            $mutated = true;
        }

        return [$mutated, $invalidate];
    }

    /**
     * @param-out array<array-key, mixed> $payload
     *
     * @return bool
     */
    private function mutatePayloadIncluded(&$payload, Resource $resource): bool
    {
        if (!is_array($payload['included'] ?? null)) {
            return false;
        }

        $resource = $resource->toArray();

        // @mago-expect analysis:mixed-assignment
        foreach ($payload['included'] as $index => $included) {
            if (!is_array($included) || !$this->resourceMatches($included, $resource)) {
                continue;
            }

            $payload['included'][$index] = $resource;

            return true;
        }

        return false;
    }

    /**
     * @param array<array-key, mixed> $candidate
     * @param array<array-key, mixed> $resource
     */
    private function resourceMatches(array $candidate, array $resource): bool
    {
        $candidateId = (string) ($candidate['id'] ?? null);
        $resourceId = (string) ($resource['id'] ?? null);

        $candidateType = (string) ($candidate['type'] ?? null);
        $resourceType = (string) ($resource['type'] ?? null);

        return $candidateId === $resourceId && $candidateType === $resourceType;
    }
}
