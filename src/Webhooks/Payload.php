<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Webhooks;

use InvalidArgumentException;
use Typdy\StarterKit\Webhooks\Enums\Domain;
use Typdy\StarterKit\Webhooks\Enums\Event;
use Typdy\StarterKit\Webhooks\Exceptions\InvalidSigningKey;

use function hash_hmac;
use function is_object;
use function json_decode;

use const JSON_THROW_ON_ERROR;

final readonly class Payload
{
    /**
     * @var object{
     *     event: string,
     *     domain: string,
     *     project?: object{
     *         identifier: string,
     *         ...
     *     },
     *     blueprint?: object{
     *         id: int,
     *         identifier: string,
     *         ...
     *     },
     *     construct?: object{
     *         id: int,
     *         identifier: string,
     *         ...
     *     },
     *     collection?: object{
     *         id: int,
     *         identifier: string,
     *         ...
     *     },
     *     field?: object{
     *         id: int,
     *         identifier: string,
     *         ...
     *     },
     *     ...
     * } $payload
     */
    public object $payload;

    /**
     * @param array<string, string> $headers
     */
    private function __construct(
        public string $name,
        public string $body,
        public array $headers,
    ) {
        /** @var object|null $json */
        $json = json_decode($body, associative: false, flags: JSON_THROW_ON_ERROR);

        if (!is_object($json)) {
            throw new InvalidArgumentException('Invalid JSON payload.');
        }

        // @mago-expect analysis:invalid-property-assignment-value
        $this->payload = $json;
    }

    /**
     * @param array<string, string> $headers
     *
     * @throws InvalidSigningKey
     */
    public static function make(string $name, string $secret, string $body, array $headers): self
    {
        if (!self::checkSigningKey($secret, $body, $headers)) {
            InvalidSigningKey::throw($name);
        }

        return new self($name, $body, $headers);
    }

    /**
     * @param array<string, string> $headers
     */
    private static function checkSigningKey(string $secret, string $body, array $headers): bool
    {
        $expectedSignature = self::generateSigningKey($body, $secret);

        $signatureHeader = $headers['Signature'] ?? null;

        return $signatureHeader === $expectedSignature;
    }

    private static function generateSigningKey(string $payload, string $secret): string
    {
        return hash_hmac('sha256', $payload, $secret);
    }

    public function getBlueprint(): ?string
    {
        return $this->payload?->blueprint->identifier ?? null;
    }

    public function getDomain(): Domain
    {
        return Domain::from($this->payload->domain);
    }

    public function getEvent(): Event
    {
        return Event::from($this->payload->event);
    }
}
