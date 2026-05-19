<?php

declare(strict_types=1);

use Laravel\Mcp\Exceptions\JsonRpcException;
use Laravel\Mcp\Sampling\Content;
use Laravel\Mcp\Sampling\Message;
use Laravel\Mcp\Sampling\ModelPreferences;
use Laravel\Mcp\Sampling\SamplingTool;
use Laravel\Mcp\Sampling\ToolChoice;
use Laravel\Mcp\Server\Sampling\Sampling;
use Laravel\Mcp\Server\Sampling\SamplingResult;
use Laravel\Mcp\Server\Transport\FakeTransporter;

function samplingTransport(): FakeTransporter
{
    $transport = new FakeTransporter;
    $transport->expectResponse([
        'role' => 'assistant',
        'content' => ['type' => 'text', 'text' => 'The capital of France is Paris.'],
        'model' => 'claude-3-sonnet-20240307',
        'stopReason' => 'endTurn',
    ]);

    return $transport;
}

it('sends a createMessage request and returns the result', function (): void {
    $transport = samplingTransport();
    $sampling = new Sampling($transport, ['sampling' => []]);

    $result = $sampling->createMessage(
        messages: [Message::user('What is the capital of France?')],
        maxTokens: 100,
        systemPrompt: 'You are a helpful assistant.',
        modelPreferences: new ModelPreferences(hints: ['claude-3-sonnet'], intelligencePriority: 0.8),
    );

    expect($result)->toBeInstanceOf(SamplingResult::class)
        ->and($result->role)->toBe('assistant')
        ->and($result->text())->toBe('The capital of France is Paris.')
        ->and($result->model)->toBe('claude-3-sonnet-20240307')
        ->and($result->stopReason)->toBe('endTurn');

    $sent = $transport->sentRequests()[0];
    expect($sent['method'])->toBe('sampling/createMessage')
        ->and($sent['params']['messages'])->toBe([
            ['role' => 'user', 'content' => ['type' => 'text', 'text' => 'What is the capital of France?']],
        ])
        ->and($sent['params']['maxTokens'])->toBe(100)
        ->and($sent['params']['systemPrompt'])->toBe('You are a helpful assistant.')
        ->and($sent['params']['modelPreferences'])->toBe([
            'hints' => [['name' => 'claude-3-sonnet']],
            'intelligencePriority' => 0.8,
        ]);
});

it('omits optional params when not provided', function (): void {
    $transport = samplingTransport();
    $sampling = new Sampling($transport, ['sampling' => []]);

    $sampling->createMessage([Message::user('hi')], maxTokens: 50);

    $params = $transport->sentRequests()[0]['params'];
    expect($params)->not->toHaveKey('systemPrompt')
        ->and($params)->not->toHaveKey('modelPreferences');
});

it('sends newer optional scalar fields', function (): void {
    $transport = samplingTransport();
    $sampling = new Sampling($transport, ['sampling' => []]);

    $sampling->createMessage(
        messages: [Message::user('hi')],
        maxTokens: 50,
        includeContext: 'none',
        temperature: 0.2,
        stopSequences: ['END'],
        metadata: ['provider' => 'example'],
    );

    $params = $transport->sentRequests()[0]['params'];

    expect($params['includeContext'])->toBe('none')
        ->and($params['temperature'])->toBe(0.2)
        ->and($params['stopSequences'])->toBe(['END'])
        ->and($params['metadata'])->toBe(['provider' => 'example']);
});

it('requires sampling context capability for deprecated context inclusion values', function (): void {
    $sampling = new Sampling(new FakeTransporter, ['sampling' => []]);

    expect(fn (): mixed => $sampling->createMessage(
        messages: [Message::user('hi')],
        maxTokens: 50,
        includeContext: 'thisServer',
    ))->toThrow(JsonRpcException::class, 'Client does not support sampling context inclusion.');
});

it('allows context inclusion when the client declares sampling context', function (): void {
    $transport = samplingTransport();
    $sampling = new Sampling($transport, ['sampling' => ['context' => []]]);

    $sampling->createMessage(
        messages: [Message::user('hi')],
        maxTokens: 50,
        includeContext: 'allServers',
    );

    expect($transport->sentRequests()[0]['params']['includeContext'])->toBe('allServers');
});

it('reports invalid context inclusion values', function (): void {
    $sampling = new Sampling(new FakeTransporter, ['sampling' => ['context' => []]]);

    expect(fn (): mixed => $sampling->createMessage(
        messages: [Message::user('hi')],
        maxTokens: 50,
        includeContext: 'elsewhere',
    ))->toThrow(JsonRpcException::class, 'Invalid sampling includeContext value ["elsewhere"]. Expected one of: none, thisServer, allServers.');
});

it('requires sampling tools capability when tools are provided', function (): void {
    $sampling = new Sampling(new FakeTransporter, ['sampling' => []]);

    expect(fn (): mixed => $sampling->createMessage(
        messages: [Message::user('hi')],
        maxTokens: 50,
        tools: [
            new SamplingTool('get_weather', ['type' => 'object']),
        ],
    ))->toThrow(JsonRpcException::class, 'Client does not support sampling tools.');
});

it('rejects invalid raw sampling tools', function (): void {
    $sampling = new Sampling(new FakeTransporter, ['sampling' => ['tools' => []]]);

    /** @var array<int, SamplingTool|array<string, mixed>> $tools */
    $tools = ['invalid'];

    expect(fn (): mixed => $sampling->createMessage(
        messages: [Message::user('hi')],
        maxTokens: 50,
        tools: $tools,
    ))->toThrow(JsonRpcException::class, 'Invalid sampling tool at index [0]; expected SamplingTool or array.');

    expect(fn (): mixed => $sampling->createMessage(
        messages: [Message::user('hi')],
        maxTokens: 50,
        tools: [['inputSchema' => ['type' => 'object']]],
    ))->toThrow(JsonRpcException::class, 'Invalid sampling tool at index [0]; expected non-empty string [name].');
});

it('rejects invalid raw sampling tool choices', function (): void {
    $sampling = new Sampling(new FakeTransporter, ['sampling' => ['tools' => []]]);

    expect(fn (): mixed => $sampling->createMessage(
        messages: [Message::user('hi')],
        maxTokens: 50,
        toolChoice: [],
    ))->toThrow(JsonRpcException::class, 'Invalid sampling toolChoice mode [null]. Expected one of: auto, required, none.');

    expect(fn (): mixed => $sampling->createMessage(
        messages: [Message::user('hi')],
        maxTokens: 50,
        toolChoice: ['mode' => 'sometimes'],
    ))->toThrow(JsonRpcException::class, 'Invalid sampling toolChoice mode ["sometimes"]. Expected one of: auto, required, none.');
});

it('sends tools and tool choice when supported', function (): void {
    $transport = samplingTransport();
    $sampling = new Sampling($transport, ['sampling' => ['tools' => []]]);

    $sampling->createMessage(
        messages: [Message::user('weather')],
        maxTokens: 100,
        tools: [
            new SamplingTool(
                name: 'get_weather',
                inputSchema: ['type' => 'object'],
                description: 'Get current weather',
            ),
        ],
        toolChoice: new ToolChoice('auto'),
    );

    $params = $transport->sentRequests()[0]['params'];

    expect($params['tools'])->toBe([
        [
            'name' => 'get_weather',
            'inputSchema' => ['type' => 'object'],
            'description' => 'Get current weather',
        ],
    ])->and($params['toolChoice'])->toBe(['mode' => 'auto']);
});

it('parses richer response content blocks and meta', function (): void {
    $transport = new FakeTransporter;
    $transport->expectResponse([
        'role' => 'assistant',
        'content' => [
            [
                'type' => 'tool_use',
                'id' => 'call_123',
                'name' => 'get_weather',
                'input' => ['city' => 'Paris'],
            ],
            [
                'type' => 'text',
                'text' => 'Checking weather',
            ],
        ],
        'model' => 'claude-3-sonnet-20240307',
        'stopReason' => 'toolUse',
        '_meta' => ['requestId' => 'abc'],
    ]);

    $sampling = new Sampling($transport, ['sampling' => ['tools' => []]]);

    $result = $sampling->createMessage(
        messages: [
            Message::assistant([
                Content::toolUse('call_123', 'get_weather', ['city' => 'Paris']),
            ]),
        ],
        maxTokens: 100,
    );

    expect($result->contentBlocks)->toHaveCount(2)
        ->and($result->contentBlocks[0]->type)->toBe('tool_use')
        ->and($result->contentBlocks[0]->id)->toBe('call_123')
        ->and($result->contentBlocks[0]->input)->toBe(['city' => 'Paris'])
        ->and($result->contentBlocks[1]->text)->toBe('Checking weather')
        ->and($result->content)->toBe($result->contentBlocks[0])
        ->and($result->meta)->toBe(['requestId' => 'abc']);
});

it('keeps a default content block when the client returns an empty content list', function (): void {
    $result = SamplingResult::fromArray([
        'role' => 'assistant',
        'content' => [],
    ]);

    expect($result->contentBlocks)->toHaveCount(1)
        ->and($result->content)->toBe($result->contentBlocks[0])
        ->and($result->text())->toBe('');
});

it('fails when the client does not declare the sampling capability', function (): void {
    $sampling = new Sampling(new FakeTransporter, clientCapabilities: []);

    expect(fn (): mixed => $sampling->createMessage([Message::user('hi')], maxTokens: 50))
        ->toThrow(JsonRpcException::class);
});
