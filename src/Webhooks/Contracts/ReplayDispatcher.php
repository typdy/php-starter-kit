<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Webhooks\Contracts;

use Typdy\StarterKit\Repositories\Contracts\Collection;
use Typdy\StarterKit\Repositories\Contracts\Replayable;
use Typdy\StarterKit\Webhooks\Data\ReplayResult;

/**
 * @api
 */
interface ReplayDispatcher
{
    /**
     * @param array<string, mixed> $options
     */
    public function dispatch(
        Collection&Replayable $repository,
        ?int $constructId = null,
        array $options = [],
    ): ReplayResult;
}
