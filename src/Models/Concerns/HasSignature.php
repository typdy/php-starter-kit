<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Models\Concerns;

/**
 * @api
 */
trait HasSignature
{
    abstract public function getBlueprint(): string;

    abstract public function getProject(): string;

    abstract public function getTeam(): string;

    abstract public function isGlobal(): bool;

    final public function getSignature(): string
    {
        $blueprint = $this->isGlobal() ? 'global' : $this->getBlueprint();

        return $this->getTeam() . ':' . $this->getProject() . ':' . $blueprint;
    }
}
