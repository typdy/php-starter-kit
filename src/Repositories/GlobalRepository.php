<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Repositories;

use Override;
use Typdy\StarterKit\Api\Contracts\Client;
use Typdy\StarterKit\Concerns\HasProject;
use Typdy\StarterKit\Models\Model;
use Typdy\StarterKit\Repositories\Concerns\HasSignature;
use Typdy\StarterKit\Repositories\Concerns\HasSynchronisedCrud;
use Typdy\StarterKit\Repositories\Contracts\Collection;
use Typdy\StarterKit\Repositories\Contracts\Replayable;
use Typdy\StarterKit\Typdy;

/**
 * @api
 */
class GlobalRepository implements Collection, Replayable
{
    use HasProject;
    use HasSignature;

    /**
     * @use HasSynchronisedCrud<Model>
     */
    use HasSynchronisedCrud;

    public function __construct()
    {
        $this->client = Typdy::container()->make(Client::class);
    }

    #[Override]
    final public function getBlueprint(): string
    {
        return 'global';
    }

    #[Override]
    final public function getEndpoint(): string
    {
        return '@' . $this->getTeam() . '/' . $this->getProject() . '/globals';
    }

    #[Override]
    final public function isGlobal(): true
    {
        return true;
    }
}
