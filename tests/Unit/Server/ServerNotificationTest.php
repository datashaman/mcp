<?php

declare(strict_types=1);

use Laravel\Mcp\Server\Notifications\ProgressNotification;
use Laravel\Mcp\Server\Transport\FakeTransporter;
use Tests\Fixtures\FakeServerNotification;

it('sends a fire-and-forget notification over the transport', function (): void {
    $transport = new FakeTransporter;

    (new FakeServerNotification($transport))->emit('demo/notice', ['foo' => 'bar']);

    $sent = json_decode($transport->sentNotifications()[0], true);

    expect($sent['jsonrpc'])->toBe('2.0')
        ->and($sent['method'])->toBe('demo/notice')
        ->and($sent['params'])->toBe(['foo' => 'bar'])
        ->and($sent)->not->toHaveKey('id');
});

it('encodes empty params as a JSON object', function (): void {
    $transport = new FakeTransporter;

    (new FakeServerNotification($transport))->emit('demo/notice');

    expect($transport->sentNotifications()[0])->toContain('"params":{}');
});

it('builds a progress notification with only the required fields', function (): void {
    $transport = new FakeTransporter;

    (new ProgressNotification($transport))->send('token-1', 25);

    $sent = json_decode($transport->sentNotifications()[0], true);

    expect($sent['method'])->toBe('notifications/progress')
        ->and($sent['params'])->toBe([
            'progressToken' => 'token-1',
            'progress' => 25,
        ]);
});

it('includes total and message when supplied', function (): void {
    $transport = new FakeTransporter;

    (new ProgressNotification($transport))->send('token-1', 50, total: 100, message: 'Halfway there');

    $sent = json_decode($transport->sentNotifications()[0], true);

    expect($sent['params'])->toBe([
        'progressToken' => 'token-1',
        'progress' => 50,
        'total' => 100,
        'message' => 'Halfway there',
    ]);
});
