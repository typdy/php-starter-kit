<?php

declare(strict_types=1);

use Typdy\StarterKit\Models\Attributes\Alias;
use Typdy\StarterKit\Models\Concerns\HasAlias;

it('aliases and unaliases fields', function () {
    $model = new class {
        use HasAlias;

        #[Alias('heading')]
        public ?string $title = null;
    };

    $fields = ['title' => 'The best blog post ever!'];

    $aliased = $model->aliasFields($fields);
    $unalias = $model->unaliasFields(['heading' => 'The best blog post ever!']);

    expect($aliased)->toHaveKey('heading');
    expect($unalias)->toHaveKey('title');
});
