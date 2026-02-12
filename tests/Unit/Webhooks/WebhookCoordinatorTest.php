<?php

declare(strict_types=1);

use Typdy\StarterKit\Webhooks\Contracts\Handler;
use Typdy\StarterKit\Webhooks\Payload;
use Typdy\StarterKit\Webhooks\ResultSet;
use Typdy\StarterKit\Webhooks\WebhookCoordinator;

it('handles a payload', function () {
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

    $coord = new WebhookCoordinator();

    $coord->registerHandler('test', new class implements Handler {
        public function getName(): string
        {
            return 'foo';
        }

        public function handle(Payload $payload, ResultSet $resultSet): void
        {
            $resultSet->results = ['Handled successfully'];
        }

        public function withOptions(array $options): static
        {
            return $this;
        }
    });

    $coord->registerHandler('test', new class implements Handler {
        public function getName(): string
        {
            return 'bar';
        }

        public function handle(Payload $payload, ResultSet $resultSet): void
        {
            $resultSet->results = ['Handled successfully'];
        }

        public function withOptions(array $options): static
        {
            return $this;
        }
    });

    $results = $coord->handle($payload);

    expect($results)->toMatchArray([
        'test' => [
            'foo' => new ResultSet(['Handled successfully']),
            'bar' => new ResultSet(['Handled successfully']),
        ],
    ]);
});
