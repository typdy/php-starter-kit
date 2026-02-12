<?php

declare(strict_types=1);

namespace Typdy\StarterKit;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\HandlerStack;
use kamermans\OAuth2\GrantType\AuthorizationCode;
use kamermans\OAuth2\GrantType\RefreshToken;
use kamermans\OAuth2\OAuth2Middleware;
use kamermans\OAuth2\Persistence\FileTokenPersistence;
use Psr\Http\Client\ClientInterface as HttpClientInterface;
use RuntimeException;
use Typdy\StarterKit\Api\Client;
use Typdy\StarterKit\Api\Contracts\Client as ClientContract;
use Typdy\StarterKit\Containers\Contracts\Container;
use Typdy\StarterKit\Contracts\CollectCallback;
use Typdy\StarterKit\Contracts\DecollectCallback;
use Typdy\StarterKit\Contracts\PaginateCallback;
use Typdy\StarterKit\Data\Paginated;
use Typdy\StarterKit\Models\Contracts\Construct;
use Typdy\StarterKit\Parsers\Contracts\DocumentParser as DocumentParserContract;
use Typdy\StarterKit\Parsers\Contracts\ErrorsParser as ErrorsParserContract;
use Typdy\StarterKit\Parsers\Contracts\IncludedParser as IncludedParserContract;
use Typdy\StarterKit\Parsers\Contracts\MetaParser as MetaParserContract;
use Typdy\StarterKit\Parsers\Contracts\ResourceParser as ResourceParserContract;
use Typdy\StarterKit\Parsers\Contracts\ResponseParser as ResponseParserContract;
use Typdy\StarterKit\Parsers\DocumentParser;
use Typdy\StarterKit\Parsers\ErrorsParser;
use Typdy\StarterKit\Parsers\IncludedParser;
use Typdy\StarterKit\Parsers\MetaParser;
use Typdy\StarterKit\Parsers\ResourceParser;
use Typdy\StarterKit\Parsers\ResponseParser;
use Typdy\StarterKit\Repositories\Data\Request;
use Typdy\StarterKit\Resolvers\Contracts\ResolvesModels;
use Typdy\StarterKit\Resolvers\Contracts\ResolvesRepositories;
use Typdy\StarterKit\Sync\Contracts\DriverPipeline;
use Typdy\StarterKit\Webhooks\Contracts\ExecutesReplayTasks;
use Typdy\StarterKit\Webhooks\Contracts\ReplayDispatcher;
use Typdy\StarterKit\Webhooks\InlineReplayDispatcher;
use Typdy\StarterKit\Webhooks\ReplayTaskExecutor;

use function array_flip;
use function array_intersect_key;
use function get_object_vars;
use function implode;
use function is_array;
use function is_string;
use function iterator_to_array;
use function property_exists;

final class Typdy
{
    public static TypdyConfig $config;

    public static ?CollectCallback $collectCallback = null;

    public static ?DecollectCallback $decollectCallback = null;

    public static ?PaginateCallback $paginateCallback = null;

    public static ?Container $container = null;

    /**
     * @template TKey of array-key
     * @template TValue
     *
     * @param iterable<TKey, TValue> $data
     *
     * @return iterable<TKey, TValue>
     */
    public static function collect(iterable $data = []): iterable
    {
        if (self::$collectCallback === null) {
            return $data;
        }

        return (self::$collectCallback)($data);
    }

    /**
     * @param list<string>|string|null $key
     *
     * @return ($key is null ? TypdyConfig : mixed)
     *
     * @mago-expect analysis:mixed-assignment
     * @mago-expect analysis:string-member-selector
     */
    public static function config(array|string|null $key = null, mixed $default = null): mixed
    {
        $value = $default;

        if ($key === null) {
            $value = self::$config;
        }

        if (is_string($key) && property_exists(self::$config, $key)) {
            $value = self::$config->$key;
        }

        if (is_array($key)) {
            $value = array_intersect_key(get_object_vars(self::$config), array_flip($key));
        }

        return $value;
    }

    /**
     * @template TClass of object
     *
     * @param string|class-string<TClass>|null $abstract
     * @param array<string, mixed> $parameters
     *
     * @return (
     *     $abstract is class-string<TClass>
     *         ? TClass
     *         : ($abstract is string
     *             ? mixed
     *             : Container)
     * )
     */
    public static function container(?string $abstract = null, array $parameters = []): mixed
    {
        if (self::$container === null) {
            throw new RuntimeException('Container not set!');
        }

        if ($abstract === null) {
            return self::$container;
        }

        return self::$container->make($abstract, $parameters);
    }

    /**
     * @template TKey of array-key
     * @template TValue
     *
     * @param iterable<TKey, TValue> $data
     *
     * @return array<TKey, TValue>
     */
    public static function decollect(iterable $data): array
    {
        if (self::$decollectCallback === null) {
            if (is_array($data)) {
                return $data;
            }

            return iterator_to_array($data);
        }

        return (self::$decollectCallback)($data);
    }

    /**
     * @template TModel of Construct
     *
     * @param Paginated<TModel> $models
     *
     * @return iterable<int, TModel>
     */
    public static function paginate(Paginated $models, Request $request): iterable
    {
        if (self::$paginateCallback === null) {
            return $models;
        }

        return (self::$paginateCallback)($models, $request);
    }

    public static function register(): void
    {
        self::registerHttpClient();
        self::registerClient();
        self::registerParsers();
        self::registerResolvers();
        self::registerPipelines();
        self::registerWebhooks();
    }

    private static function bearerClient(string $token): GuzzleClient
    {
        return new GuzzleClient([
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $authConfig
     */
    private static function oauthClient(array $authConfig): GuzzleClient
    {
        $authClient = new GuzzleClient(['base_uri' => 'https://app.typedcms.com/oauth/token']);

        $oauth = new OAuth2Middleware(
            new AuthorizationCode($authClient, $authConfig),
            new RefreshToken($authClient, $authConfig),
        );

        $storage = new FileTokenPersistence(self::$config->privateStoragePath . '/oauth');

        $oauth->setTokenPersistence($storage);

        $stack = HandlerStack::create();
        $stack->push($oauth);

        return new GuzzleClient(['auth' => 'oauth', 'handler' => $stack]);
    }

    private static function registerClient(): void
    {
        self::container()->singleton(ClientContract::class, Client::class);
    }

    private static function registerHttpClient(): void
    {
        self::container()
            ->bind(HttpClientInterface::class, static function (): GuzzleClient {
                if (self::$config->clientId === null && self::$config->token !== null) {
                    return self::bearerClient(self::$config->token);
                }

                return self::oauthClient([
                    'client_id' => self::$config->clientId,
                    'client_secret' => self::$config->clientSecret,
                    'redirect_uri' => self::$config->redirectUrl,
                    'code' => self::$config->authCode,
                    'scope' => implode(',', self::$config->scopes ?? []),
                ]);
            });
    }

    private static function registerParsers(): void
    {
        self::container()->bind(ResponseParserContract::class, ResponseParser::class);
        self::container()->bind(DocumentParserContract::class, DocumentParser::class);
        self::container()->bind(ResourceParserContract::class, ResourceParser::class);
        self::container()->bind(IncludedParserContract::class, IncludedParser::class);
        self::container()->bind(ErrorsParserContract::class, ErrorsParser::class);
        self::container()->bind(MetaParserContract::class, MetaParser::class);
    }

    private static function registerPipelines(): void
    {
        self::container()
            ->singleton(
                DriverPipeline::class,
                static fn () => self::container(self::$config->driverPipeline),
            );
    }

    private static function registerResolvers(): void
    {
        self::container()
            ->singleton(
                ResolvesModels::class,
                static fn () => self::container(self::$config->modelResolver),
            );

        self::container()
            ->singleton(
                ResolvesRepositories::class,
                static fn () => self::container(self::$config->repositoryResolver),
            );
    }

    private static function registerWebhooks(): void
    {
        self::container()->singleton(ReplayDispatcher::class, InlineReplayDispatcher::class);
        self::container()->singleton(ExecutesReplayTasks::class, ReplayTaskExecutor::class);
    }
}
