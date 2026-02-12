<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Storage\Utils;

use Typdy\StarterKit\Repositories\Data\Request;
use Typdy\StarterKit\Utils\Arr;

final readonly class RequestNormalization
{
    public static function sortQuery(Request $request): Request
    {
        $request->query = Arr::deepSort($request->query);

        return $request;
    }
}
