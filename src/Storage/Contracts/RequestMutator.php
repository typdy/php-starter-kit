<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Storage\Contracts;

use Typdy\StarterKit\Repositories\Data\Request;

/**
 * @api
 */
interface RequestMutator
{
    public function mutate(Request $request): Request;
}
