<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Models\Contracts;

/**
 * @api
 */
interface Aliasable
{
    /**
     * @param array<string, mixed> $fields
     *
     * @return array<string, mixed>
     */
    public function aliasFields(array $fields): array;

    /**
     * @param array<string, mixed> $fields
     *
     * @return array<string, mixed>
     */
    public function unaliasFields(array $fields): array;
}
