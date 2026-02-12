<?php

declare(strict_types=1);

use Typdy\StarterKit\Repositories\Contracts\Collection;
use Typdy\StarterKit\Repositories\Contracts\Replayable;
use Typdy\StarterKit\Webhooks\Contracts\QueuesReplayTasks;
use Typdy\StarterKit\Webhooks\Data\ReplayTask;
use Typdy\StarterKit\Webhooks\QueueReplayDispatcher;

it('enqueues replay tasks and reports dispatch status', function () {
    $repo = new class implements Collection, Replayable {
        public function getBlueprint(): string
        {
            return 'test';
        }

        public function getDrivers(): array
        {
            return [];
        }

        public function getProject(): string
        {
            return 'project';
        }

        public function getSignature(): string
        {
            return 'team:project:test';
        }

        public function getTeam(): string
        {
            return 'team';
        }

        public function isGlobal(): bool
        {
            return false;
        }

        public function replay(?int $constructId = null, array $options = []): int
        {
            return 0;
        }

        public function replayableRequests(?int $constructId = null): array
        {
            return [];
        }
    };

    $queue = mock(QueuesReplayTasks::class);
    $queue
        ->shouldReceive('enqueue')
        ->once()
        ->with(Mockery::on(
            static fn (ReplayTask $task): bool => (
                $task->repositoryClass !== ''
                && $task->team === 'team'
                && $task->project === 'project'
                && $task->blueprint === 'test'
                && $task->constructId === 99
                && ($task->options['tries'] ?? null) === 3
            ),
        ))
        ->andReturn(true);

    $dispatcher = new QueueReplayDispatcher($queue);

    $result = $dispatcher->dispatch($repo, 99, ['tries' => 3]);

    expect($result->dispatched)->toBeTrue();
    expect($result->failed)->toBeFalse();
    expect($result->replayed)->toBe(0);
});
