<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Models;

use Carbon\Carbon;
use InvalidArgumentException;
use JsonSerializable;
use Override;
use Typdy\StarterKit\Concerns\HasBlueprint;
use Typdy\StarterKit\Concerns\HasProject;
use Typdy\StarterKit\Models\Concerns\HasAlias;
use Typdy\StarterKit\Models\Concerns\HasCamelFields;
use Typdy\StarterKit\Models\Concerns\HasMeta;
use Typdy\StarterKit\Models\Concerns\HasRelationships;
use Typdy\StarterKit\Models\Concerns\HasSignature;
use Typdy\StarterKit\Models\Concerns\SerialisesConstruct;
use Typdy\StarterKit\Models\Contracts\TypdyModel;
use Typdy\StarterKit\Parsers\Data\Relation;
use Typdy\StarterKit\Parsers\Data\Resource;

use function property_exists;

/**
 * @api
 */
abstract class Model implements TypdyModel, JsonSerializable
{
    use HasAlias;
    use HasBlueprint;
    use HasCamelFields;
    use HasMeta;
    use HasProject;
    use HasRelationships;
    use HasSignature;
    use SerialisesConstruct;

    public protected(set) ?Resource $resource = null;

    public protected(set) ?int $id = null;

    public ?string $identifier = null;

    public ?Carbon $created = null {
        set(string|Carbon|null $value) {
            if ($value !== null) {
                $this->created = Carbon::parse($value);
            }

            $this->created = null;
        }
    }

    public ?Carbon $updated = null {
        set(string|Carbon|null $value) {
            if ($value !== null) {
                $this->updated = Carbon::parse($value);
            }

            $this->updated = null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function getSyncHeaders(): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function getSyncParameters(): array
    {
        return [];
    }

    /**
     * @return $this
     */
    #[Override]
    public function hydrateFromResource(Resource $resource): self
    {
        if ($resource->type !== $this->getBlueprint()) {
            throw new InvalidArgumentException(
                "Cannot hydrate a '{$this->getBlueprint()}' with a resource of type '{$resource->type}'.",
            );
        }

        $this->resource = $resource;

        $this->id = (int) $resource->id;

        $attributes = [...$resource->attributes, ...$resource->relationships]
            |> $this->camelFields(...)
            |> $this->unaliasFields(...);

        $this->clearRelationshipMeta();

        // @mago-expect analysis:mixed-assignment
        foreach ($attributes as $key => $value) {
            if ($value instanceof Relation) {
                $value = $this->hydrateRelated($value);
            }

            if (property_exists($this, $key)) {
                // @mago-expect analysis:string-member-selector
                $this->$key = $value;
            }
        }

        $this->meta = $resource->meta;

        return $this;
    }

    #[Override]
    public function isGlobal(): bool
    {
        // @mago-expect analysis:mixed-operand
        return (bool) ($this->meta['global'] ?? false);
    }

    #[Override]
    public function isNew(): bool
    {
        return $this->id === null;
    }
}
