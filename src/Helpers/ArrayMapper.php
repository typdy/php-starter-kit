<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Helpers;

use InvalidArgumentException;
use ReflectionClass;
use ReflectionProperty;
use RuntimeException;
use stdClass;
use WeakMap;

use function get_object_vars;
use function is_iterable;
use function is_object;
use function is_scalar;

final class ArrayMapper
{
    /**
     * @var WeakMap<object, true>
     */
    private WeakMap $visited;

    /**
     * @param mixed $from Value to map. Scalars and null are returned by toArray() as single-element arrays.
     * @param list<string> $without
     * @param list<string> $includeNonPublic
     */
    public function __construct(
        private mixed $from,
        private array $without = [],
        private array $includeNonPublic = [],
        private int $maxDepth = 512,
    ) {
        $this->visited = new WeakMap();
    }

    /**
     * Maps the configured input value to an array representation.
     *
     * Expected behaviour:
     * - Objects are converted to associative arrays of mapped properties.
     * - Iterables are converted to arrays with recursively mapped values.
     * - Scalar and null inputs are wrapped in a single-element array.
     *
     * @return array<array-key, mixed>
     */
    public function toArray(): array
    {
        $value = $this->mapValue($this->from, top: true);

        if ($value === null || is_scalar($value)) {
            return [$value];
        }

        return $value;
    }

    /**
     * @param iterable<array-key, mixed> $iterable
     *
     * @return array<array-key, mixed>
     */
    private function mapIterableToArray(iterable $iterable, bool $top, int $depth): array
    {
        $mapped = [];

        // @mago-expect analysis:mixed-assignment
        foreach ($iterable as $key => $value) {
            $mapped[$key] = $this->mapValue($value, depth: $depth + 1);
        }

        if (!$top) {
            return $mapped;
        }

        foreach ($this->without as $name) {
            unset($mapped[$name]);
        }

        return $mapped;
    }

    private function mapNonPublicProperty(object $object, string $name, int $depth): mixed
    {
        $class = new ReflectionClass($object);

        if (!$class->hasProperty($name)) {
            throw new InvalidArgumentException(
                "Cannot include non-public property '{$name}' because it does not exist on class '{$class->getName()}'.",
            );
        }

        $property = $class->getProperty($name);

        if (!$property->isInitialized($object)) {
            throw new InvalidArgumentException(
                "Cannot include non-public property '{$name}' because it is not initialized on the given object.",
            );
        }

        return $this->mapValue($property->getValue($object), depth: $depth + 1);
    }

    /**
     * @return array<array-key, mixed>
     */
    private function mapObjectToArray(object $object, bool $top, int $depth): array
    {
        // @mago-expect lint:no-isset
        if (isset($this->visited[$object])) {
            throw new RuntimeException(
                "Circular reference detected while mapping object of class '" . $object::class . "' to array.",
            );
        }

        $this->visited[$object] = true;

        try {
            $mapped = [];

            $class = new ReflectionClass($object);

            $properties = $class->getProperties(ReflectionProperty::IS_PUBLIC);

            foreach ($properties as $property) {
                if (!$property->isInitialized($object)) {
                    continue;
                }

                $mapped[$property->getName()] = $this->mapValue($property->getValue($object), depth: $depth + 1);
            }

            if (!$top) {
                return $mapped;
            }

            foreach ($this->without as $name) {
                unset($mapped[$name]);
            }

            foreach ($this->includeNonPublic as $name) {
                $mapped[$name] = $this->mapNonPublicProperty($object, $name, $depth + 1);
            }

            return $mapped;
        } finally {
            unset($this->visited[$object]);
        }
    }

    /**
     * @return array<array-key, mixed>|string|int|float|bool|null
     */
    private function mapValue(mixed $value, bool $top = false, int $depth = 0): array|string|int|float|bool|null
    {
        if ($depth > $this->maxDepth) {
            throw new RuntimeException("Maximum mapping depth of {$this->maxDepth} exceeded.");
        }

        if ($value instanceof stdClass) {
            return get_object_vars($value);
        }

        if (is_object($value)) {
            return $this->mapObjectToArray($value, $top, $depth);
        }

        if (is_iterable($value)) {
            // @mago-expect analysis:less-specific-nested-argument-type
            return $this->mapIterableToArray($value, $top, $depth);
        }

        if (is_scalar($value)) {
            // @mago-expect analysis:never-return false positive, obviously reachable
            return $value;
        }

        return null;
    }
}
