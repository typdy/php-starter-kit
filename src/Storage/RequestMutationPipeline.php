<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Storage;

use Typdy\StarterKit\Repositories\Data\Request;
use Typdy\StarterKit\Typdy;

final readonly class RequestMutationPipeline
{
    public function mutate(Request $request): Request
    {
        foreach (Typdy::config()->requestMutators as $mutatorClass) {
            $mutator = Typdy::container($mutatorClass);
            $request = $mutator->mutate($request);
        }

        return $request;
    }
}
