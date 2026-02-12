<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Parsers\Exceptions;

use Exception;

final class ErrorsValidationException extends Exception
{
    /**
     * @throws self
     */
    public static function emptyErrorsArray(): never
    {
        throw new self('The JSON:API errors array is empty.');
    }

    /**
     * @throws self
     */
    public static function errorItemMissingMembers(int $index): never
    {
        throw new self(
            "A valid JSON:API error object must contain at least one of the following members: 'id', 'links', 'status', 'code', 'title', 'detail', 'source' or 'meta'. At index '{$index}'.",
        );
    }

    /**
     * @throws self
     */
    public static function invalidErrorItemType(int $index, string $actual): never
    {
        throw new self("Expected JSON:API error to be of type 'object', got '{$actual}' instead, at index '{$index}'.");
    }

    /**
     * @throws self
     */
    public static function invalidErrorMemberType(int $index, string $member, string $actual): never
    {
        throw new self(
            "Expected JSON:API error member '{$member}' to be of type 'string', got '{$actual}' instead, at index '{$index}'.",
        );
    }

    /**
     * @throws self
     */
    public static function invalidErrorsType(string $actual): never
    {
        throw new self("Expected JSON:API errors to be of type 'array', got '{$actual}' instead.");
    }
}
