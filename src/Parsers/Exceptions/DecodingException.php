<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Parsers\Exceptions;

use Exception;

final class DecodingException extends Exception
{
    /**
     * @throws self
     */
    public static function invalidDocument(): never
    {
        throw new self('Document body must be a JSON object.');
    }

    /**
     * @throws self
     */
    public static function invalidJson(string $message): never
    {
        throw new self("Failed to decode JSON data: {$message}");
    }
}
