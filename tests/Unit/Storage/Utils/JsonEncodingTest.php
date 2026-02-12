<?php

declare(strict_types=1);

use Typdy\StarterKit\Storage\Utils\JsonEncoding;

it('encodes arrays as pretty-printed json', function () {
    $json = JsonEncoding::pretty(['foo' => 'bar']);

    expect($json)
        ->toContain("\n")
        ->and($json)
        ->toContain('    "foo": "bar"');
});
