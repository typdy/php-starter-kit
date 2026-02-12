<?php

declare(strict_types=1);

use Typdy\StarterKit\Tests\Unit\Concerns\Fixtures\ClassWithBlueprint;
use Typdy\StarterKit\Tests\Unit\Concerns\Fixtures\ClassWithoutBlueprint;

it('returns the blueprint attribute value', function () {
    expect(new ClassWithBlueprint()->getBlueprint())->toBe('test-blueprint');
});

it('throws an exception if the class does not have a blueprint attribute', function () {
    new ClassWithoutBlueprint()->getBlueprint();
})->throws(InvalidArgumentException::class);
