<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Contracts;

use Typdy\StarterKit\Data\Paginated;
use Typdy\StarterKit\Models\Contracts\Construct;
use Typdy\StarterKit\Repositories\Data\Request;

/**
 * @api
 */
interface PaginateCallback
{
    /**
     * @template TModel of Construct
     *
     * @param Paginated<TModel> $models
     * @return iterable<int, TModel>
     */
    public function __invoke(Paginated $models, Request $request): iterable;
}
