<?php

use Laravel\Mcp\Response;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Cancellation;
use Laravel\Mcp\Server\Contracts\Method;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Transport\JsonRpcRequest;
use Laravel\Mcp\Transport\JsonRpcResponse;
use Tests\Fixtures\ArrayTransport;
use Tests\Fixtures\CustomMethodHandler;
use Tests\Fixtures\ExampleServer;
use Tests\Fixtures\ThrowingMethodHandler;

it('can handle an initialize message', function (): void {
    $transport = new ArrayTransport;
    $server = new ExampleServer($transport);

    $server->start();

    $payload = json_encode(initializeMessage());

    ($transport->handler)($payload);

    $response = json_decode((string) $transport->sent[0], true);

    expect($response)->toEqual(expectedInitializeResponse());
});

it('can add a capability', function (): void {
    $transport = new ArrayTransport;
    $server = new ExampleServer($transport);

    $server->addCapability('customFeature.enabled', true);
    $server->addCapability('anotherFeature');

    $server->start();

    $payload = json_encode(initializeMessage());

    ($transport->handler)($payload);

    $jsonResponse = $transport->sent[0];

    $capabilities = (fn (): array => $this->capabilities)->call($server);

    $expectedCapabilitiesJson = json_encode(array_merge($capabilities, [
        'customFeature' => [
            'enabled' => true,
        ],
        'anotherFeature' => (object) [],
    ]));

    $this->assertStringContainsString($expectedCapabilitiesJson, $jsonResponse);
});

it('can handle a list tools message', function (): void {
    $transport = new ArrayTransport;
    $server = new ExampleServer($transport);

    $server->start();

    $payload = json_encode(listToolsMessage());

    ($transport->handler)($payload);

    $response = json_decode((string) $transport->sent[0], true);

    expect($response)->toEqual(expectedListToolsResponse());
});

it('can handle a call tool message', function (): void {
    $transport = new ArrayTransport;
    $server = new ExampleServer($transport);

    $server->start();

    $payload = json_encode(callToolMessage());

    ($transport->handler)($payload);

    $response = json_decode((string) $transport->sent[0], true);

    expect($response)->toEqual(expectedCallToolResponse());
});

it('can handle a notification message', function (): void {
    $transport = new ArrayTransport;
    $server = new ExampleServer($transport);

    $server->start();

    $payload = json_encode([
        'jsonrpc' => '2.0',
        'method' => 'notifications/initialized',
    ]);

    ($transport->handler)($payload);

    expect($transport->sent)->toHaveCount(0);
});

it('accepts cancellation notifications without sending a response', function (): void {
    $transport = new ArrayTransport;
    $server = new ExampleServer($transport);

    $server->start();

    ($transport->handler)(json_encode([
        'jsonrpc' => '2.0',
        'method' => 'notifications/cancelled',
        'params' => [
            'requestId' => 123,
            'reason' => 'User requested cancellation',
        ],
    ]));

    expect($transport->sent)->toHaveCount(0);
});

it('lets active request execution observe cancellation', function (): void {
    $transport = new ArrayTransport;
    $observed = (object) ['data' => []];

    $server = new class($transport, $observed) extends Server
    {
        public function __construct(
            ArrayTransport $transport,
            protected object $observed,
        ) {
            parent::__construct($transport);
        }

        protected array $methods = [
            'cancel/observe' => ObservesCancellationMethod::class,
        ];

        protected function boot(): void
        {
            app()->instance('test.transport', $this->transport);
            app()->instance('test.observed', $this->observed);
        }
    };

    $server->start();

    ($transport->handler)(json_encode([
        'jsonrpc' => '2.0',
        'id' => 456,
        'method' => 'cancel/observe',
        'params' => [],
    ]));

    expect($observed->data)->toBe([
        'before' => false,
        'after' => true,
        'reason' => 'No longer needed',
    ])->and($transport->sent)->toHaveCount(0);
});

it('stops streaming responses after cancellation is observed', function (): void {
    $transport = new ArrayTransport;

    $server = new class($transport) extends Server
    {
        protected array $tools = [
            CancellingStreamingTool::class,
        ];

        protected function boot(): void
        {
            app()->instance('test.transport', $this->transport);
        }
    };

    $server->start();

    ($transport->handler)(json_encode([
        'jsonrpc' => '2.0',
        'id' => 789,
        'method' => 'tools/call',
        'params' => [
            'name' => 'cancelling-streaming-tool',
            'arguments' => [],
        ],
    ]));

    $messages = array_map(fn (string $message): mixed => json_decode($message, true), $transport->sent);

    expect($messages)->toHaveCount(1)
        ->and($messages[0])->toEqual([
            'jsonrpc' => '2.0',
            'method' => 'notifications/progress',
            'params' => ['progress' => 50],
        ]);
});

it('can handle an unknown method', function (): void {
    $transport = new ArrayTransport;
    $server = new ExampleServer($transport);

    $server->start();

    $payload = json_encode([
        'jsonrpc' => '2.0',
        'id' => 789,
        'method' => 'unknown/method',
        'params' => [],
    ]);

    ($transport->handler)($payload);

    $response = json_decode((string) $transport->sent[0], true);

    expect($response)->toEqual([
        'jsonrpc' => '2.0',
        'id' => 789,
        'error' => [
            'code' => -32601,
            'message' => 'The method [unknown/method] was not found.',
        ],
    ]);
});

it('handles json decode errors', function (): void {
    $transport = new ArrayTransport;
    $server = new ExampleServer($transport);

    $server->start();

    $invalidJsonPayload = '{"jsonrpc": "2.0", "id": 123, "method": "initialize", "params": {}';

    // Malformed JSON
    ($transport->handler)($invalidJsonPayload);

    expect($transport->sent)->toHaveCount(1);
    $response = json_decode((string) $transport->sent[0], true);

    expect($response)->toBe([
        'jsonrpc' => '2.0',
        'error' => [
            'code' => -32700,
            'message' => 'Parse error: Invalid JSON was received by the server.',
        ],
    ]);
});

it('can handle a custom method message', function (): void {
    $transport = new ArrayTransport;
    $server = new ExampleServer($transport);

    $server->addMethod('custom/method', CustomMethodHandler::class);

    $this->app->bind(CustomMethodHandler::class, fn (): CustomMethodHandler => new CustomMethodHandler('custom-dependency'));

    $server->start();

    $payload = json_encode([
        'jsonrpc' => '2.0',
        'id' => 12345,
        'method' => 'custom/method',
        'params' => [],
    ]);

    ($transport->handler)($payload);

    expect($transport->sent)->toHaveCount(1);
    $response = json_decode((string) $transport->sent[0], true);

    expect($response)->toEqual([
        'jsonrpc' => '2.0',
        'id' => 12345,
        'result' => [
            'message' => 'Custom method executed successfully!',
        ],
    ]);
});

it('can handle a ping message', function (): void {
    $transport = new ArrayTransport;
    $server = new ExampleServer($transport);

    $server->start();

    $payload = json_encode(pingMessage());

    ($transport->handler)($payload);

    $response = json_decode((string) $transport->sent[0], true);

    expect($response)->toEqual(expectedPingResponse());
});

it('calls boot method on connect', function (): void {
    $transport = new ArrayTransport;

    $server = new class($transport) extends Server
    {
        public function boot(): void
        {
            $this->bootCalled = true;
        }
    };
    $server->start();

    expect($server->bootCalled)->toBeTrue('The boot() method was not called on connect.');
});

it('can handle a tool streaming multiple messages', function (): void {
    $transport = new ArrayTransport;
    $server = new ExampleServer($transport);

    $server->start();

    $payload = json_encode(callStreamingToolMessage());

    ($transport->handler)($payload);

    $messages = array_map(fn ($msg): mixed => json_decode((string) $msg, true), $transport->sent);

    expect($messages)->toEqual(expectedStreamingToolResponse());
});

it('handles capability with non-array existing value', function (): void {
    $transport = new ArrayTransport;
    $server = new ExampleServer($transport);

    // First set a non-array value
    $server->addCapability('feature');

    // Then try to add a nested capability to it
    $server->addCapability('feature.enabled', true);

    $server->start();

    $payload = json_encode(initializeMessage());

    ($transport->handler)($payload);

    $capabilities = (fn (): array => $this->capabilities)->call($server);

    expect($capabilities['feature'])->toBeArray();
    expect($capabilities['feature']['enabled'])->toBeTrue();
});

it('handles exceptions in debug mode', function (): void {
    config()->set('app.debug', true);

    $transport = new ArrayTransport;
    $server = new class($transport) extends Server
    {
        protected array $methods = [
            'test/method' => ThrowingMethodHandler::class,
        ];
    };

    $this->app->bind(ThrowingMethodHandler::class, fn (): Method => new class implements Method
    {
        public function handle(JsonRpcRequest $request, ServerContext $context): JsonRpcResponse
        {
            throw new Exception('Test exception');
        }
    });

    $server->start();

    $payload = json_encode([
        'jsonrpc' => '2.0',
        'id' => 999,
        'method' => 'test/method',
        'params' => [],
    ]);

    expect(function () use ($transport, $payload): void {
        ($transport->handler)($payload);
    })->toThrow(Exception::class, 'Test exception');
});

it('handles exceptions in production mode', function (): void {
    config()->set('app.debug', false);

    $transport = new ArrayTransport;
    $server = new class($transport) extends Server
    {
        protected array $methods = [
            'test/method' => ThrowingMethodHandler::class,
        ];
    };

    $this->app->bind(ThrowingMethodHandler::class, fn (): Method => new class implements Method
    {
        public function handle(JsonRpcRequest $request, ServerContext $context): JsonRpcResponse
        {
            throw new Exception('Test exception');
        }
    });

    $server->start();

    $payload = json_encode([
        'jsonrpc' => '2.0',
        'id' => 999,
        'method' => 'test/method',
        'params' => [],
    ]);

    ($transport->handler)($payload);

    expect($transport->sent)->toHaveCount(1);
    $response = json_decode((string) $transport->sent[0], true);

    expect($response)->toEqual([
        'jsonrpc' => '2.0',
        'id' => 999,
        'error' => [
            'code' => -32603,
            'message' => 'Something went wrong while processing the request.',
        ],
    ]);
});

class ObservesCancellationMethod implements Method
{
    public function handle(JsonRpcRequest $request, ServerContext $context): JsonRpcResponse
    {
        /** @var Cancellation $cancellation */
        $cancellation = app(Cancellation::class);

        /** @var object{data: array<string, mixed>} $observed */
        $observed = app('test.observed');

        /** @var ArrayTransport $transport */
        $transport = app('test.transport');

        $observed->data['before'] = $cancellation->cancelled();

        ($transport->handler)(json_encode([
            'jsonrpc' => '2.0',
            'method' => 'notifications/cancelled',
            'params' => [
                'requestId' => $request->id,
                'reason' => 'No longer needed',
            ],
        ]));

        $observed->data['after'] = $cancellation->cancelled();
        $observed->data['reason'] = $cancellation->reason();

        return JsonRpcResponse::result($request->id, ['ok' => true]);
    }
}

class CancellingStreamingTool extends Tool
{
    protected string $name = 'cancelling-streaming-tool';

    public function handle(): Generator
    {
        yield Response::notification('notifications/progress', ['progress' => 50]);

        /** @var ArrayTransport $transport */
        $transport = app('test.transport');

        ($transport->handler)(json_encode([
            'jsonrpc' => '2.0',
            'method' => 'notifications/cancelled',
            'params' => [
                'requestId' => 789,
                'reason' => 'Stream no longer needed',
            ],
        ]));

        yield Response::text('This should not be sent.');
    }
}
