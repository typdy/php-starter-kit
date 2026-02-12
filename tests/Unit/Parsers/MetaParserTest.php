<?php

declare(strict_types=1);

use Typdy\StarterKit\Parsers\Exceptions\MetaValidationException;
use Typdy\StarterKit\Parsers\MetaParser;

it('parses meta object', function () {
    $meta = new MetaParser()->parse((object) [
        'something' => 'value',
    ]);

    expect($meta)->toHaveKey('something');
});

it('throws if meta is not an object', function () {
    $meta = new MetaParser()->parse('not-an-object');
})->throws(MetaValidationException::class, "Expected JSON:API meta to be of type 'object', got 'string' instead.");
