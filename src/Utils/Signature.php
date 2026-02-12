<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Utils;

use InvalidArgumentException;
use Typdy\StarterKit\Models\Contracts\Construct;
use Typdy\StarterKit\Repositories\Contracts\Collection;

final readonly class Signature
{
    public static function validate(Collection $repository, Construct $model): void
    {
        if ($model->getSignature() !== $repository->getSignature()) {
            throw new InvalidArgumentException(
                "Construct signature '{$model->getSignature()}' does not match repository signature '{$repository->getSignature()}'.",
            );
        }
    }
}
