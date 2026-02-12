<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Storage\Helpers;

use Typdy\StarterKit\Repositories\Data\Request;

use function array_key_exists;
use function str_starts_with;

final readonly class ContravariantQueryDiscovery
{
    /**
     * The idea is to create broader versions of the request that are both
     * guaranteed to be compatible with the original request, and likely to
     * actually be fetched at some point in the future.
     *
     * Currently, we create two variants:
     * - simplified request: all filters, sorts, and pagination removed
     * - no includes request: simplified + includes removed
     *
     * @return list{?Request, ?Request}
     */
    public function discover(Request $request): array
    {
        $query = $request->query;

        $simplifiedRequest = null;
        $noIncludesRequest = null;

        // sparse fields will never be compatible
        if (!array_key_exists('fields', $query)) {
            $simplified = $this->makeSimplifiedQuery($query);
            $noIncludes = $this->makeNoIncludesQuery($query);

            if ($simplified !== null) {
                $simplifiedRequest = $request->cloneWithQuery($simplified);
            }

            if ($noIncludes !== null) {
                $noIncludesRequest = $request->cloneWithQuery($noIncludes);
            }
        }

        return [$simplifiedRequest, $noIncludesRequest];
    }

    /**
     * @param array<string, mixed> $query
     *
     * @return array<string, mixed>|null
     */
    private function makeNoIncludesQuery(array $query): ?array
    {
        if (!array_key_exists('parameters', $query) || $query['parameters'] === []) {
            return null;
        }

        /** @var array<string, mixed> $parameters */
        $parameters = $query['parameters'] ?? [];

        if (!array_key_exists('include', $parameters) || $parameters['include'] === null) {
            return null;
        }

        $query['parameters'] = $this->widenQueryParams($parameters);

        unset($query['parameters']['include']);

        return $query;
    }

    /**
     * @param array<string, mixed> $query
     *
     * @return array<string, mixed>|null
     */
    private function makeSimplifiedQuery(array $query): ?array
    {
        if (!array_key_exists('parameters', $query) || $query['parameters'] === []) {
            return null;
        }

        /** @var array<string, mixed> $parameters */
        $parameters = $query['parameters'] ?? [];

        $query['parameters'] = $this->widenQueryParams($parameters);

        return $query;
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function widenQueryParams(array $params): array
    {
        unset($params['all']);
        unset($params['sort']);

        // @mago-expect analysis:mixed-assignment
        foreach ($params as $key => $value) {
            if (str_starts_with($key, 'filter[')) {
                unset($params[$key]);
            }

            if (str_starts_with($key, 'page[')) {
                unset($params[$key]);
            }
        }

        return $params;
    }
}
