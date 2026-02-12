<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Webhooks;

use Override;
use Typdy\StarterKit\Repositories\Contracts\Collection;
use Typdy\StarterKit\Repositories\Contracts\Replayable;
use Typdy\StarterKit\Webhooks\Contracts\ReplayDispatcher;
use Typdy\StarterKit\Webhooks\Data\ReplayResult;

final class InlineReplayDispatcher implements ReplayDispatcher
{
    #[Override]
    public function dispatch(
        Collection&Replayable $repository,
        ?int $constructId = null,
        array $options = [],
    ): ReplayResult {
        $replayed = $repository->replay($constructId, $options);

        return new ReplayResult(dispatched: true, replayed: $replayed);
    }
}
