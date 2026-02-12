<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Webhooks\Contracts;

use Typdy\StarterKit\Webhooks\Data\ReplayTask;

/**
 * @api
 */
interface QueuesReplayTasks
{
    public function enqueue(ReplayTask $task): bool;
}
