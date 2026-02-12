<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Parsers;

use Override;
use RuntimeException;
use Typdy\StarterKit\Parsers\Contracts\ResourceParser;
use Typdy\StarterKit\Parsers\Exceptions\IncludedValidationException;
use Typdy\StarterKit\Parsers\Exceptions\ResourceValidationException;

use function array_values;
use function gettype;
use function is_array;

final readonly class IncludedParser implements Contracts\IncludedParser
{
    public function __construct(
        private ResourceParser $resourceParser,
    ) {}

    /**
     * @throws IncludedValidationException
     * @throws ResourceValidationException
     */
    #[Override]
    public function parse(mixed $included): array
    {
        $validated = $this->validateIncluded($included);

        $includes = $this->resourceParser->parse($validated);

        if (!is_array($includes)) {
            throw new RuntimeException('Includes must be an array of resources.');
        }

        return $includes;
    }

    /**
     * @return list<mixed>
     *
     * @throws IncludedValidationException
     */
    private function validateIncluded(mixed $included): array
    {
        if (!is_array($included)) {
            IncludedValidationException::invalidIncludedType(gettype($included));
        }

        return array_values($included);
    }
}
