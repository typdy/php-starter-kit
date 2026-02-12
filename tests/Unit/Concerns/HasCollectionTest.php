<?php

declare(strict_types=1);

use Typdy\StarterKit\Tests\Unit\Concerns\Fixtures\ClassWithCollection;
use Typdy\StarterKit\Tests\Unit\Concerns\Fixtures\ClassWithoutCollection;

it('returns the collection attribute value', function () {
    expect(new ClassWithCollection()->getCollection())->toBe('test-collection');
});

it('guesses the collection name from the blueprint attribute value', function () {
    expect(new ClassWithoutCollection()->getCollection())->toBe('test-blueprints');
});

it('guesses the collection name using a macro', function () {
    $class = new ClassWithoutCollection();

    $class::$macros['guessCollection'] = fn () => 'macro-collection';

    expect($class->getCollection())->toBe('macro-collection');
});
