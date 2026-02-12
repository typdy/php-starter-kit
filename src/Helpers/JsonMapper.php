<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Helpers;

use function json_encode;

use const JSON_THROW_ON_ERROR;

final class JsonMapper
{
    private ArrayMapper $arrayMapper;

    /**
     * @param list<string> $without
     * @param list<string> $includeNonPublic
     */
    public function __construct(
        mixed $from,
        array $without = [],
        array $includeNonPublic = [],
        int $maxDepth = 512,
    ) {
        $this->arrayMapper = new ArrayMapper(
            $from,
            without: $without,
            includeNonPublic: $includeNonPublic,
            maxDepth: $maxDepth,
        );
    }

    public function toJson(): string
    {
        return json_encode($this->arrayMapper->toArray(), JSON_THROW_ON_ERROR);
    }
}
