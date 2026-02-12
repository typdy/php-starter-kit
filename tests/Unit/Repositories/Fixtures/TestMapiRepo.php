<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Tests\Unit\Repositories\Fixtures;

use Typdy\StarterKit\Attributes\Blueprint;
use Typdy\StarterKit\Repositories\Repository;

#[Blueprint('test')]
class TestMapiRepo extends Repository
{
    protected bool $mapi = true;
}
