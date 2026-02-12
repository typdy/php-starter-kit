<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Concerns;

use InvalidArgumentException;
use ReflectionClass;
use Typdy\StarterKit\Attributes\Blueprint;

use function count;

/**
 * Returns a blueprint name from the `Blueprint` class attribute.
 *
 * @api
 */
trait HasBlueprint
{
    /**
     * @internal
     */
    private ?string $_blueprint = null;

    public function getBlueprint(): string
    {
        if ($this->_blueprint === null) {
            $reflection = new ReflectionClass($this);
            $attributes = $reflection->getAttributes(Blueprint::class);

            if (count($attributes) === 0) {
                throw new InvalidArgumentException("Blueprint attribute not found on '{$reflection->getName()}'.");
            }

            $this->_blueprint = $attributes[0]->newInstance()->blueprint;
        }

        return $this->_blueprint;
    }
}
