<?php

declare(strict_types=1);

use Typdy\StarterKit\Utils\Str;

it('converts to kebab-case', function () {
    expect(Str::kebab('fooBar'))->toBe('foo-bar');
    expect(Str::kebab('FooBarBaz'))->toBe('foo-bar-baz');
    expect(Str::kebab('already-kebab'))->toBe('already-kebab');
    expect(Str::kebab('foo_bar'))->toBe('foo_bar');
    expect(Str::kebab('simple'))->toBe('simple');
});

it('converts to StudlyCase', function () {
    expect(Str::studly('foo-bar'))->toBe('FooBar');
    expect(Str::studly('foo_bar'))->toBe('FooBar');
    expect(Str::studly('foo bar'))->toBe('FooBar');
    expect(Str::studly('fooBar'))->toBe('FooBar');
    expect(Str::studly('FOO-BAR'))->toBe('FOOBAR');
});

it('converts to camelCase', function () {
    expect(Str::camel('foo-bar'))->toBe('fooBar');
    expect(Str::camel('foo_bar'))->toBe('fooBar');
    expect(Str::camel('foo bar'))->toBe('fooBar');
    expect(Str::camel('FooBar'))->toBe('fooBar');
    expect(Str::camel('FOO-BAR'))->toBe('fOOBAR');
});

it('converts to lowercase (UTF-8 safe)', function () {
    expect(Str::lower('FOO'))->toBe('foo');
    expect(Str::lower('Bär'))->toBe('bär');
    expect(Str::lower('Straße'))->toBe('straße');
});
