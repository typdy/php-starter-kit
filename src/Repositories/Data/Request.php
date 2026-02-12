<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Repositories\Data;

use InvalidArgumentException;

final class Request
{
    /**
     * @param array<string, mixed> $query
     */
    public function __construct(
        public readonly string $team,
        public readonly string $project,
        public readonly ?string $blueprint = null,
        public readonly ?string $collection = null,
        public readonly bool $isGlobal = false,
        public readonly ?int $id = null,
        public readonly ?string $identifier = null,
        public array $query = [],
    ) {
        if ($this->id !== null && $this->identifier !== null) {
            throw new InvalidArgumentException('Cannot set an both id and an identifier.');
        }
    }

    public function cloneWithIdentifierOnly(?string $identifier = null): self
    {
        return new self(
            team: $this->team,
            project: $this->project,
            collection: $this->collection,
            blueprint: $this->blueprint,
            isGlobal: $this->isGlobal,
            id: null,
            identifier: $identifier ?? $this->identifier,
            query: $this->query,
        );
    }

    public function cloneWithIdOnly(?int $id = null): self
    {
        return new self(
            team: $this->team,
            project: $this->project,
            collection: $this->collection,
            blueprint: $this->blueprint,
            isGlobal: $this->isGlobal,
            id: $id ?? $this->id,
            identifier: null,
            query: $this->query,
        );
    }

    /**
     * @param array<string, mixed> $query
     */
    public function cloneWithQuery(array $query): self
    {
        return new self(
            team: $this->team,
            project: $this->project,
            collection: $this->collection,
            blueprint: $this->blueprint,
            isGlobal: $this->isGlobal,
            id: $this->id,
            identifier: $this->identifier,
            query: $query,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'team' => $this->team,
            'project' => $this->project,
            'collection' => $this->collection,
            'blueprint' => $this->blueprint,
            'isGlobal' => $this->isGlobal,
            'id' => $this->id,
            'identifier' => $this->identifier,
            'query' => $this->query,
        ];
    }
}
