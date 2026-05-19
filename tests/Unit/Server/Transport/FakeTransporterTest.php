<?php

declare(strict_types=1);

use Laravel\Mcp\Server\Transport\FakeTransporter;

it('records sent notifications for assertion', function (): void {
    $transport = new FakeTransporter;

    expect($transport->sentNotifications())->toBe([]);

    $transport->sendNotification('{"jsonrpc":"2.0","method":"notifications/message","params":{"level":"info"}}');
    $transport->sendNotification('{"jsonrpc":"2.0","method":"notifications/resources/updated","params":{}}');

    expect($transport->sentNotifications())->toBe([
        '{"jsonrpc":"2.0","method":"notifications/message","params":{"level":"info"}}',
        '{"jsonrpc":"2.0","method":"notifications/resources/updated","params":{}}',
    ]);
});

it('keeps notifications separate from requests and messages', function (): void {
    $transport = new FakeTransporter;

    $transport->sendNotification('{"jsonrpc":"2.0","method":"notifications/message","params":{}}');

    expect($transport->sentNotifications())->toHaveCount(1)
        ->and($transport->sentRequests())->toBe([])
        ->and($transport->sentMessages())->toBe([]);
});

it('rejects invalid request messages with a clear exception', function (): void {
    $transport = new FakeTransporter;

    expect(fn (): string => $transport->sendRequest('not-json'))
        ->toThrow(LogicException::class, 'Invalid JSON-RPC request message.');
});
