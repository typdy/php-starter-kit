<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Tests\Unit\Resolvers\Fixtures\Models;

use Typdy\StarterKit\Attributes\Blueprint;
use Typdy\StarterKit\Attributes\Project;
use Typdy\StarterKit\Models\Model;

#[Project('the-a-team', 'project-x')]
#[Blueprint('bar')]
class Bar extends Model {}
