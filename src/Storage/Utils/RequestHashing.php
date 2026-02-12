<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Storage\Utils;

use Typdy\StarterKit\Repositories\Data\Request;
use Typdy\StarterKit\Storage\RequestMutationPipeline;

use function hash;
use function json_encode;
use function substr;

use const JSON_THROW_ON_ERROR;

final readonly class RequestHashing
{
    public static function hash(Request $request): string
    {
        $request = new RequestMutationPipeline()->mutate($request);

        $request = RequestNormalization::sortQuery($request)->toArray();

        return hash('sha256', json_encode($request, JSON_THROW_ON_ERROR));
    }

    /**
     * @return array{0: string, 1: string}
     */
    public static function shard(string $hash, string $separator = '/'): array
    {
        $l1 = substr($hash, offset: 0, length: 2);
        $l2 = substr($hash, offset: 2, length: 2);

        return ["{$l1}{$separator}{$l2}", $hash];
    }
}
