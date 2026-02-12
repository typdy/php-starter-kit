<?php

declare(strict_types=1);

use Illuminate\Contracts\Bus\Dispatcher;
use Typdy\StarterKit\Webhooks\Data\ReplayTask;
use Typdy\StarterKit\Webhooks\Laravel\LaravelQueueAdaptor;
use Typdy\StarterKit\Webhooks\Laravel\ReplayTaskJob;

it('maps queue options onto laravel replay jobs', function () {
    $dispatcher = mock(Dispatcher::class);

    $dispatcher
        ->shouldReceive('dispatch')
        ->once()
        ->with(Mockery::on(
            static fn (ReplayTaskJob $job): bool => (
                $job->tries === 4
                && $job->backoff === [1, 5]
                && $job->delay === 10
                && $job->queue === 'webhooks'
            ),
        ))
        ->andReturnNull();

    $queue = new LaravelQueueAdaptor($dispatcher);

    $ok = $queue->enqueue(new ReplayTask(
        repositoryClass: 'App\\Repositories\\PageRepository',
        team: 'team',
        project: 'project',
        blueprint: 'page',
        constructId: 1,
        options: [
            'tries' => 4,
            'backoff' => [1, 5],
            'delay' => 10,
            'queue' => 'webhooks',
        ],
    ));

    expect($ok)->toBeTrue();
});

it('returns false when laravel dispatch throws', function () {
    $dispatcher = mock(Dispatcher::class);

    $dispatcher
        ->shouldReceive('dispatch')
        ->once()
        ->andThrow(new RuntimeException('dispatch failed'));

    $queue = new LaravelQueueAdaptor($dispatcher);

    $ok = $queue->enqueue(new ReplayTask(
        repositoryClass: 'App\\Repositories\\PageRepository',
        team: 'team',
        project: 'project',
        blueprint: 'page',
    ));

    expect($ok)->toBeFalse();
});
