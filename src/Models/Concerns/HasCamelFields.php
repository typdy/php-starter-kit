<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Models\Concerns;

use Typdy\StarterKit\Utils\Str;

use function array_combine;
use function array_key_exists;
use function array_keys;
use function array_map;
use function array_values;

/**
 * Constructs a named value array to and from its camel-cased representation.
 *
 * @api
 */
trait HasCamelFields
{
    /**
     * @internal
     *
     * @var array<string, string>
     */
    private array $_camelCache = [];

    /**
     * @internal
     *
     * @var array<string, string>
     */
    private array $_kebabCache = [];

    /**
     * @param array<string, mixed> $fields
     *
     * @return array<string, mixed>
     *
     * @mago-ignore analysis:mixed-argument
     */
    public function camelFields(array $fields): array
    {
        $values = array_values($fields);

        // @mago-ignore analysis:less-specific-return-statement
        return $fields
            |> array_keys(...)
            |> (fn ($fields) => array_map($this->toCamel(...), $fields))
            |> (static fn ($fields) => array_combine($fields, $values));
    }

    /**
     * @param array<string, mixed> $fields
     *
     * @return array<string, mixed>
     *
     * @mago-ignore analysis:mixed-argument
     */
    public function uncamelFields(array $fields): array
    {
        $values = array_values($fields);

        // @mago-ignore analysis:less-specific-return-statement
        return $fields
            |> array_keys(...)
            |> (fn ($fields) => array_map($this->toKebab(...), $fields))
            |> (static fn ($fields) => array_combine($fields, $values));
    }

    final protected function toCamel(string $fieldName): string
    {
        if (array_key_exists($fieldName, $this->_camelCache)) {
            return $this->_camelCache[$fieldName];
        }

        $camel = Str::camel($fieldName);

        $this->_camelCache[$fieldName] = $camel;

        if (!array_key_exists($camel, $this->_kebabCache)) {
            $this->_kebabCache[$camel] = $fieldName;
        }

        return $camel;
    }

    final protected function toKebab(string $fieldName): string
    {
        if (array_key_exists($fieldName, $this->_kebabCache)) {
            return $this->_kebabCache[$fieldName];
        }

        $kebab = Str::kebab($fieldName);

        $this->_kebabCache[$fieldName] = $kebab;

        if (!array_key_exists($kebab, $this->_camelCache)) {
            $this->_camelCache[$kebab] = $fieldName;
        }

        return $kebab;
    }
}
