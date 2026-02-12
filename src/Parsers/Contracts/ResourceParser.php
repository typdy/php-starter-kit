<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Parsers\Contracts;

use Typdy\StarterKit\Parsers\Data\Resource;
use Typdy\StarterKit\Parsers\Exceptions\ResourceValidationException;

/**
 * @api
 */
interface ResourceParser
{
    /**
     * @return Resource|list<Resource>
     *
     * @throws ResourceValidationException
     */
    public function parse(mixed $data): Resource|array;
}
