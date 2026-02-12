<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Tests\Unit\Concerns\Fixtures;

use Typdy\StarterKit\Attributes\Project;
use Typdy\StarterKit\Concerns\HasProject;

#[Project('test-team', 'test-project')]
class ClassWithProject
{
    use HasProject;
}
