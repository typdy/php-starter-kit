<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Parsers\Exceptions;

use Exception;

final class IncludedValidationException extends Exception
{
    /**
     * @throws self
     */
    public static function invalidIncludedType(string $actual): never
    {
        throw new self("Expected JSON:API included to be of type 'array', got '{$actual}' instead.");
    }
}
