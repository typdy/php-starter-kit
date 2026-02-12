<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Resolvers;

use DirectoryIterator;
use InvalidArgumentException;
use Override;
use RegexIterator;
use SplFileInfo;
use Typdy\StarterKit\Repositories\Contracts\Collection;
use Typdy\StarterKit\Repositories\GlobalRepository;
use Typdy\StarterKit\Resolvers\Contracts\ResolvesRepositories;
use Typdy\StarterKit\Typdy;

use function class_exists;
use function file_exists;
use function is_subclass_of;

/**
 * @api
 */
class RepositoryResolver implements ResolvesRepositories
{
    /**
     * @var list<Collection>|null
     */
    protected ?array $collections = null;

    #[Override]
    public function resolveMany(?string $team = null, ?string $project = null): array
    {
        if ($team === null && $project !== null) {
            throw new InvalidArgumentException('A project must not be specified without a team.');
        }

        $collections = [];
        $globalSeen = false;

        foreach ($this->getCollections() as $collection) {
            if ($collection->isGlobal()) {
                $globalSeen = true;
            }

            if (
                !(
                    ($team === null || $collection->getTeam() === $team)
                    && ($project === null || $collection->getProject() === $project)
                )
            ) {
                continue;
            }

            $collections[] = clone $collection;
        }

        if (!$globalSeen) {
            $collections[] = Typdy::container(GlobalRepository::class);
        }

        return $collections;
    }

    #[Override]
    public function resolveOne(string $team, string $project, string $blueprint): ?Collection
    {
        foreach ($this->getCollections() as $collection) {
            if (
                $collection->getTeam() === $team
                && $collection->getProject() === $project
                && ($collection->getBlueprint() === $blueprint || $collection->isGlobal() && $blueprint === 'global')
            ) {
                return clone $collection;
            }
        }

        // fallback to the default global repository
        if ($blueprint === 'global') {
            return Typdy::container(GlobalRepository::class);
        }

        return null;
    }

    /**
     * @return list<Collection>
     */
    protected function getCollections(): array
    {
        if ($this->collections === null) {
            $this->collections = [];

            foreach ($this->getLocations() as $namespace => $path) {
                $files = [];

                if (file_exists($path)) {
                    $files = new RegexIterator(new DirectoryIterator($path), '/\.php$/');
                }

                /** @var SplFileInfo $file */
                foreach ($files as $file) {
                    $class = $namespace . '\\' . $file->getBasename('.php');

                    if (class_exists($class) && is_subclass_of($class, Collection::class)) {
                        $this->collections[] = Typdy::container($class);
                    }
                }
            }
        }

        return $this->collections;
    }

    /**
     * @return array<string, string>
     */
    protected function getLocations(): array
    {
        return Typdy::config()->repositoryLocations;
    }
}
