<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Models\Contracts;

/**
 * @api
 */
interface Relatable
{
    /**
     * @return array<string, mixed>
     */
    public function getRelationshipMeta(string $name): array;

    /**
     * @return array<string, string>
     */
    public function getRelationships(): array;
}
