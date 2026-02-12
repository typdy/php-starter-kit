<?php

declare(strict_types=1);

use Typdy\StarterKit\Models\Concerns\HasMeta;

it('has a meta property', function () {
    $model = new class {
        use HasMeta;
    };

    $model->meta = ['global' => true];

    expect($model->meta)->toBe(['global' => true]);
});
