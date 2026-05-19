<?php

declare(strict_types=1);

use Laravel\Mcp\Exceptions\JsonRpcException;
use Laravel\Mcp\Server\Roots\Root;
use Laravel\Mcp\Server\Roots\Roots;
use Laravel\Mcp\Server\Transport\FakeTransporter;

it('sends a roots list request and returns roots', function (): void {
    $transport = new FakeTransporter;
    $transport->expectResponse([
        'roots' => [
            [
                'uri' => 'file:///Users/taylor/project',
                'name' => 'Project',
                '_meta' => ['source' => 'workspace'],
            ],
            [
                'uri' => 'file:///Users/taylor/notes',
            ],
        ],
    ]);

    $roots = new Roots($transport, ['roots' => ['listChanged' => true]]);

    $result = $roots->list();

    expect($result)->toHaveCount(2)
        ->and($result[0])->toBeInstanceOf(Root::class)
        ->and($result[0]->uri)->toBe('file:///Users/taylor/project')
        ->and($result[0]->name)->toBe('Project')
        ->and($result[0]->meta)->toBe(['source' => 'workspace'])
        ->and($result[1]->toArray())->toBe(['uri' => 'file:///Users/taylor/notes']);

    $sent = $transport->sentRequests()[0];

    expect($sent['method'])->toBe('roots/list')
        ->and($sent['params'])->toBe([]);
});

it('fails when the client does not declare the roots capability', function (): void {
    $roots = new Roots(new FakeTransporter, clientCapabilities: []);

    expect(fn (): array => $roots->list())
        ->toThrow(JsonRpcException::class, 'Client does not support [roots].');
});

it('surfaces client roots list errors', function (): void {
    $transport = new FakeTransporter;
    $transport->expectError(
        code: -32601,
        message: 'Roots not supported',
        data: ['reason' => 'Client does not have roots capability'],
    );

    $roots = new Roots($transport, ['roots' => []]);

    expect(fn (): array => $roots->list())
        ->toThrow(JsonRpcException::class, 'Roots not supported');
});

it('requires file uri roots from client results', function (): void {
    $transport = new FakeTransporter;
    $transport->expectResponse([
        'roots' => [
            ['uri' => 'https://example.com/project'],
        ],
    ]);

    $roots = new Roots($transport, ['roots' => []]);

    expect(fn (): array => $roots->list())
        ->toThrow(JsonRpcException::class, 'Root URIs must be file:// URIs.');
});
