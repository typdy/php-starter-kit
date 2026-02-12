<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Parsers\Exceptions;

use Exception;

final class DocumentValidationException extends Exception
{
    /**
     * @throws self
     */
    public static function dataAndErrorsPresent(): never
    {
        throw new self("A valid JSON:API document cannot contain both the 'data' and 'errors' top-level members.");
    }

    /**
     * @throws self
     */
    public static function includedPresentWithoutData(): never
    {
        throw new self(
            "A valid JSON:API document cannot contain the 'included' top-level member without 'data' member.",
        );
    }

    /**
     * @throws self
     */
    public static function invalidDataType(string $actual): never
    {
        throw new self("Expected 'data' member to be of type 'null', 'object' or 'array', got '{$actual}' instead.");
    }

    /**
     * @throws self
     */
    public static function invalidDocumentType(string $actual): never
    {
        throw new self("Expected JSON:API document to be of type 'object', got '{$actual}' instead.");
    }

    /**
     * @throws self
     */
    public static function missingTopLevelMembers(): never
    {
        throw new self(
            "A valid JSON:API document must contain at least one of the following top-level members: 'data', 'errors' or 'meta'.",
        );
    }
}
