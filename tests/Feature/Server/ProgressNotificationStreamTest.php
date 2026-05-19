<?php

declare(strict_types=1);

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Notifications\ProgressNotification;
use Laravel\Mcp\Server\Registrar;
use Laravel\Mcp\Server\Tool;
use Tests\Fixtures\ArrayTransport;

class ProgressNotificationServer extends Server
{
    protected array $tools = [
        ProgressNotificationTool::class,
    ];

    protected function generateSessionId(): string
    {
        return 'progress-notification-session';
    }
}

class ProgressNotificationTool extends Tool
{
    protected string $name = 'progress-notification-tool';

    public function handle(Request $request, ProgressNotification $progress): Generator
    {
        $progress->send('progress-token-1', 1, total: 2, message: 'Halfway');

        yield Response::text('Finished progress work.');
    }
}

it('streams progress notifications before the final result over stdio-style transport', function (): void {
    $transport = new ArrayTransport;
    $server = new ProgressNotificationServer($transport);

    $server->start();

    ($transport->handler)(json_encode(callProgressNotificationToolMessage()));

    $messages = array_map(fn (string $message): mixed => json_decode($message, true), $transport->sent);

    expect($messages)->toHaveCount(2)
        ->and($messages[0])->toEqual(expectedProgressNotification())
        ->and($messages[1])->toEqual(expectedProgressNotificationToolResult());
});

it('streams progress notifications before the final result over http sse', function (): void {
    app(Registrar::class)->web('test-mcp-progress', ProgressNotificationServer::class);

    $sessionId = initializeProgressNotificationConnection($this);

    $response = $this->postJson(
        'test-mcp-progress',
        callProgressNotificationToolMessage(),
        ['MCP-Session-Id' => $sessionId, 'Accept' => 'text/event-stream'],
    );

    $response->assertStatus(200);

    expect(strtolower((string) $response->headers->get('Content-Type')))->toBe('text/event-stream; charset=utf-8');

    $messages = parseJsonRpcMessagesFromSseStream($response->streamedContent());

    expect($messages)->toHaveCount(2)
        ->and($messages[0])->toEqual(expectedProgressNotification())
        ->and($messages[1])->toEqual(expectedProgressNotificationToolResult());
});

function initializeProgressNotificationConnection($that): string
{
    $response = $that->postJson('test-mcp-progress', [
        'jsonrpc' => '2.0',
        'id' => 456,
        'method' => 'initialize',
        'params' => [],
    ]);

    $response->assertStatus(200);

    $sessionId = $response->headers->get('MCP-Session-Id');

    expect($sessionId)->toBeString();

    return $sessionId;
}

function callProgressNotificationToolMessage(): array
{
    return [
        'jsonrpc' => '2.0',
        'id' => 88,
        'method' => 'tools/call',
        'params' => [
            'name' => 'progress-notification-tool',
            'arguments' => [],
        ],
    ];
}

function expectedProgressNotification(): array
{
    return [
        'jsonrpc' => '2.0',
        'method' => 'notifications/progress',
        'params' => [
            'progressToken' => 'progress-token-1',
            'progress' => 1,
            'total' => 2,
            'message' => 'Halfway',
        ],
    ];
}

function expectedProgressNotificationToolResult(): array
{
    return [
        'jsonrpc' => '2.0',
        'id' => 88,
        'result' => [
            'content' => [[
                'type' => 'text',
                'text' => 'Finished progress work.',
            ]],
            'isError' => false,
        ],
    ];
}
