<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Webhooks\Exceptions;

use RuntimeException;

final class InvalidSigningKey extends RuntimeException
{
    public static function throw(string $name): never
    {
        throw new self("The signing key for the webhook '{$name}' is invalid.");
    }
}
