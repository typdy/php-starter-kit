<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Parsers;

use Override;
use Psr\Http\Message\ResponseInterface;
use Typdy\StarterKit\Parsers\Contracts\ErrorsParser;
use Typdy\StarterKit\Parsers\Contracts\IncludedParser;
use Typdy\StarterKit\Parsers\Contracts\MetaParser;
use Typdy\StarterKit\Parsers\Contracts\ResourceParser;
use Typdy\StarterKit\Parsers\Data\Document;
use Typdy\StarterKit\Parsers\Data\Error;
use Typdy\StarterKit\Parsers\Data\Resource;
use Typdy\StarterKit\Parsers\Exceptions\DocumentValidationException;
use Typdy\StarterKit\Parsers\Exceptions\ErrorsValidationException;
use Typdy\StarterKit\Parsers\Exceptions\IncludedValidationException;
use Typdy\StarterKit\Parsers\Exceptions\MetaValidationException;
use Typdy\StarterKit\Parsers\Exceptions\ResourceValidationException;

use function gettype;
use function is_array;
use function is_object;
use function property_exists;

final readonly class DocumentParser implements Contracts\DocumentParser
{
    public function __construct(
        private ResourceParser $resourceParser,
        private IncludedParser $includedParser,
        private ErrorsParser $errorsParser,
        private MetaParser $metaParser,
    ) {}

    /**
     * @throws DocumentValidationException
     * @throws ResourceValidationException
     * @throws IncludedValidationException
     * @throws ErrorsValidationException
     * @throws MetaValidationException
     */
    #[Override]
    public function parse(mixed $body, ResponseInterface $response): Document
    {
        $validated = $this->validateDocument($body);

        $document = new Document(
            $response,
            data: $this->parseData($validated),
            included: $this->parseIncluded($validated),
            errors: $this->parseErrors($validated),
            meta: $this->parseMeta($validated),
        );

        $this->associateIncluded($document);

        return $document;
    }

    private function associateIncluded(Document $document): void
    {
        $data = $document->data ?? [];

        if (!is_array($data)) {
            $data = [$data];
        }

        $allResources = [...$document->included, ...$data];

        foreach ($allResources as $resource) {
            foreach ($resource->relationships as $relationship) {
                $relationship->associateIncluded($document->included);
            }
        }
    }

    /**
     * @param object{
     *     data?: object|list<object>|null,
     *     ...
     * } $body
     *
     * @return Resource|list<Resource>|null
     *
     * @throws ResourceValidationException
     */
    private function parseData(object $body): Resource|array|null
    {
        if (!property_exists($body, 'data') || $body->data === null) {
            return null;
        }

        return $this->resourceParser->parse($body->data);
    }

    /**
     * @param object{
     *     errors?: list<object>,
     *     ...
     * } $body
     *
     * @return list<Error>
     *
     * @throws ErrorsValidationException
     */
    private function parseErrors(object $body): array
    {
        if (!property_exists($body, 'errors')) {
            return [];
        }

        return $this->errorsParser->parse($body->errors);
    }

    /**
     * @param object{
     *     included?: list<object>,
     *     ...
     * } $body
     * @return list<Resource>
     *
     * @throws IncludedValidationException
     */
    private function parseIncluded(object $body): array
    {
        if (!property_exists($body, 'included')) {
            return [];
        }

        return $this->includedParser->parse($body->included);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws MetaValidationException
     */
    private function parseMeta(object $body): array
    {
        if (!property_exists($body, 'meta')) {
            return [];
        }

        return $this->metaParser->parse($body->meta);
    }

    /**
     * @return object{
     *     data?: object|list<object>|null,
     *     errors?: list<object>,
     *     included?: list<object>,
     * }
     *
     * @throws DocumentValidationException
     */
    private function validateDocument(mixed $body): object
    {
        if (!is_object($body)) {
            DocumentValidationException::invalidDocumentType(gettype($body));
        }

        if (!property_exists($body, 'data') && !property_exists($body, 'errors') && !property_exists($body, 'meta')) {
            DocumentValidationException::missingTopLevelMembers();
        }

        if (property_exists($body, 'data') && property_exists($body, 'errors')) {
            DocumentValidationException::dataAndErrorsPresent();
        }

        if (property_exists($body, 'included') && !property_exists($body, 'data')) {
            DocumentValidationException::includedPresentWithoutData();
        }

        if (
            property_exists($body, 'data')
            && $body->data !== null
            && !is_object($body->data)
            && !is_array($body->data)
        ) {
            DocumentValidationException::invalidDataType(gettype($body->data));
        }

        // @mago-expect analysis:invalid-return-statement
        return $body;
    }
}
