<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Repositories\Contracts;

use Typdy\StarterKit\Storage\Contracts\Driver;

/**
 * @api
 */
interface Collection
{
    public function getBlueprint(): string;

    /**
     * @return list<class-string<Driver>>
     */
    public function getDrivers(): array;

    public function getProject(): string;

    public function getSignature(): string;

    public function getTeam(): string;

    public function isGlobal(): bool;
}
