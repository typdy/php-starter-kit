<?php

declare(strict_types=1);

use Typdy\StarterKit\Webhooks\Contracts\ExecutesReplayTasks;
use Typdy\StarterKit\Webhooks\Data\ReplayResult;
use Typdy\StarterKit\Webhooks\Data\ReplayTask;
use Typdy\StarterKit\Webhooks\Laravel\ReplayTaskJob;

it('completes when replay task execution succeeds', function () {
    $executor = mock(ExecutesReplayTasks::class);

    $task = new ReplayTask(
        repositoryClass: 'App\\Repositories\\PageRepository',
        team: 'team',
        project: 'project',
        blueprint: 'page',
    );

    $executor
        ->shouldReceive('execute')
        ->once()
        ->with($task)
        ->andReturn(new ReplayResult(dispatched: true, replayed: 2));

    $job = new ReplayTaskJob($task);

    $job->handle($executor);

    expect(true)->toBeTrue();
});

it('throws when replay task execution fails', function () {
    $executor = mock(ExecutesReplayTasks::class);

    $task = new ReplayTask(
        repositoryClass: 'App\\Repositories\\PageRepository',
        team: 'team',
        project: 'project',
        blueprint: 'page',
    );

    $executor
        ->shouldReceive('execute')
        ->once()
        ->with($task)
        ->andReturn(new ReplayResult(dispatched: false, failed: true));

    $job = new ReplayTaskJob($task);

    $job->handle($executor);
})->throws(RuntimeException::class, 'Replay task failed');
