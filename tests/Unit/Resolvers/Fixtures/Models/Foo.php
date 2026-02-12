<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Tests\Unit\Resolvers\Fixtures\Models;

use Typdy\StarterKit\Attributes\Blueprint;
use Typdy\StarterKit\Models\Model;

#[Blueprint('foo')]
class Foo extends Model {}
