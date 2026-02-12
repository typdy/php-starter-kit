<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Parsers\Exceptions;

use Exception;

final class ResourceValidationException extends Exception
{
    /**
     * @throws self
     */
    public static function conflictingMember(string $member, string $field1, string $field2, string $path = ''): never
    {
        if ($path !== '') {
            $path .= '->';
        }

        throw new self(
            "The JSON:API resource member '{$member}' cannot exist in both of the '{$path}{$field1}' and '{$path}{$field2}' fields.",
        );
    }

    /**
     * @throws self
     */
    public static function invalidMemberType(string $member, string $expected, string $actual, string $path = ''): never
    {
        if ($path !== '') {
            $path = ", in '{$path}'";
        }

        throw new self(
            "Expected JSON:API resource member '{$member}' to be of type '{$expected}', got '{$actual}' instead{$path}.",
        );
    }

    /**
     * @throws self
     */
    public static function invalidResourceType(string $actual, string $path = ''): never
    {
        if ($path !== '') {
            $path = ", in '{$path}'";
        }

        throw new self("Expected JSON:API resource to be of type 'object', got '{$actual}' instead{$path}.");
    }

    /**
     * @throws self
     */
    public static function missingRelationshipMembers(string $path = ''): never
    {
        if ($path !== '') {
            $path = " In '{$path}'.";
        }

        throw new self(
            "A valid JSON:API relationship must contain at least one of the following members: 'data', 'links' or 'meta'.{$path}",
        );
    }

    /**
     * @throws self
     */
    public static function missingRequiredMember(string $member, string $path = ''): never
    {
        if ($path !== '') {
            $path = ", in '{$path}'";
        }

        throw new self("A valid JSON:API resource must contain the required member '{$member}'{$path}.");
    }

    /**
     * @throws self
     */
    public static function prohibitedMember(string $member, string $field, string $path = ''): never
    {
        if ($path !== '') {
            $path .= '->';
        }

        throw new self("The JSON:API resource member '{$member}' is prohibited under the '{$path}{$field}' field.");
    }
}
