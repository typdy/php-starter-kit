<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Parsers;

use Exception;
use InvalidArgumentException;
use JsonException;
use Override;
use Psr\Http\Message\ResponseInterface;
use Typdy\StarterKit\Parsers\Contracts\DocumentParser;
use Typdy\StarterKit\Parsers\Data\Document;
use Typdy\StarterKit\Parsers\Data\Resource;
use Typdy\StarterKit\Parsers\Exceptions\DecodingException;
use Typdy\StarterKit\Parsers\Exceptions\ResponseParserException;

use function is_array;
use function is_object;
use function json_decode;

use const JSON_THROW_ON_ERROR;

final readonly class ResponseParser implements Contracts\ResponseParser
{
    public function __construct(
        private DocumentParser $documentParser,
        private ?string $expectedType = null,
        private bool $mixedType = false,
    ) {
        if ($expectedType === null && !$mixedType) {
            throw new InvalidArgumentException("Either explicitly mark as 'mixedType' or provide an 'expectedType'.");
        }
    }

    /**
     * @throws ResponseParserException
     * @throws DecodingException
     * @throws JsonException
     */
    #[Override]
    public function parse(ResponseInterface $response): Document
    {
        if (!$this->hasBody($response)) {
            return new Document($response);
        }

        $body = $this->decodeJson((string) $response->getBody());

        try {
            $document = $this->documentParser->parse($body, $response);
        } catch (Exception $e) {
            throw new ResponseParserException($e);
        }

        if (!$this->mixedType && !$this->isExpectedType($document)) {
            throw new InvalidArgumentException(
                "Document data type does not match expected type '{$this->expectedType}'.",
            );
        }

        return $document;
    }

    /**
     * @throws DecodingException
     * @throws JsonException
     */
    private function decodeJson(string $json): object
    {
        try {
            // @mago-expect analysis:mixed-assignment
            $doc = json_decode($json, associative: false, flags: JSON_THROW_ON_ERROR);

            if (!is_object($doc)) {
                DecodingException::invalidDocument();
            }

            return $doc;
        } catch (JsonException $e) {
            DecodingException::invalidJson($e->getMessage());
        }
    }

    private function hasBody(ResponseInterface $response): bool
    {
        $body = $response->getBody();
        $size = $body->getSize();

        if ($size === 0) {
            return false;
        }

        if ($size !== null && $size > 0) {
            return true;
        }

        $pos = null;

        if ($body->isSeekable()) {
            $pos = $body->tell();
        }

        $chunk = $body->read(1);

        if ($pos !== null) {
            $body->seek($pos);
        }

        return $chunk !== '';
    }

    private function isExpectedType(Document $document): bool
    {
        if ($document->data instanceof Resource) {
            return $document->data->type === $this->expectedType;
        }

        if (is_array($document->data)) {
            foreach ($document->data as $resource) {
                if ($resource->type !== $this->expectedType) {
                    return false;
                }
            }

            return true;
        }

        return true;
    }
}
