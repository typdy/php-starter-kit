<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Models\Concerns;

/**
 * @api
 */
trait HasMeta
{
    /**
     * @var array<string, mixed>
     */
    public array $meta = [];
}
