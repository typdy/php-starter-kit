<?php

declare(strict_types=1);

use Typdy\StarterKit\Models\Concerns\HasCamelFields;

it('converts fields to camelCase and back', function () {
    $model = new class {
        use HasCamelFields;
    };

    $snake = ['cover-image' => 1, 'thumb-image' => 2];

    $camel = $model->camelFields($snake);
    $uncamel = $model->uncamelFields($camel);

    expect($camel)->toHaveKey('coverImage')->toHaveKey('thumbImage');
    expect($uncamel)->toHaveKey('cover-image')->toHaveKey('thumb-image');
});
