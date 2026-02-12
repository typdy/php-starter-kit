<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Webhooks\Laravel;

use DateInterval;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use RuntimeException;
use Typdy\StarterKit\Webhooks\Contracts\ExecutesReplayTasks;
use Typdy\StarterKit\Webhooks\Data\ReplayTask;

final class ReplayTaskJob implements ShouldQueue
{
    public int $tries = 1;

    /**
     * @var int|list<int>
     */
    public int|array $backoff = 0;

    public int|DateInterval|DateTimeInterface|null $delay = null;

    public ?int $timeout = null;

    public ?string $queue = null;

    public ?string $connection = null;

    public bool $afterCommit = false;

    public function __construct(
        public readonly ReplayTask $task,
    ) {}

    public function handle(ExecutesReplayTasks $executor): void
    {
        $result = $executor->execute($this->task);

        if ($result->failed) {
            throw new RuntimeException(
                "Replay task failed for {$this->task->team}/{$this->task->project}/{$this->task->blueprint}.",
            );
        }
    }
}
