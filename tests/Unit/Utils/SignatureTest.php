<?php

declare(strict_types=1);

use Typdy\StarterKit\Attributes\Blueprint;
use Typdy\StarterKit\Models\Model;
use Typdy\StarterKit\Parsers\Data\Resource;
use Typdy\StarterKit\Repositories\Contracts\Collection;
use Typdy\StarterKit\Typdy;
use Typdy\StarterKit\TypdyConfig;
use Typdy\StarterKit\Utils\Signature;

beforeEach(function () {
    Typdy::$config = new TypdyConfig(
        team: 'team',
        project: 'project',
        privateStoragePath: sys_get_temp_dir(),
    );

    $this->makeRepository = fn (string $signature = 'team:project:test') => mock(Collection::class)
        ->shouldReceive('getSignature')
        ->andReturn($signature)
        ->getMock();

    $this->makeModel = function () {
        $model = new
            #[Blueprint('test')]
            class extends Model {
                public ?string $title = null;
            };

        return $model->hydrateFromResource(new Resource(
            type: 'test',
            id: '1',
            attributes: ['title' => 'Hello'],
        ));
    };
});

it('throws when a model signature does not match the repository signature', function () {
    $model = ($this->makeModel)();

    Signature::validate(($this->makeRepository)(), $model);

    Signature::validate(($this->makeRepository)('team:project:other'), $model);
})->throws(InvalidArgumentException::class, "Construct signature 'team:project:test'");
