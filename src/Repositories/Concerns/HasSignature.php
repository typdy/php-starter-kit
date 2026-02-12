<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Repositories\Concerns;

/**
 * @api
 */
trait HasSignature
{
    abstract public function getBlueprint(): string;

    abstract public function getProject(): string;

    abstract public function getTeam(): string;

    abstract public function isGlobal(): bool;

    public function getSignature(): string
    {
        $signature = $this->getTeam() . ':' . $this->getProject();

        if ($this->isGlobal()) {
            return $signature . ':global';
        }

        return $signature . ':' . $this->getBlueprint();
    }
}
