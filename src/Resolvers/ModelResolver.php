<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Resolvers;

use DirectoryIterator;
use InvalidArgumentException;
use Override;
use RegexIterator;
use SplFileInfo;
use Typdy\StarterKit\Models\Contracts\Construct;
use Typdy\StarterKit\Models\Media;
use Typdy\StarterKit\Resolvers\Contracts\ResolvesModels;
use Typdy\StarterKit\Typdy;

use function class_exists;
use function file_exists;
use function is_subclass_of;

/**
 * @api
 */
class ModelResolver implements ResolvesModels
{
    /**
     * @var list<Construct>|null
     */
    protected ?array $constructs = null;

    #[Override]
    public function resolveMany(?string $team = null, ?string $project = null): array
    {
        if ($team === null && $project !== null) {
            throw new InvalidArgumentException('A project must not be specified without a team.');
        }

        $constructs = [];

        foreach ($this->getDefaultModels() as $type => $class) {
            $constructs[] = Typdy::container($class);
        }

        foreach ($this->getConstructs() as $construct) {
            if (
                !(
                    ($team === null || $construct->getTeam() === $team)
                    && ($project === null || $construct->getProject() === $project)
                )
            ) {
                continue;
            }

            $constructs[] = clone $construct;
        }

        return $constructs;
    }

    #[Override]
    public function resolveOne(string $team, string $project, string $blueprint): ?Construct
    {
        foreach ($this->getConstructs() as $construct) {
            if (
                $construct->getTeam() === $team
                && $construct->getProject() === $project
                && $construct->getBlueprint() === $blueprint
            ) {
                return clone $construct;
            }
        }

        foreach ($this->getDefaultModels() as $type => $class) {
            if ($blueprint === $type) {
                return Typdy::container($class);
            }
        }

        return null;
    }

    /**
     * @return list<Construct>
     */
    protected function getConstructs(): array
    {
        if ($this->constructs === null) {
            $this->constructs = [];

            foreach ($this->getLocations() as $namespace => $path) {
                $files = [];

                if (file_exists($path)) {
                    $files = new RegexIterator(new DirectoryIterator($path), '/\.php$/');
                }

                /** @var SplFileInfo $file */
                foreach ($files as $file) {
                    $class = $namespace . '\\' . $file->getBasename('.php');

                    if (class_exists($class) && is_subclass_of($class, Construct::class)) {
                        $this->constructs[] = Typdy::container($class);
                    }
                }
            }
        }

        return $this->constructs;
    }

    /**
     * @return array<string, class-string<Construct>>
     */
    protected function getDefaultModels(): array
    {
        return [
            'media' => Media::class,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function getLocations(): array
    {
        return Typdy::config()->modelLocations;
    }
}
