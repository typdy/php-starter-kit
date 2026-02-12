<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Containers;

use Closure;
use Illuminate\Container\Container as LaravelContainer;
use Illuminate\Contracts\Container\BindingResolutionException;
use Override;
use Typdy\StarterKit\Containers\Contracts\Container;

final class LaravelAdaptor implements Container
{
    public function __construct(
        private LaravelContainer $container,
    ) {}

    #[Override]
    public function bind(
        Closure|string $abstract,
        Closure|string|null $concrete = null,
        bool $shared = false,
    ): void {
        $this->container->bind($abstract, $concrete, $shared);
    }

    /**
     * @throws BindingResolutionException
     */
    #[Override]
    public function make(string $abstract, array $parameters = []): mixed
    {
        return $this->container->make($abstract, $parameters);
    }

    #[Override]
    public function singleton(
        Closure|string $abstract,
        Closure|string|null $concrete = null,
    ): void {
        $this->container->singleton($abstract, $concrete);
    }
}
