<?php

declare(strict_types=1);

use Typdy\StarterKit\Tests\Unit\Concerns\Fixtures\ClassWithoutProject;
use Typdy\StarterKit\Tests\Unit\Concerns\Fixtures\ClassWithProject;
use Typdy\StarterKit\Typdy;
use Typdy\StarterKit\TypdyConfig;

beforeEach(function () {
    Typdy::$config = new TypdyConfig(
        team: 'config-team',
        project: 'config-project',
    );
});

it('returns the project attribute values', function () {
    $class = new ClassWithProject();

    expect($class->getTeam())->toBe('test-team');
    expect($class->getProject())->toBe('test-project');
});

it('falls back to the configured project and team', function () {
    $class = new ClassWithoutProject();

    expect($class->getTeam())->toBe('config-team');
    expect($class->getProject())->toBe('config-project');
});
