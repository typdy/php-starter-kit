<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Parsers\Exceptions;

use Exception;

final class ResponseParserException extends Exception
{
    public function __construct(Exception $previous)
    {
        parent::__construct(message: 'Failed to parse JSON:API response.', previous: $previous);
    }
}
