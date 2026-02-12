<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Repositories\Contracts;

use Typdy\StarterKit\Repositories\Data\Request;

/**
 * @api
 */
interface Replayable
{
    /**
     * @param array<string, mixed> $options
     */
    public function replay(?int $constructId = null, array $options = []): int;

    /**
     * @return list<Request>
     */
    public function replayableRequests(?int $constructId = null): array;
}
