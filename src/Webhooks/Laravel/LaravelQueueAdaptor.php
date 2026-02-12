<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Webhooks\Laravel;

use Illuminate\Contracts\Bus\Dispatcher;
use Override;
use Throwable;
use Typdy\StarterKit\Webhooks\Contracts\QueuesReplayTasks;
use Typdy\StarterKit\Webhooks\Data\ReplayTask;

use function array_filter;
use function array_values;
use function is_array;
use function is_int;
use function is_string;

final readonly class LaravelQueueAdaptor implements QueuesReplayTasks
{
    public function __construct(
        private Dispatcher $dispatcher,
    ) {}

    #[Override]
    public function enqueue(ReplayTask $task): bool
    {
        $job = new ReplayTaskJob($task);

        $this->applyOptions($job, $task->options);

        try {
            $this->dispatcher->dispatch($job);
        } catch (Throwable) {
            return false;
        }

        return true;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function applyOptions(ReplayTaskJob $job, array $options): void
    {
        // @mago-expect analysis:mixed-assignment
        $tries = $options['tries'] ?? null;

        if (is_int($tries) && $tries > 0) {
            $job->tries = $tries;
        }

        // @mago-expect analysis:mixed-assignment
        $backoff = $options['backoff'] ?? null;

        if (is_array($backoff)) {
            $backoff = array_values(array_filter(
                $backoff,
                static fn (mixed $value): bool => is_int($value) && $value > 0,
            ));

            if ($backoff !== []) {
                /** @var list<int> $backoff */
                $job->backoff = $backoff;
            }
        }

        // @mago-expect analysis:mixed-assignment
        $delaySeconds = $options['delay'] ?? 5;

        if (!is_int($delaySeconds) || $delaySeconds < 0) {
            $delaySeconds = 5;
        }

        $job->delay = $delaySeconds;

        // @mago-expect analysis:mixed-assignment
        $queue = $options['queue'] ?? null;

        if (is_string($queue) && $queue !== '') {
            $job->queue = $queue;
        }
    }
}
