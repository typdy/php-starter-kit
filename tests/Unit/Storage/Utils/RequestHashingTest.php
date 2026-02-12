<?php

declare(strict_types=1);

use Typdy\StarterKit\Repositories\Data\Request;
use Typdy\StarterKit\Storage\Utils\RequestHashing;

it('produces stable hash keys', function () {
    $requestA = new Request(
        team: 'test-team',
        project: 'test-project',
        query: ['parameters' => ['b' => 2, 'a' => 1], 'id' => 123],
    );

    $requestB = new Request(
        team: 'test-team',
        project: 'test-project',
        query: ['id' => 123, 'parameters' => ['a' => 1, 'b' => 2]],
    );

    $normalizedA = RequestHashing::hash($requestA);
    $normalizedB = RequestHashing::hash($requestB);

    expect($normalizedA)->toBe($normalizedB);
});
