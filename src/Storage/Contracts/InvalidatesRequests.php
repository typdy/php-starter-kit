<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Storage\Contracts;

use Typdy\StarterKit\Repositories\Contracts\Collection;
use Typdy\StarterKit\Repositories\Data\Request;

/**
 * @api
 */
interface InvalidatesRequests
{
    public function invalidate(Collection $repository, Request $request): void;
}
