<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Tests\Unit\Resolvers\Fixtures\Repositories;

use Typdy\StarterKit\Attributes\Blueprint;
use Typdy\StarterKit\Repositories\Repository;

#[Blueprint('foo')]
class FooRepository extends Repository
{
    public function __construct() {}
}
