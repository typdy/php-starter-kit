<?php

declare(strict_types=1);

use Typdy\StarterKit\Helpers\JsonMapper;

it('converts an object to JSON', function () {
    $obj = (object) ['foo' => 'bar', 'baz' => 42];

    $result = new JsonMapper($obj)->toJson();

    expect($result)->toBe(json_encode($obj, JSON_THROW_ON_ERROR));
});

it('forwards without and includeNonPublic options to ArrayMapper', function () {
    $obj = new class {
        public string $foo = 'bar';

        public int $removeMe = 123;

        // @mago-expect lint:no-literal-password
        protected string $secret = 'hidden';
    };

    $json = new JsonMapper($obj, without: ['removeMe'], includeNonPublic: ['secret'])->toJson();

    expect(json_decode($json, associative: true, flags: JSON_THROW_ON_ERROR))->toBe([
        'foo' => 'bar',
        // @mago-expect lint:no-literal-password
        'secret' => 'hidden',
    ]);
});

it('forwards maxDepth option to ArrayMapper', function () {
    $value = ['leaf'];

    for ($i = 0; $i < 3; $i++) {
        $value = [$value];
    }

    new JsonMapper($value, maxDepth: 2)->toJson();
})->throws(RuntimeException::class, 'Maximum mapping depth of 2 exceeded.');

it('throws an exception when JSON encoding fails', function () {
    $obj = (object) ['foo' => "\xB1\x31"];

    new JsonMapper($obj)->toJson();
})->throws(JsonException::class);
