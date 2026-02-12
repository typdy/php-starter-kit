<?php

declare(strict_types=1);

use Typdy\StarterKit\Helpers\ArrayMapper;

/**
 * @mago-expect lint:file-name
 */
class TestClass
{
    public int $a = 1;

    public string $b = 'foo';

    // @mago-expect lint:no-literal-password
    protected string $secret = 'hidden';

    protected string $uninitialized;

    private int $hidden = 42;
}

it('maps public properties to array', function () {
    $obj = new TestClass();

    $result = new ArrayMapper($obj)->toArray();

    expect($result)->toBe([
        'a' => 1,
        'b' => 'foo',
    ]);
});

it('excludes properties', function () {
    $obj = new TestClass();

    $result = new ArrayMapper($obj, without: ['a'])->toArray();

    expect($result)->toBe([
        'b' => 'foo',
    ]);
});

it('includes non-public properties', function () {
    $obj = new TestClass();

    $result = new ArrayMapper($obj, includeNonPublic: ['secret', 'hidden'])->toArray();

    expect($result)->toBe([
        'a' => 1,
        'b' => 'foo',
        // @mago-expect lint:no-literal-password
        'secret' => 'hidden',
        'hidden' => 42,
    ]);
});

it('maps nested objects and arrays', function () {
    $obj = new class {
        public $x = [1, 2, 3];

        public $y;

        public function __construct()
        {
            $this->y = new TestClass();
        }
    };

    $result = new ArrayMapper($obj)->toArray();

    expect($result)->toBe([
        'x' => [1, 2, 3],
        'y' => [
            'a' => 1,
            'b' => 'foo',
        ],
    ]);
});

it('throws for non-existent non-public property', function () {
    $obj = new TestClass();

    new ArrayMapper($obj, includeNonPublic: ['doesNotExist'])->toArray();
})->throws(
    InvalidArgumentException::class,
    "Cannot include non-public property 'doesNotExist' because it does not exist on class 'TestClass'.",
);

it('throws for uninitialized non-public property', function () {
    $obj = new TestClass();

    new ArrayMapper($obj, includeNonPublic: ['uninitialized'])->toArray();
})->throws(
    InvalidArgumentException::class,
    "Cannot include non-public property 'uninitialized' because it is not initialized on the given object.",
);

it('handles empty object', function () {
    $obj = (object) [];

    $result = new ArrayMapper($obj)->toArray();

    expect($result)->toBeEmpty();
});

it('handles object with only non-public properties', function () {
    $obj = new class {
        protected $foo = 'bar';

        private $baz = 123;
    };

    $result = new ArrayMapper($obj, includeNonPublic: ['foo', 'baz'])->toArray();

    expect($result)->toBe([
        'foo' => 'bar',
        'baz' => 123,
    ]);
});

it('handles array of objects', function () {
    $objs = [new TestClass(), new TestClass()];

    $result = new ArrayMapper($objs)->toArray();

    expect($result)->toBe([
        [
            'a' => 1,
            'b' => 'foo',
        ],
        [
            'a' => 1,
            'b' => 'foo',
        ],
    ]);
});

it('handles deeply nested structures', function () {
    $obj = new class {
        public $nested;

        public function __construct()
        {
            $this->nested = [
                new TestClass(),
                ['foo' => new TestClass()],
            ];
        }
    };

    $result = new ArrayMapper($obj)->toArray();

    expect($result)->toBe([
        'nested' => [
            [
                'a' => 1,
                'b' => 'foo',
            ],
            [
                'foo' => [
                    'a' => 1,
                    'b' => 'foo',
                ],
            ],
        ],
    ]);
});

it('throws on circular object references', function () {
    $node = new class {
        public ?object $self = null;
    };

    $node->self = $node;

    new ArrayMapper($node)->toArray();
})->throws(RuntimeException::class, 'Circular reference detected while mapping object of class');

it('throws when max depth is exceeded', function () {
    $value = ['leaf'];

    for ($i = 0; $i < 3; $i++) {
        $value = [$value];
    }

    new ArrayMapper($value, maxDepth: 2)->toArray();
})->throws(RuntimeException::class, 'Maximum mapping depth of 2 exceeded.');

it('returns scalar value wrapped in an array', function () {
    expect(new ArrayMapper(123)->toArray())->toBe([123]);
    expect(new ArrayMapper(null)->toArray())->toBe([null]);
});
