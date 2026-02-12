<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Parsers\Data;

use Psr\Http\Message\ResponseInterface;

final readonly class Document
{
    /**
     * @param Resource|list<Resource>|null $data
     * @param list<Resource> $included
     * @param list<Error> $errors
     * @param array<string, mixed> $meta
     */
    public function __construct(
        public ResponseInterface $response,
        public Resource|array|null $data = null,
        public array $included = [],
        public array $errors = [],
        public array $meta = [],
    ) {}

    public function failed(): bool
    {
        return !$this->successful();
    }

    public function successful(): bool
    {
        $statusCode = $this->response->getStatusCode();

        return $statusCode >= 200 && $statusCode < 300;
    }

    public function wasNotFound(): bool
    {
        return $this->response->getStatusCode() === 404;
    }
}
