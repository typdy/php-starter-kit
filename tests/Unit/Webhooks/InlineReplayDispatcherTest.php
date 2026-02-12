<?php

declare(strict_types=1);

use Typdy\StarterKit\Repositories\Contracts\Collection;
use Typdy\StarterKit\Repositories\Contracts\Replayable;
use Typdy\StarterKit\Webhooks\InlineReplayDispatcher;

it('dispatches replay inline and reports replayed count', function () {
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
            expect($constructId)->toBe(77);
            expect($options['tries'] ?? null)->toBe(3);

            return 4;
        }

        public function replayableRequests(?int $constructId = null): array
        {
            return [];
        }
    };

    $dispatcher = new InlineReplayDispatcher();

    $result = $dispatcher->dispatch($repo, 77, ['tries' => 3]);

    expect($result->dispatched)->toBeTrue();
    expect($result->failed)->toBeFalse();
    expect($result->replayed)->toBe(4);
});
