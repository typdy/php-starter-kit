<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Concerns;

use Closure;
use ReflectionClass;
use Typdy\StarterKit\Attributes\Collection;

use function array_key_exists;
use function count;
use function is_array;
use function property_exists;

/**
 * Returns a collection name from the `Collection` class attribute or guesses it
 * from the blueprint or macro.
 *
 * @api
 */
trait HasCollection
{
    /**
     * @internal
     */
    private ?string $_collection = null;

    abstract public function getBlueprint(): string;

    public function getCollection(): string
    {
        if ($this->_collection === null) {
            $reflection = new ReflectionClass($this);
            $attributes = $reflection->getAttributes(Collection::class);

            if (count($attributes) === 0) {
                return $this->_collection = $this->guessCollection();
            }

            $this->_collection = $attributes[0]->newInstance()->collection;
        }

        return $this->_collection;
    }

    protected function guessCollection(): string
    {
        $macros = [];

        if (property_exists(static::class, 'macros')) {
            /** @var array<string, Closure(): string> $macros */
            // @mago-ignore analysis:all
            $macros = is_array(static::$macros) ? static::$macros : [];
        }

        if (array_key_exists('guessCollection', $macros)) {
            $macro = $macros['guessCollection'];

            return $macro();
        }

        return $this->getBlueprint() . 's';
    }
}
