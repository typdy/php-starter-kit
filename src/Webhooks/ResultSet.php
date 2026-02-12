<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Webhooks;

use Typdy\StarterKit\Webhooks\Data\Result;

use function array_map;
use function array_values;
use function is_bool;
use function is_string;

final class ResultSet
{
    /**
     * @var list<Result>
     */
    public array $results = [] {
        set(array $results) {
            $this->results = array_map(
                $this->makeResult(...),
                array_values($results),
                array_keys($results),
            )
                |> array_values(...);
        }
    }

    /**
     * @param list<Result>|array<string, string|bool> $results
     */
    public function __construct(array $results = [])
    {
        $this->results = $results;
    }

    public function add(Result $result): void
    {
        $this->results = [...$this->results, $result];
    }

    private function makeResult(Result|string|bool $result, int|string $key): Result
    {
        if (is_bool($result)) {
            return new Result(message: (string) $key, failed: $result);
        }

        if (is_string($result)) {
            return new Result(message: $result);
        }

        return $result;
    }
}
