<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Parsers\Contracts;

use Psr\Http\Message\ResponseInterface;
use Typdy\StarterKit\Parsers\Data\Document;
use Typdy\StarterKit\Parsers\Exceptions\DocumentValidationException;

/**
 * @api
 */
interface DocumentParser
{
    /**
     * @throws DocumentValidationException
     */
    public function parse(mixed $body, ResponseInterface $response): Document;
}
