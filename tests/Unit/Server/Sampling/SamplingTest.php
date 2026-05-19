<?php

declare(strict_types=1);

use Laravel\Mcp\Exceptions\JsonRpcException;
use Laravel\Mcp\Sampling\Message;
use Laravel\Mcp\Sampling\ModelPreferences;
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

it('fails when the client does not declare the sampling capability', function (): void {
    $sampling = new Sampling(new FakeTransporter, clientCapabilities: []);

    expect(fn (): mixed => $sampling->createMessage([Message::user('hi')], maxTokens: 50))
        ->toThrow(JsonRpcException::class);
});
