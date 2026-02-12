<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Parsers;

use Override;
use Typdy\StarterKit\Parsers\Contracts\MetaParser;
use Typdy\StarterKit\Parsers\Data\Error;
use Typdy\StarterKit\Parsers\Exceptions\ErrorsValidationException;
use Typdy\StarterKit\Parsers\Exceptions\MetaValidationException;

use function array_map;
use function array_values;
use function count;
use function gettype;
use function is_array;
use function is_object;
use function is_string;
use function property_exists;

final readonly class ErrorsParser implements Contracts\ErrorsParser
{
    public function __construct(
        private MetaParser $metaParser,
    ) {}

    /**
     * @throws ErrorsValidationException
     * @throws MetaValidationException
     */
    #[Override]
    public function parse(mixed $errors): array
    {
        $validated = $this->validateErrors($errors);

        return array_map(
            /**
             * @param object{
             *     id?: string,
             *     status?: string,
             *     code?: string,
             *     title?: string,
             *     detail?: string,
             *     meta?: object,
             *     ...
             * } $error
             *
             * @throws MetaValidationException
             */
            fn (object $error): Error => new Error(
                $error->id ?? null,
                $error->status ?? null,
                $error->code ?? null,
                $error->title ?? null,
                $error->detail ?? null,
                $this->parseMeta($error),
            ),
            $validated,
        )
            |> array_values(...);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws MetaValidationException
     */
    private function parseMeta(object $error): array
    {
        if (!property_exists($error, 'meta')) {
            return [];
        }

        return $this->metaParser->parse($error->meta);
    }

    /**
     * @return array<int, object{
     *     id?: string,
     *     status?: string,
     *     code?: string,
     *     title?: string,
     *     detail?: string,
     *     source?: object,
     *     links?: object,
     *     meta?: object,
     * }>
     *
     * @throws ErrorsValidationException
     */
    private function validateErrors(mixed $errors): array
    {
        if (!is_array($errors)) {
            ErrorsValidationException::invalidErrorsType(gettype($errors));
        }

        if (count($errors) === 0) {
            ErrorsValidationException::emptyErrorsArray();
        }

        // @mago-expect analysis:mixed-assignment
        foreach (array_values($errors) as $i => $error) {
            if (!is_object($error)) {
                ErrorsValidationException::invalidErrorItemType($i, gettype($error));
            }

            $hasMember = false;

            foreach ([
                'id',
                'status',
                'code',
                'title',
                'detail',
            ] as $member) {
                if (!property_exists($error, $member)) {
                    continue;
                }

                // @mago-expect analysis:ambiguous-object-property-access
                if (!is_string($error->$member)) {
                    ErrorsValidationException::invalidErrorMemberType($i, $member, gettype($error->$member));
                }

                $hasMember = true;
            }

            if (
                !$hasMember
                && !property_exists($error, 'source')
                && !property_exists($error, 'links')
                && !property_exists($error, 'meta')
            ) {
                ErrorsValidationException::errorItemMissingMembers($i);
            }
        }

        // @mago-expect analysis:less-specific-return-statement
        return $errors;
    }
}
