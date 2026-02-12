<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Webhooks\Contracts;

use Typdy\StarterKit\Webhooks\Data\ReplayResult;
use Typdy\StarterKit\Webhooks\Data\ReplayTask;

/**
 * @api
 */
interface ExecutesReplayTasks
{
    public function execute(ReplayTask $task): ReplayResult;
}
