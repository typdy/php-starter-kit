<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Models\Concerns;

use InvalidArgumentException;
use ReflectionClass;
use ReflectionProperty;
use Typdy\StarterKit\Models\Attributes\Alias;
use Typdy\StarterKit\Models\Attributes\Relationship;
use Typdy\StarterKit\Utils\Str;

use function array_key_exists;

/**
 * Converts a named value array to and from it's aliased representation, as
 * defined by the `Alias` property attributes.
 *
 * @api
 */
trait HasAlias
{
    /**
     * @internal
     *
     * @var array<string, string>|null
     */
    private ?array $_aliasMap = null;

    /**
     * @internal
     *
     * @var array<string, list<string>>|null
     */
    private ?array $_reverseAliasMap = null;

    /**
     * @param array<string, mixed> $fields
     *
     * @return array<string, mixed>
     */
    final public function aliasFields(array $fields): array
    {
        $map = $this->getAliasMap();

        foreach ($map as $property => $alias) {
            $kebabProperty = Str::kebab($property);

            if (array_key_exists($property, $fields)) {
                $fields[$alias] = $fields[$property];

                if ($property !== $alias) {
                    unset($fields[$property]);
                }
            }

            if (array_key_exists($kebabProperty, $fields)) {
                $fields[$alias] = $fields[$kebabProperty];

                if ($kebabProperty !== $alias) {
                    unset($fields[$kebabProperty]);
                }
            }
        }

        return $fields;
    }

    /**
     * @param array<string, mixed> $fields
     *
     * @return array<string, mixed>
     */
    final public function unaliasFields(array $fields): array
    {
        $map = $this->getReverseAliasMap();

        foreach ($map as $alias => $properties) {
            if (!array_key_exists($alias, $fields)) {
                continue;
            }

            foreach ($properties as $property) {
                $fields[$property] = $fields[$alias];
            }
        }

        return $fields;
    }

    /**
     * @return array<string, string>
     */
    final protected function getAliasMap(): array
    {
        if ($this->_aliasMap !== null) {
            return $this->_aliasMap;
        }

        $map = [];

        $reflection = new ReflectionClass($this);

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $propertyAlias = null;

            $aliasAttributes = $property->getAttributes(Alias::class);
            $relationshipAttributes = $property->getAttributes(Relationship::class);

            if ($aliasAttributes !== []) {
                $attribute = $aliasAttributes[0]->newInstance();

                $propertyAlias = $attribute->name;
            }

            if ($relationshipAttributes !== []) {
                $relationship = $relationshipAttributes[0]->newInstance();

                if ($relationship->alias !== null && $propertyAlias !== null) {
                    throw new InvalidArgumentException(
                        "Property '{$property->getName()}' cannot declare both #[Relationship('...')] and #[Alias('...')].",
                    );
                }

                if ($relationship->alias !== null) {
                    $propertyAlias = $relationship->alias;
                }
            }

            if ($propertyAlias !== null) {
                $map[$property->getName()] = $propertyAlias;
            }
        }

        return $this->_aliasMap = $map;
    }

    /**
     * @return array<string, list<string>>
     */
    final protected function getReverseAliasMap(): array
    {
        if ($this->_reverseAliasMap !== null) {
            return $this->_reverseAliasMap;
        }

        $map = [];

        foreach ($this->getAliasMap() as $property => $alias) {
            $map[$alias] ??= [];
            $map[$alias][] = $property;

            $camelAlias = Str::camel($alias);

            $map[$camelAlias] ??= [];
            $map[$camelAlias][] = $property;
        }

        return $this->_reverseAliasMap = $map;
    }
}
