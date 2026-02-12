<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Webhooks;

use Override;
use Typdy\StarterKit\Repositories\Contracts\Replayable;
use Typdy\StarterKit\Resolvers\Contracts\ResolvesRepositories;
use Typdy\StarterKit\Typdy;
use Typdy\StarterKit\Webhooks\Contracts\ExecutesReplayTasks;
use Typdy\StarterKit\Webhooks\Data\ReplayResult;
use Typdy\StarterKit\Webhooks\Data\ReplayTask;

final class ReplayTaskExecutor implements ExecutesReplayTasks
{
    #[Override]
    public function execute(ReplayTask $task): ReplayResult
    {
        $repository = Typdy::container(ResolvesRepositories::class)
            ->resolveOne($task->team, $task->project, $task->blueprint);

        if (!$repository instanceof Replayable || $repository::class !== $task->repositoryClass) {
            return new ReplayResult(dispatched: false, failed: true);
        }

        $replayed = $repository->replay($task->constructId, $task->options);

        return new ReplayResult(dispatched: true, replayed: $replayed);
    }
}
