<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Tests\Unit\Resolvers\Fixtures\Repositories;

use Typdy\StarterKit\Attributes\Project;
use Typdy\StarterKit\Repositories\GlobalRepository as TypdyGlobalRepository;

#[Project('the-a-team', 'project-x')]
class GlobalRepository extends TypdyGlobalRepository
{
    public function __construct() {}
}
