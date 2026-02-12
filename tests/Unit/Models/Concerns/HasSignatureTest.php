<?php

declare(strict_types=1);

use Typdy\StarterKit\Attributes\Blueprint;
use Typdy\StarterKit\Attributes\Project;
use Typdy\StarterKit\Concerns\HasBlueprint;
use Typdy\StarterKit\Concerns\HasProject;
use Typdy\StarterKit\Models\Concerns\HasSignature;

it('generates a signature', function () {
    $model = new
        #[Project('our-team', 'my-project')]
        #[Blueprint('page')]
        class {
            use HasBlueprint;
            use HasProject;
            use HasSignature;

            public function isGlobal(): bool
            {
                return false;
            }
        };

    expect($model->getSignature())->toBe('our-team:my-project:page');
});
