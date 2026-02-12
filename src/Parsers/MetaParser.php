<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Parsers;

use Override;
use Typdy\StarterKit\Parsers\Exceptions\MetaValidationException;

use function get_object_vars;
use function gettype;
use function is_object;

final readonly class MetaParser implements Contracts\MetaParser
{
    #[Override]
    public function parse(mixed $meta): array
    {
        $validated = $this->validateMeta($meta);

        $normalizedMeta = [];

        // @mago-expect analysis:mixed-assignment
        foreach (get_object_vars($validated) as $key => $value) {
            $normalizedMeta[(string) $key] = $value;
        }

        return $normalizedMeta;
    }

    /**
     * @return object
     *
     * @throws MetaValidationException
     */
    private function validateMeta(mixed $meta): object
    {
        if (!is_object($meta)) {
            MetaValidationException::invalidMetaType(gettype($meta));
        }

        return $meta;
    }
}
