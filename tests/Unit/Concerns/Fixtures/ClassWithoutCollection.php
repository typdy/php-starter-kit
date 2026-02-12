<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Tests\Unit\Concerns\Fixtures;

use Typdy\StarterKit\Attributes\Blueprint;
use Typdy\StarterKit\Concerns\HasBlueprint;
use Typdy\StarterKit\Concerns\HasCollection;

#[Blueprint('test-blueprint')]
class ClassWithoutCollection
{
    use HasBlueprint;
    use HasCollection;

    public static array $macros = [];
}
