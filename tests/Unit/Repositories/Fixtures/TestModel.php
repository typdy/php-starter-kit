<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Tests\Unit\Repositories\Fixtures;

use Typdy\StarterKit\Attributes\Blueprint;
use Typdy\StarterKit\Models\Model;

#[Blueprint('test')]
class TestModel extends Model
{
    public ?string $title = null;
}
