<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Webhooks\Contracts;

use Typdy\StarterKit\Webhooks\Payload;
use Typdy\StarterKit\Webhooks\ResultSet;

/**
 * @api
 */
interface Handler
{
    public function getName(): string;

    public function handle(Payload $payload, ResultSet $results): void;

    /**
     * @param array<string, mixed> $options
     */
    public function withOptions(array $options): static;
}
