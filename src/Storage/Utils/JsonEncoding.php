<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Storage\Utils;

use function json_encode;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;

final readonly class JsonEncoding
{
    /**
     * @param array<array-key, mixed> $data
     */
    public static function pretty(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) ?: '';
    }
}
