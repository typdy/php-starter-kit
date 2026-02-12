<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Parsers\Contracts;

use Typdy\StarterKit\Parsers\Data\Error;
use Typdy\StarterKit\Parsers\Exceptions\ErrorsValidationException;

/**
 * @api
 */
interface ErrorsParser
{
    /**
     * @return list<Error>
     *
     * @throws ErrorsValidationException
     */
    public function parse(mixed $errors): array;
}
