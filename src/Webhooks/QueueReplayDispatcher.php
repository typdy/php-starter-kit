<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Webhooks;

use Override;
use Typdy\StarterKit\Repositories\Contracts\Collection;
use Typdy\StarterKit\Repositories\Contracts\Replayable;
use Typdy\StarterKit\Webhooks\Contracts\QueuesReplayTasks;
use Typdy\StarterKit\Webhooks\Contracts\ReplayDispatcher;
use Typdy\StarterKit\Webhooks\Data\ReplayResult;
use Typdy\StarterKit\Webhooks\Data\ReplayTask;

final readonly class QueueReplayDispatcher implements ReplayDispatcher
{
    public function __construct(
        private QueuesReplayTasks $queue,
    ) {}

    #[Override]
    public function dispatch(
        Collection&Replayable $repository,
        ?int $constructId = null,
        array $options = [],
    ): ReplayResult {
        $dispatched = $this->queue->enqueue(new ReplayTask(
            repositoryClass: $repository::class,
            team: $repository->getTeam(),
            project: $repository->getProject(),
            blueprint: $repository->getBlueprint(),
            constructId: $constructId,
            options: $options,
        ));

        return new ReplayResult(
            dispatched: $dispatched,
            replayed: 0,
            failed: !$dispatched,
        );
    }
}
