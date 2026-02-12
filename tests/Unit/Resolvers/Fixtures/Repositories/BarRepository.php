<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Tests\Unit\Resolvers\Fixtures\Repositories;

use Typdy\StarterKit\Attributes\Blueprint;
use Typdy\StarterKit\Attributes\Project;
use Typdy\StarterKit\Repositories\Repository;

#[Project('the-a-team', 'project-x')]
#[Blueprint('bar')]
class BarRepository extends Repository
{
    public function __construct() {}
}
