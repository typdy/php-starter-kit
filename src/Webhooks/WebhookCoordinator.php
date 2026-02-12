<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Webhooks;

use Typdy\StarterKit\Webhooks\Contracts\Handler;

use function array_key_exists;

final class WebhookCoordinator
{
    /**
     * @var array<string, list<Handler>>
     */
    private array $handlers = [];

    /**
     * @return array<string, array<string, ResultSet>>
     */
    public function handle(Payload $payload): array
    {
        $results = [];

        if (!array_key_exists($payload->name, $this->handlers)) {
            return $results;
        }

        foreach ($this->handlers[$payload->name] as $handler) {
            $resultSet = new ResultSet();

            $handler->handle($payload, $resultSet);

            $results[$payload->name][$handler->getName()] = $resultSet;
        }

        return $results;
    }

    public function registerHandler(string $name, Handler $handler): void
    {
        $this->handlers[$name][] = $handler;
    }
}
