<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Storage\Json;

use DateTimeImmutable;
use Override;
use RuntimeException;
use Typdy\StarterKit\Models\Contracts\Construct;
use Typdy\StarterKit\Parsers\Data\Resource;
use Typdy\StarterKit\Repositories\Contracts\Collection;
use Typdy\StarterKit\Repositories\Data\Request;
use Typdy\StarterKit\Storage\Concerns\SyncsModels;
use Typdy\StarterKit\Storage\Contracts\Driver;
use Typdy\StarterKit\Storage\Contracts\InvalidatesRequests;
use Typdy\StarterKit\Storage\Helpers\DocumentTransformer;
use Typdy\StarterKit\Storage\Json\Concerns\MutatesConstructs;
use Typdy\StarterKit\Storage\Utils\JsonEncoding;
use Typdy\StarterKit\Storage\Utils\RequestHashing;
use Typdy\StarterKit\Sync\Data\Metadata;
use Typdy\StarterKit\Typdy;
use Typdy\StarterKit\Utils\Arr;

use function array_filter;
use function array_map;
use function array_values;
use function count;
use function fclose;
use function fflush;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function flock;
use function fopen;
use function ftruncate;
use function fwrite;
use function in_array;
use function is_array;
use function is_dir;
use function is_link;
use function is_string;
use function json_decode;
use function mkdir;
use function rewind;
use function rtrim;
use function str_replace;
use function stream_get_contents;
use function symlink;
use function unlink;

use const JSON_THROW_ON_ERROR;

/**
 * @api
 */
class JsonDriver implements Driver, InvalidatesRequests
{
    use MutatesConstructs;
    use SyncsModels;

    public function __construct(
        protected readonly DocumentTransformer $transformer,
    ) {}

    #[Override]
    public function all(Collection $repository, Request $request): ?iterable
    {
        if ($request->id !== null || $request->identifier !== null) {
            return null;
        }

        $path = $this->collectionStoragePath($request);

        if (!file_exists($path)) {
            return null;
        }

        $json = file_get_contents($path);

        if ($json === false) {
            throw new RuntimeException("Unable to read collection file at '{$path}'.");
        }

        /**
         * @var list<array{
         *     type: string,
         *     id: string,
         *     attributes?: array<string, mixed>,
         *     meta?: array<string, mixed>,
         *     relationships?: array<string, array<string, mixed>>,
         * }> $constructs
         */
        $constructs = $this->jsonDecode($json)['constructs'] ?? [];

        return Typdy::collect(
            array_map(
                fn (array $array) => $array
                    |> Resource::fromArray(...)
                    |> (fn (Resource $resource): Construct => $this->transformer->makeModel($repository, $resource)),
                $constructs,
            )
                |> array_values(...),
        );
    }

    #[Override]
    public function delete(Collection $repository, Request $request): void
    {
        if ($request->id === null) {
            return;
        }

        $paths = $this->getIndexById($repository, $request->id);

        if (count($paths) === 0) {
            return;
        }

        $basePath = $this->getBasePath();

        foreach ($paths as $relativePath) {
            $absolutePath = $basePath . '/' . $relativePath;

            if (file_exists($absolutePath) || is_link($absolutePath)) {
                unlink($absolutePath);
            }
        }

        $this->removeIndexFilePaths($paths);
    }

    #[Override]
    public function find(Collection $repository, Request $request): ?Construct
    {
        if ($request->id === null && $request->identifier === null) {
            return null;
        }

        $path = $this->constructStoragePath($request);

        if (!file_exists($path)) {
            return null;
        }

        $json = file_get_contents($path);

        if ($json === false) {
            throw new RuntimeException("Unable to read construct file at '{$path}'.");
        }

        /**
         * @var array{
         *     type: string,
         *     id: string,
         *     attributes?: array<string, mixed>,
         *     meta?: array<string, mixed>,
         *     relationships?: array<string, array<string, mixed>>,
         * } $construct
         */
        $construct = $this->jsonDecode($json)['construct'] ?? [];

        return $construct
            |> Resource::fromArray(...)
            |> (fn (Resource $resource): Construct => $this->transformer->makeModel($repository, $resource));
    }

    /**
     * @return list<string>
     *
     * @mago-expect analysis:unused-parameter
     */
    public function getIndexByBlueprint(Collection $repository, string $blueprint): array
    {
        return ($this->getIndexFileContents()['blueprints'][$blueprint] ?? []) |> array_values(...);
    }

    /**
     * @return list<string>
     *
     * @mago-expect analysis:unused-parameter
     */
    public function getIndexById(Collection $repository, int $id): array
    {
        return ($this->getIndexFileContents()['constructs'][$id] ?? []) |> array_values(...);
    }

    #[Override]
    public function getMetadata(Collection $repository, Request $request): Metadata
    {
        if ($request->id !== null || $request->identifier !== null) {
            return $this->constructStoragePath($request) |> $this->readMetadataFromPath(...);
        }

        return $this->collectionStoragePath($request) |> $this->readMetadataFromPath(...);
    }

    #[Override]
    public function getReplayableRequests(Collection $repository, ?int $constructId): array
    {
        return $this->resolveRequestsForContext($repository, $constructId);
    }

    #[Override]
    public function invalidate(Collection $repository, Request $request): void
    {
        $path = $this->storagePath($request);

        if (!file_exists($path) && !is_link($path)) {
            return;
        }

        unlink($path);

        $this->removeIndexFilePaths([$this->toRelativePath($path)]);
    }

    #[Override]
    protected function writeCollectionToStorage(
        Collection $repository,
        Request $request,
        iterable $models,
        array $meta,
    ): void {
        $models = Typdy::decollect($models);

        $path = $this->collectionStoragePath($request);

        if (file_exists($path) || is_link($path)) {
            $this->removeIndexFilePaths([$this->toRelativePath($path)]);
        }

        file_put_contents($path, JsonEncoding::pretty([
            '__meta' => $this->prepareMetadata($meta),
            '__request' => $request->toArray(),
            'constructs' => array_map(
                static function (Construct $model): ?array {
                    if ($model->id === null || $model->resource === null) {
                        return null;
                    }

                    return $model->resource->toArray();
                },
                $models,
            )
                |> array_filter(...)
                |> array_values(...),
        ]));

        foreach ($models as $model) {
            $this->updateConstructIndex($model, $path);
        }
    }

    #[Override]
    protected function writeConstructToStorage(
        Collection $repository,
        Request $request,
        Construct $model,
        array $meta,
    ): void {
        if ($model->id === null || $model->resource === null) {
            return;
        }

        $idRequest = $request->cloneWithIdOnly($model->id);
        $identifierRequest = $request->cloneWithIdentifierOnly($model->identifier);

        $path = $this->constructStoragePath($idRequest);

        if (file_exists($path) || is_link($path)) {
            $this->removeIndexFilePaths([$this->toRelativePath($path)]);
        }

        file_put_contents($path, JsonEncoding::pretty([
            '__meta' => $this->prepareMetadata($meta),
            '__request' => $request->toArray(),
            // @mago-expect analysis:possible-method-access-on-null model->resource is null-checked above
            'construct' => $model->resource->toArray(),
        ]));

        $this->updateConstructIndex($model, $path);

        // symlink for identifier
        $symlinkPath = $this->constructStoragePath($identifierRequest);

        if (file_exists($symlinkPath) || is_link($symlinkPath)) {
            $this->removeIndexFilePaths([$this->toRelativePath($symlinkPath)]);
        }

        $this->updateConstructIndex($model, $symlinkPath);

        if (!file_exists($symlinkPath) && !is_link($symlinkPath)) {
            symlink($path, $symlinkPath);
        }

        $this->mutateStoredPayloads(
            $repository,
            $model,
            [$path, $symlinkPath],
            allowMutation: Typdy::config()->mutateStoredPayloads,
        );
    }

    private function collectionStoragePath(Request $request): string
    {
        return $this->storagePath($request, 'collections');
    }

    private function constructStoragePath(Request $request): string
    {
        return $this->storagePath($request, 'constructs');
    }

    private function getBasePath(): string
    {
        return rtrim(Typdy::config()->privateStoragePath, characters: '/') . '/json';
    }

    /**
     * @return array{
     *     constructs?: array<int, list<string>>,
     *     blueprints?: array<string, list<string>>,
     * }
     */
    private function getIndexFileContents(): array
    {
        $indexPath = $this->getBasePath() . '/index.json';

        if (!file_exists($indexPath)) {
            return [];
        }

        $json = file_get_contents($indexPath);

        if ($json === false) {
            throw new RuntimeException("Unable to index file at '{$indexPath}'.");
        }

        // @mago-expect analysis:mixed-return-statement
        return $this->jsonDecode($json);
    }

    private function jsonDecode(string $json): mixed
    {
        return json_decode($json, associative: true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * @param list<string> $excludePaths
     */
    private function mutateStoredPayloads(
        Collection $repository,
        Construct $model,
        array $excludePaths = [],
        bool $allowMutation = true,
    ): void {
        if ($model->id === null) {
            return;
        }

        $basePath = $this->getBasePath();

        $exclude = [];

        foreach ($excludePaths as $excludePath) {
            $exclude[] = str_replace(search: $basePath . '/', replace: '', subject: $excludePath);
        }

        $relativePaths = $this->getIndexById($repository, $model->id);

        if (count($relativePaths) === 0) {
            return;
        }

        $invalidatedPaths = [];

        foreach ($relativePaths as $relativePath) {
            if (in_array($relativePath, $exclude, strict: true)) {
                continue;
            }

            $absolutePath = $basePath . '/' . $relativePath;

            if (!file_exists($absolutePath) || is_link($absolutePath)) {
                continue;
            }

            $json = file_get_contents($absolutePath);

            if ($json === false) {
                throw new RuntimeException("Unable to read json file at '{$absolutePath}'.");
            }

            /** @var array<string, mixed> $payload */
            $payload = $this->jsonDecode($json);

            [$mutated, $invalidate] = $this->mutatePayloadByContext($payload, $model);

            if ($invalidate) {
                unlink($absolutePath);
                $invalidatedPaths[] = $relativePath;

                continue;
            }

            if ($mutated && $allowMutation) {
                $meta = [];

                if (is_array($payload['__meta'] ?? null)) {
                    $meta = $payload['__meta'];
                }

                $payload['__meta'] = $this->prepareMetadata($meta);

                file_put_contents($absolutePath, JsonEncoding::pretty($payload));
            }
        }

        if (count($invalidatedPaths) > 0) {
            $this->removeIndexFilePaths($invalidatedPaths);
        }
    }

    /**
     * @param array<array-key, mixed> $meta
     *
     * @return array<array-key, string>
     */
    private function prepareMetadata(array $meta): array
    {
        return [
            ...$meta,
            'fetchedAt' => new DateTimeImmutable()->format(DATE_ATOM),
        ];
    }

    private function readMetadataFromPath(string $path): Metadata
    {
        if (!file_exists($path)) {
            return new Metadata();
        }

        $json = file_get_contents($path);

        if ($json === false) {
            throw new RuntimeException("Unable to read json file at '{$path}'.");
        }

        /** @var array<string, mixed> $data */
        $data = $this->jsonDecode($json);

        if (!is_array($data['__meta'] ?? null)) {
            return new Metadata();
        }

        $meta = $data['__meta'];

        $fetchedAt = null;
        $revision = null;

        if (is_string($meta['fetchedAt'] ?? null)) {
            $fetchedAt = new DateTimeImmutable($meta['fetchedAt']);
        }

        if (is_string($meta['revision'] ?? null)) {
            $revision = $meta['revision'];
        }

        return new Metadata(
            fetchedAt: $fetchedAt,
            revision: $revision,
            raw: $meta,
        );
    }

    private function readRequestFromPath(string $relativePath): ?Request
    {
        $path = $this->getBasePath() . '/' . $relativePath;

        if (!file_exists($path) && !is_link($path)) {
            return null;
        }

        $json = file_get_contents($path);

        if ($json === false) {
            throw new RuntimeException("Unable to read request payload at '{$path}'.");
        }

        /** @var array{__request?: array<string, mixed>} $payload */
        $payload = $this->jsonDecode($json);

        if (!is_array($payload['__request'] ?? null)) {
            return null;
        }

        /**
         * @var array{
         *     team: string,
         *     project: string,
         *     blueprint?: string|null,
         *     collection?: string|null,
         *     isGlobal?: bool,
         *     id?: int|null,
         *     identifier?: string|null,
         *     query?: array<string, mixed>,
         * } $request
         */
        $request = $payload['__request'];

        return new Request(
            team: $request['team'],
            project: $request['project'],
            blueprint: is_string($request['blueprint'] ?? null) ? $request['blueprint'] : null,
            collection: is_string($request['collection'] ?? null) ? $request['collection'] : null,
            isGlobal: $request['isGlobal'] ?? false,
            id: is_int($request['id'] ?? null) ? $request['id'] : null,
            identifier: is_string($request['identifier'] ?? null) ? $request['identifier'] : null,
            query: is_array($request['query'] ?? null) ? $request['query'] : [],
        );
    }

    /**
     * @param list<string> $filePaths
     */
    private function removeIndexFilePaths(array $filePaths): void
    {
        $indexPath = $this->getBasePath() . '/index.json';

        if (!file_exists($indexPath)) {
            return;
        }

        $handle = fopen($indexPath, mode: 'c+');

        if ($handle === false) {
            throw new RuntimeException("Unable to open index file at '{$indexPath}'.");
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException("Unable to lock index file at '{$indexPath}'.");
            }

            rewind($handle);

            $json = stream_get_contents($handle);

            /** @var array<string, array<int|string, list<string>>> $index **/
            $index = $json === false || $json === '' ? [] : $this->jsonDecode($json);

            foreach (['constructs', 'blueprints'] as $type) {
                if (!is_array($index[$type] ?? null)) {
                    continue;
                }

                foreach ($index[$type] as $key => $indexedPaths) {
                    $remaining = array_filter(
                        $indexedPaths,
                        static fn (string $path) => !in_array($path, $filePaths, strict: true),
                    )
                        |> array_values(...);

                    if (count($remaining) === 0) {
                        unset($index[$type][$key]);

                        continue;
                    }

                    $index[$type][$key] = $remaining;
                }
            }

            rewind($handle);
            ftruncate($handle, size: 0);
            fwrite($handle, JsonEncoding::pretty($index));
            fflush($handle);
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }
    }

    /**
     * @return list<Request>
     */
    private function resolveRequestsForContext(Collection $repository, ?int $constructId): array
    {
        $paths = $this->getIndexByBlueprint($repository, $repository->getBlueprint());

        if ($constructId !== null) {
            $paths = [
                ...$paths,
                ...$this->getIndexById($repository, $constructId),
            ];
        }

        $requests = [];
        $seen = [];

        foreach ($paths as $relativePath) {
            $request = $this->readRequestFromPath($relativePath);

            if ($request === null) {
                continue;
            }

            if ($request->team !== $repository->getTeam() || $request->project !== $repository->getProject()) {
                continue;
            }

            $hash = RequestHashing::hash($request);

            if ($seen[$hash] ?? false) {
                continue;
            }

            $seen[$hash] = true;
            $requests[] = $request;
        }

        return $requests;
    }

    private function storagePath(Request $request, ?string $dir = null): string
    {
        $basePath = $this->getBasePath() . ($dir ? '/' . $dir : '');

        [$shardedPath, $hash] = RequestHashing::shard(RequestHashing::hash($request));

        $path = $basePath . '/' . $shardedPath;

        if (!is_dir($path)) {
            mkdir($path, permissions: 0o755, recursive: true);
        }

        return $path . '/' . $hash . '.json';
    }

    private function toRelativePath(string $absolutePath): string
    {
        return str_replace(search: $this->getBasePath() . '/', replace: '', subject: $absolutePath);
    }

    private function updateConstructIndex(Construct $model, string $filePath): void
    {
        if ($model->id === null || $model->resource === null) {
            return;
        }

        $filePath = $this->toRelativePath($filePath);

        $indexPath = $this->getBasePath() . '/index.json';

        $handle = fopen($indexPath, mode: 'c+');

        if ($handle === false) {
            throw new RuntimeException("Unable to open index file at '{$indexPath}'.");
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException("Unable to lock index file at '{$indexPath}'.");
            }

            rewind($handle);

            $json = stream_get_contents($handle);

            /** @var array<string, array<int|string, list<string>>> $index **/
            $index = $json === false || $json === '' ? [] : $this->jsonDecode($json);

            $this->updateIndexArray($model->id, $filePath, $index);
            $this->updateIndexArray($model->resource->type, $filePath, $index, 'blueprints');

            foreach ($model->resource->relationships as $relation) {
                if ($relation->data === null) {
                    continue;
                }

                /** @var list<Resource> $related **/
                $related = Arr::wrap($relation->data);

                foreach ($related as $relatedResource) {
                    $this->updateIndexArray($relatedResource->id, $filePath, $index);
                    $this->updateIndexArray($relatedResource->type, $filePath, $index, 'blueprints');
                }
            }

            rewind($handle);
            ftruncate($handle, size: 0);
            fwrite($handle, JsonEncoding::pretty($index));
            fflush($handle);
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param-out array<string, array<int|string, list<string>>> $index
     *
     * @mago-expect analysis:reference-constraint-violation
     */
    private function updateIndexArray(
        int|string $key,
        string $filePath,
        array &$index,
        string $type = 'constructs',
    ): void {
        if (!is_array($index[$type] ?? null)) {
            $index[$type] = [];
        }

        if (!is_array($index[$type][$key] ?? null)) {
            $index[$type][$key] = [];
        }

        if (!in_array($filePath, $index[$type][$key], strict: true)) {
            $index[$type][$key][] = $filePath;
        }
    }
}
