<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Models\Contracts;

use Carbon\Carbon;
use Typdy\StarterKit\Parsers\Data\Resource;

/**
 * @api
 */
interface Construct
{
    public protected(set) ?Resource $resource { get; set; }

    /**
     * ids are set by typdy when hydrating from a resource. Client applications
     * should not set this value directly - it will be fulfilled upon sync with
     * typdy.
     */
    public protected(set) ?int $id { get; set; }

    public ?string $identifier { get; set; }

    /**
     * @var array<string, mixed>
     */
    public array $meta { get; set; }

    public ?Carbon $created { set; }

    public ?Carbon $updated { set; }

    public function getBlueprint(): string;

    public function getProject(): string;

    /**
     * @return array<string, mixed>
     */
    public function getRelationshipMeta(string $name): array;

    public function getSignature(): string;

    public function getSyncBody(): ?string;

    /**
     * @return array<string, mixed>
     */
    public function getSyncHeaders(): array;

    /**
     * @return array<string, mixed>
     */
    public function getSyncParameters(): array;

    public function getTeam(): string;

    /**
     * @return $this
     */
    public function hydrateFromResource(Resource $resource): self;

    public function isGlobal(): bool;

    public function isNew(): bool;
}
