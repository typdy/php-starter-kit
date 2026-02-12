<?php

declare(strict_types=1);

use Typdy\StarterKit\Webhooks\Data\Result;
use Typdy\StarterKit\Webhooks\ResultSet;

it('creates results from a result list', function () {
    $list = [
        new Result('Foo successfully bared', failed: false),
        new Result('Foobar failed', failed: true),
    ];

    $set = new ResultSet($list);

    expect($set->results)->toHaveCount(2);
    expect($set->results)->toMatchArray($list);
});

it('creates results from an array', function () {
    $array = [
        'Foo successfully bared',
        'foobar failed' => true,
    ];

    $set = new ResultSet($array);

    expect($set->results)->toHaveCount(2);
    expect($set->results)->toMatchArray([
        new Result('Foo successfully bared', failed: false),
        new Result('foobar failed', failed: true),
    ]);
});
