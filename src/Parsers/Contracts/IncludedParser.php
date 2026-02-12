<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Parsers\Contracts;

use Typdy\StarterKit\Parsers\Data\Resource;
use Typdy\StarterKit\Parsers\Exceptions\IncludedValidationException;

/**
 * @api
 */
interface IncludedParser
{
    /**
     * @return list<Resource>
     *
     * @throws IncludedValidationException
     */
    public function parse(mixed $included): array;
}
