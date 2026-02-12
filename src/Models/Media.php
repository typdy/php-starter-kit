<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Models;

use Typdy\StarterKit\Attributes\Blueprint;

use function array_filter;
use function array_first;
use function str_starts_with;

use const ARRAY_FILTER_USE_BOTH;

/**
 * @api
 *
 * @mago-ignore analysis:all
 */
#[Blueprint('media')]
class Media extends Model
{
    public ?string $name = null;

    public ?string $url = null;

    public ?object $conversions = null {
        /**
         * @param object|array<string, string>|null $value
         */
        set(object|array|null $value) {
            if ($value === null) {
                $this->conversions = null;

                return;
            }

            $this->conversions = (object) $this->camelFields((array) $value);
        }
    }

    /**
     * @var list<string>
     */
    public array $conversionsInProgress = [];

    public ?string $constraintUrl {
        get {
            if ($this->conversions === null) {
                return null;
            }

            return array_first(
                array_filter(
                    (array) $this->conversions,
                    static fn (string $url, string $name): bool => str_starts_with($name, 'constraint'),
                    ARRAY_FILTER_USE_BOTH,
                ),
            );
        }
    }
}
