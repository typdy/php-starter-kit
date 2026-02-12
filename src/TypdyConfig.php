<?php

declare(strict_types=1);

namespace Typdy\StarterKit;

use Typdy\StarterKit\Models\Contracts\Construct;
use Typdy\StarterKit\Resolvers\ModelResolver;
use Typdy\StarterKit\Resolvers\RepositoryResolver;
use Typdy\StarterKit\Storage\Contracts\Driver;
use Typdy\StarterKit\Storage\Contracts\RequestMutator;
use Typdy\StarterKit\Storage\Json\JsonDriver;
use Typdy\StarterKit\Sync\Contracts\DriverPipeline as DriverPipelineContract;
use Typdy\StarterKit\Sync\DriverPipeline;

final readonly class TypdyConfig
{
    /**
     * @param list<string>|null $scopes
     * @param array<string, string> $modelLocations
     * @param array<string, string> $repositoryLocations
     * @param list<class-string<Driver>> $drivers
     * @param class-string<DriverPipelineContract<Construct>> $driverPipeline
     * @param list<class-string<RequestMutator>> $requestMutators
     */
    public function __construct(
        // project details
        public string $team,
        public string $project,

        // personal access token
        public ?string $token = null,

        // oauth credentials
        public ?string $clientId = null,
        public ?string $clientSecret = null,
        public ?string $redirectUrl = null,
        public ?string $authCode = null,
        public ?array $scopes = null,

        // storage paths
        public string $privateStoragePath = '/path/to/my/private/storage',

        // model resolution
        public string $modelResolver = ModelResolver::class,
        public array $modelLocations = [
            'Typdy\\StarterKit\\Models' => __DIR__ . '/Models',
        ],

        // repository resolution
        public string $repositoryResolver = RepositoryResolver::class,
        public array $repositoryLocations = [
            'Typdy\\StarterKit\\Repositories' => __DIR__ . '/Repositories',
        ],

        // api interaction
        public bool $responseFailureExceptions = true,
        public bool $legacyTypes = true,

        // drivers
        public array $drivers = [
            JsonDriver::class,
        ],

        // driver orchestration
        public string $driverPipeline = DriverPipeline::class,
        public bool $promoteReadHits = true,
        public int $maxCacheAgeDays = 90,
        public bool $mutateStoredPayloads = false,

        // driver request mutation
        public array $requestMutators = [],
    ) {}
}
