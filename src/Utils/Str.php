<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Utils;

use function array_key_exists;
use function ctype_lower;
use function lcfirst;
use function mb_strtolower;
use function preg_replace;
use function str_replace;
use function ucwords;

final class Str
{
    /**
     * @var array<string, string>
     */
    private static array $kebabCache = [];

    public static function camel(string $value): string
    {
        return lcfirst(self::studly($value));
    }

    public static function kebab(string $value): string
    {
        $key = $value;

        if (array_key_exists($key, self::$kebabCache)) {
            return self::$kebabCache[$key];
        }

        if (!ctype_lower($value)) {
            $value = (
                preg_replace(
                    pattern: '/(.)(?=[A-Z])/u',
                    replacement: '$1-',
                    subject: $value,
                ) ?? ''
            )
                |> self::lower(...);
        }

        return self::$kebabCache[$key] = $value;
    }

    public static function lower(string $value): string
    {
        return mb_strtolower(string: $value, encoding: 'UTF-8');
    }

    public static function studly(string $value): string
    {
        $value = str_replace(search: ['-', '_'], replace: ' ', subject: $value);
        $value = ucwords($value);

        return str_replace(search: ' ', replace: '', subject: $value);
    }
}
