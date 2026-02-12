<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Parsers\Contracts;

use Typdy\StarterKit\Parsers\Exceptions\MetaValidationException;

/**
 * @api
 */
interface MetaParser
{
    /**
     * @return array<string, mixed>
     *
     * @throws MetaValidationException
     */
    public function parse(mixed $meta): array;
}
