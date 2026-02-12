<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Utils;

use function is_array;
use function ksort;

final class Arr
{
    /**
     * @template TKey of array-key
     * @template TValue
     *
     * @param array<TKey, TValue> $array
     *
     * @return array<TKey, TValue>
     */
    public static function deepSort(array $array): array
    {
        ksort($array);

        foreach ($array as $key => $value) {
            if (!is_array($value)) {
                continue;
            }

            $array[$key] = self::deepSort($value);
        }

        return $array;
    }

    /**
     * @template TKey of array-key
     * @template TValue
     *
     * @param array<TKey, TValue>|TValue $value
     *
     * @return ($value is array<TKey, TValue> ? array<TKey, TValue> : list{TValue})
     */
    public static function wrap(mixed $value): array
    {
        return is_array($value) ? $value : [$value];
    }
}
