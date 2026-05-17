<?php

declare(strict_types=1);

use Laravel\Mcp\Enums\ProtocolVersion;
use Laravel\Mcp\Exceptions\JsonRpcException;
use Laravel\Mcp\Server\Transport\FakeTransporter;
use Tests\Fixtures\FakeClientRequest;

it('sends a request to the client and returns the result', function (): void {
    $transport = new FakeTransporter;
    $transport->expectResponse(['answer' => 42]);

    $clientRequest = new FakeClientRequest($transport);

    $result = $clientRequest->call('demo/method', ['foo' => 'bar']);

    expect($result)->toBe(['answer' => 42]);

    $sent = $transport->sentRequests()[0];
    expect($sent['jsonrpc'])->toBe('2.0')
        ->and($sent['method'])->toBe('demo/method')
        ->and($sent['params'])->toBe(['foo' => 'bar'])
        ->and($sent['id'])->toBeString();
});

it('throws when the client returns an error', function (): void {
    $transport = new FakeTransporter;
    $transport->expectResponse([]);

    // Replace the queued result with an error envelope.
    $reflection = new ReflectionClass($transport);
    $property = $reflection->getProperty('queuedResponses');
    $property->setValue($transport, [(string) json_encode([
        'jsonrpc' => '2.0',
        'id' => '_placeholder_',
        'error' => ['code' => -32601, 'message' => 'Method not found'],
    ])]);

    $clientRequest = new FakeClientRequest($transport);

    expect(fn (): array => $clientRequest->call('demo/method'))
        ->toThrow(JsonRpcException::class, 'Method not found');
});

it('rejects a missing client capability', function (): void {
    $clientRequest = new FakeClientRequest(new FakeTransporter, clientCapabilities: []);

    expect(fn () => $clientRequest->requireCapability('sampling'))
        ->toThrow(JsonRpcException::class);
});

it('accepts a declared client capability', function (): void {
    $clientRequest = new FakeClientRequest(new FakeTransporter, clientCapabilities: ['sampling' => []]);

    $clientRequest->requireCapability('sampling');

    expect(true)->toBeTrue();
});

it('rejects an unsupported protocol version', function (): void {
    $clientRequest = new FakeClientRequest(
        new FakeTransporter,
        protocolVersion: ProtocolVersion::V2024_11_05->value,
    );

    expect(fn () => $clientRequest->requireProtocol('sampling', [ProtocolVersion::V2025_11_25->value]))
        ->toThrow(JsonRpcException::class);
});
