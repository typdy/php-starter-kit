<?php

declare(strict_types=1);

use Typdy\StarterKit\Repositories\Data\Request;
use Typdy\StarterKit\Storage\Utils\RequestNormalization;

it('sorts request queries', function () {
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

    $normalizedA = RequestNormalization::sortQuery($requestA);
    $normalizedB = RequestNormalization::sortQuery($requestB);

    expect($normalizedA->query)->toBe($normalizedB->query);
});
