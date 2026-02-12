<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Containers\Contracts;

use Closure;

/**
 * @api
 */
interface Container
{
    /**
     * @param Closure|string|class-string $abstract
     * @param Closure|string|class-string|null $concrete
     */
    public function bind(
        Closure|string $abstract,
        Closure|string|null $concrete = null,
        bool $shared = false,
    ): void;

    /**
     * @template TClass of object
     *
     * @param string|class-string<TClass> $abstract
     * @param array<string, mixed> $parameters
     *
     * @return ($abstract is class-string<TClass> ? TClass : mixed)
     */
    public function make(string $abstract, array $parameters = []): mixed;

    /**
     * @param Closure|string|class-string $abstract
     * @param Closure|string|class-string|null $concrete
     */
    public function singleton(
        Closure|string $abstract,
        Closure|string|null $concrete = null,
    ): void;
}
