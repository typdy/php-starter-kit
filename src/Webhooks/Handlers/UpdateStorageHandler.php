<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Webhooks\Handlers;

use Override;
use Typdy\StarterKit\Repositories\Contracts\Collection;
use Typdy\StarterKit\Repositories\Contracts\Replayable;
use Typdy\StarterKit\Resolvers\Contracts\ResolvesRepositories;
use Typdy\StarterKit\Storage\Contracts\DatabaseDriver;
use Typdy\StarterKit\Typdy;
use Typdy\StarterKit\Webhooks\Contracts\Handler;
use Typdy\StarterKit\Webhooks\Contracts\ReplayDispatcher;
use Typdy\StarterKit\Webhooks\Data\Result;
use Typdy\StarterKit\Webhooks\Payload;
use Typdy\StarterKit\Webhooks\ResultSet;

use function in_array;
use function is_subclass_of;

final class UpdateStorageHandler implements Handler
{
    /**
     * @var array<string, mixed>
     */
    private array $options = [];

    /**
     * @var array<string, list<string>>
     */
    private array $supported = [
        'blueprints' => ['update', 'delete'],
        'collections' => ['update', 'delete'],
        'fields' => ['create', 'update'],
        'constructs' => ['create', 'update', 'delete'],
        'globals' => ['create', 'update', 'delete'],
    ];

    #[Override]
    public function getName(): string
    {
        return 'update-storage';
    }

    #[Override]
    public function handle(Payload $payload, ResultSet $results): void
    {
        $domain = $payload->getDomain()->value;
        $event = $payload->getEvent()->value;

        $supportedEvents = $this->supported[$domain] ?? [];

        if (!in_array($event, $supportedEvents, strict: true)) {
            $results->add(new Result("No action taken for event: {$domain}.{$event}."));

            return;
        }

        $team = (string) ($this->options['team'] ?? Typdy::config()->team);
        $project = (string) ($this->options['project'] ?? Typdy::config()->project);
        $blueprint = $payload->getBlueprint();

        if ($blueprint === null) {
            $results->add(new Result(
                "Webhook payload for {$domain}.{$event} did not include a blueprint identifier.",
                failed: true,
            ));

            return;
        }

        $constructId = $payload->payload?->construct->id ?? null;
        $constructId = $constructId !== null ? (int) $constructId : null;

        $repos = Typdy::container(ResolvesRepositories::class)->resolveMany($team, $project);
        $dispatcher = $this->resolveDispatcher();

        $replayed = 0;
        $dispatched = 0;
        $failed = 0;

        foreach ($repos as $repo) {
            if (!$this->isSupportedRepo($repo, $domain, $blueprint)) {
                continue;
            }

            if ($this->hasDatabaseDriver($repo)) {
                continue;
            }

            // @mago-expect analysis:possibly-invalid-argument Replayable interface is checked above
            $result = $dispatcher->dispatch($repo, $constructId, $this->options);

            if ($result->failed) {
                $failed++;

                continue;
            }

            if ($result->dispatched) {
                $dispatched++;
            }

            $replayed += $result->replayed;
        }

        $failedMessage = $failed > 0
            ? " ({$failed} failed dispatches)"
            : '';

        $results->add(new Result(
            "Replay handled for {$domain}.{$event} across {$dispatched} repositories ({$replayed} requests).{$failedMessage}",
            failed: $failed > 0,
        ));
    }

    #[Override]
    public function withOptions(array $options): static
    {
        $this->options = $options;

        return $this;
    }

    private function hasDatabaseDriver(Collection $repo): bool
    {
        foreach ($repo->getDrivers() as $driverClass) {
            if (is_subclass_of($driverClass, DatabaseDriver::class)) {
                return true;
            }
        }

        return false;
    }

    private function isSupportedRepo(Collection $repo, string $domain, string $blueprint): bool
    {
        if (!$repo instanceof Replayable) {
            return false;
        }

        if ($domain === 'globals') {
            return $repo->isGlobal();
        }

        return $repo->getBlueprint() === $blueprint;
    }

    private function resolveDispatcher(): ReplayDispatcher
    {
        /** @var class-string<ReplayDispatcher>|null $class */
        $class = $this->options['dispatcher'] ?? null;

        if ($class !== null) {
            return Typdy::container($class);
        }

        return Typdy::container(ReplayDispatcher::class);
    }
}
