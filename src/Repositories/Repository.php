<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Repositories;

use Override;
use Typdy\StarterKit\Api\Contracts\Client;
use Typdy\StarterKit\Concerns\HasBlueprint;
use Typdy\StarterKit\Concerns\HasCollection;
use Typdy\StarterKit\Concerns\HasProject;
use Typdy\StarterKit\Models\Contracts\Construct;
use Typdy\StarterKit\Repositories\Concerns\HasSignature;
use Typdy\StarterKit\Repositories\Concerns\HasSynchronisedCrud;
use Typdy\StarterKit\Repositories\Contracts\Collection;
use Typdy\StarterKit\Repositories\Contracts\Replayable;
use Typdy\StarterKit\Typdy;

/**
 * @api
 *
 * @template TModel of Construct
 */
abstract class Repository implements Collection, Replayable
{
    use HasBlueprint;
    use HasCollection;
    use HasProject;
    use HasSignature;

    /**
     * @use HasSynchronisedCrud<TModel>
     */
    use HasSynchronisedCrud;

    public function __construct()
    {
        $this->client = Typdy::container()->make(Client::class);
    }

    #[Override]
    final public function getEndpoint(): string
    {
        $endpoint = '@' . $this->getTeam() . '/' . $this->getProject() . '/';

        if ($this->mapi) {
            return $endpoint . 'constructs/' . $this->getBlueprint();
        }

        return $endpoint . $this->getCollection();
    }

    #[Override]
    final public function isGlobal(): false
    {
        return false;
    }
}
