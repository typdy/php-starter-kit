<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Parsers\Exceptions;

use Exception;

final class MetaValidationException extends Exception
{
    /**
     * @throws self
     */
    public static function invalidMetaType(string $actual, string $path = ''): never
    {
        if ($path !== '') {
            $path = ", in '{$path}'";
        }

        throw new self("Expected JSON:API meta to be of type 'object', got '{$actual}' instead{$path}.");
    }
}
