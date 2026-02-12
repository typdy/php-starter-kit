<?php

declare(strict_types=1);

use Typdy\StarterKit\Webhooks\Enums\Domain;
use Typdy\StarterKit\Webhooks\Enums\Event;
use Typdy\StarterKit\Webhooks\Exceptions\InvalidSigningKey;
use Typdy\StarterKit\Webhooks\Payload;

it('can be created with a valid signing key', function () {
    $body = [
        'domain' => 'constructs',
        'event' => 'create',
        'blueprint' => [
            'identifier' => 'page',
        ],
    ];

    $json = json_encode($body, JSON_THROW_ON_ERROR);

    // @mago-expect lint:no-literal-password
    $secret = 'secret';
    $signature = hash_hmac('sha256', $json, $secret);

    $payload = Payload::make(
        name: 'test',
        secret: $secret,
        body: $json,
        headers: ['Signature' => $signature],
    );

    expect($payload->name)->toBe('test');
    expect($payload->getBlueprint())->toBe('page');
    expect($payload->getDomain())->toBe(Domain::Constructs);
    expect($payload->getEvent())->toBe(Event::Create);
});

it('throws with an invalid signing key', function () {
    $body = [
        'blueprint' => [
            'identifier' => 'page',
        ],
    ];

    $json = json_encode($body, JSON_THROW_ON_ERROR);

    // @mago-expect lint:no-literal-password
    $secret = 'secret';
    $invalidSignature = 'invalid-signature';

    Payload::make(
        name: 'test',
        secret: $secret,
        body: $json,
        headers: ['Signature' => $invalidSignature],
    );
})->throws(InvalidSigningKey::class);
