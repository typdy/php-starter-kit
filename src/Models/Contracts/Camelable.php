<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Models\Contracts;

/**
 * @api
 */
interface Camelable
{
    /**
     * @param array<string, mixed> $fields
     *
     * @return array<string, mixed>
     */
    public function camelFields(array $fields): array;

    /**
     * @param array<string, mixed> $fields
     *
     * @return array<string, mixed>
     */
    public function uncamelFields(array $fields): array;
}
