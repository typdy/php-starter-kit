<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Concerns;

use ReflectionClass;
use Typdy\StarterKit\Attributes\Project;
use Typdy\StarterKit\Typdy;

use function count;

/**
 * Returns a project and team name from the `Project` class attribute or falls
 * back to the default project and team from config.
 *
 * @api
 */
trait HasProject
{
    /**
     * @internal
     */
    private ?string $_team = null;

    /**
     * @internal
     */
    private ?string $_project = null;

    public function getProject(): string
    {
        if ($this->_project === null) {
            $this->getTeam();
        }

        return $this->_project;
    }

    public function getTeam(): string
    {
        if ($this->_team === null) {
            $reflection = new ReflectionClass($this);
            $attributes = $reflection->getAttributes(Project::class);

            if (count($attributes) === 0) {
                $this->_project = Typdy::config()->project;

                return $this->_team = Typdy::config()->team;
            }

            /** @var Project $project */
            $attribute = $attributes[0]->newInstance();

            $this->_team = $attribute->team;
            $this->_project = $attribute->project;
        }

        return $this->_team;
    }
}
