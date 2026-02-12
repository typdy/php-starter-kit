<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Tests\Unit\Concerns\Fixtures;

use Typdy\StarterKit\Attributes\Blueprint;
use Typdy\StarterKit\Concerns\HasBlueprint;

#[Blueprint('test-blueprint')]
class ClassWithBlueprint
{
    use HasBlueprint;
}
