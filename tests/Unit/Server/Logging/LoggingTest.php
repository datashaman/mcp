<?php

declare(strict_types=1);

use Laravel\Mcp\Exceptions\JsonRpcException;
use Laravel\Mcp\Server\Logging\Logging;
use Laravel\Mcp\Server\Transport\FakeTransporter;

it('emits message notifications with level logger and data', function (): void {
    $transport = new FakeTransporter;
    $logging = new Logging($transport, enabled: true, threshold: 'debug');

    $logging->error(['message' => 'Connection failed'], logger: 'database');

    $sent = json_decode($transport->sentNotifications()[0], true);

    expect($sent['method'])->toBe('notifications/message')
        ->and($sent['params'])->toBe([
            'level' => 'error',
            'data' => ['message' => 'Connection failed'],
            'logger' => 'database',
        ]);
});

it('omits logger when not provided', function (): void {
    $transport = new FakeTransporter;
    $logging = new Logging($transport, enabled: true, threshold: 'debug');

    $logging->info('Started');

    $sent = json_decode($transport->sentNotifications()[0], true);

    expect($sent['params'])->toBe([
        'level' => 'info',
        'data' => 'Started',
    ]);
});

it('filters messages below the configured threshold', function (): void {
    $transport = new FakeTransporter;
    $logging = new Logging($transport, enabled: true, threshold: 'warning');

    $logging->info('Hidden');
    $logging->warning('Visible');
    $logging->critical('Also visible');

    expect($transport->sentNotifications())->toHaveCount(2);

    $first = json_decode($transport->sentNotifications()[0], true);
    $second = json_decode($transport->sentNotifications()[1], true);

    expect($first['params']['level'])->toBe('warning')
        ->and($second['params']['level'])->toBe('critical');
});

it('does not emit when logging is disabled', function (): void {
    $transport = new FakeTransporter;
    $logging = new Logging($transport, enabled: false, threshold: 'debug');

    $logging->emergency('Suppressed');

    expect($transport->sentNotifications())->toBe([]);
});

it('rejects invalid logging levels', function (): void {
    $logging = new Logging(new FakeTransporter, enabled: true);

    expect(fn (): mixed => $logging->send('verbose', 'Nope'))
        ->toThrow(JsonRpcException::class, 'Invalid logging level [verbose].');
});
