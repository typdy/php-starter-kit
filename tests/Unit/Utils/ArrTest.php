<?php

declare(strict_types=1);

use Typdy\StarterKit\Utils\Arr;

it('deepSorts arrays recursively by keys', function () {
    $input = [
        'b' => ['d' => 4, 'c' => 3],
        'a' => ['b' => 2, 'a' => 1],
    ];
    $expected = [
        'a' => ['a' => 1, 'b' => 2],
        'b' => ['c' => 3, 'd' => 4],
    ];
    expect(Arr::deepSort($input))->toBe($expected);
});

it('deepSorts non-nested arrays', function () {
    $input = ['b' => 2, 'a' => 1];
    $expected = ['a' => 1, 'b' => 2];
    expect(Arr::deepSort($input))->toBe($expected);
});

it('returns empty array for empty deepSort input', function () {
    expect(Arr::deepSort([]))->toBeEmpty();
});

it('does not wrap an array', function () {
    $input = [1, 2, 3];
    expect(Arr::wrap($input))->toBe($input);
});

it('it wrap non-array values in an array', function () {
    expect(Arr::wrap('foo'))->toBe(['foo']);
    expect(Arr::wrap(null))->toBe([null]);
    expect(Arr::wrap(123))->toBe([123]);
});
