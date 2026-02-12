<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Repositories\Concerns;

use BadMethodCallException;
use RuntimeException;
use Typdy\StarterKit\Parsers\Data\Document;
use Typdy\StarterKit\Repositories\Exceptions\HttpNotFoundException;
use Typdy\StarterKit\Typdy;

use function array_key_exists;
use function call_user_func_array;
use function in_array;
use function is_int;

/**
 * Implements a macro system for repositories, allowing dynamic method definitions at runtime.
 *
 * @api
 */
trait HasMacros
{
    /**
     * @var array<string, callable>
     */
    protected static array $macros = [];

    /**
     * @var array<string>
     */
    protected static array $protectedMacros = [
        'throwNotFoundException',
        'responseFailureException',
        'resolvePageNumber',
    ];

    public static function clearMacros(): void
    {
        self::$macros = [];
    }

    public static function hasMacro(string $name): bool
    {
        return array_key_exists($name, self::$macros) || in_array($name, self::$protectedMacros, strict: true);
    }

    /**
     * @param callable(): mixed $macro
     */
    public static function macro(string $name, callable $macro): void
    {
        self::$macros[$name] = $macro;
    }

    public static function removeMacro(string $name): void
    {
        unset(self::$macros[$name]);
    }

    protected function resolvePageNumber(): int
    {
        if (array_key_exists('resolvePageNumber', self::$macros)) {
            $macro = self::$macros['resolvePageNumber'];

            // @mago-expect analysis:mixed-assignment
            $pageNumber = $macro();

            if (is_int($pageNumber)) {
                return $pageNumber;
            }
        }

        $page = (int) ($_GET['page']['number'] ?? 1);

        return $page < 1 ? 1 : $page;
    }

    /**
     * @throws HttpNotFoundException
     */
    protected function throwNotFoundException(): never
    {
        if (array_key_exists('throwNotFoundException', self::$macros)) {
            $macro = self::$macros['throwNotFoundException'];

            $macro();
        }

        throw new HttpNotFoundException('Construct not found.');
    }

    /**
     * @throws HttpNotFoundException
     */
    protected function throwResponseException(Document $document): void
    {
        if (!Typdy::config('responseFailureException', default: true)) {
            return;
        }

        if (array_key_exists('throwResponseException', self::$macros)) {
            $macro = self::$macros['throwResponseException'];

            $macro($document);

            return;
        }

        $code = $document->response->getStatusCode();
        $message = $document->response->getReasonPhrase();

        if ($code !== 404) {
            throw new RuntimeException("Received unsuccessful response from typdy: {$code} '{$message}'.");
        }

        $this->throwNotFoundException();
    }

    /**
     * @param array<array-key, mixed> $arguments
     */
    public function __call(string $name, array $arguments): mixed
    {
        if (in_array($name, self::$protectedMacros, strict: true)) {
            throw new BadMethodCallException("Macro {$name} is protected and cannot be called externally.");
        }

        if (!array_key_exists($name, self::$macros)) {
            throw new BadMethodCallException("Macro {$name} does not exist.");
        }

        return call_user_func_array(self::$macros[$name], $arguments);
    }
}
