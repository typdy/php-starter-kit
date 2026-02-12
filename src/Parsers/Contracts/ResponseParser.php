<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Parsers\Contracts;

use Psr\Http\Message\ResponseInterface;
use Typdy\StarterKit\Parsers\Data\Document;
use Typdy\StarterKit\Parsers\Exceptions\ResponseParserException;

/**
 * @api
 */
interface ResponseParser
{
    /**
     * @throws ResponseParserException
     */
    public function parse(ResponseInterface $response): Document;
}
