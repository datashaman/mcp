<?php

declare(strict_types=1);

use Laravel\Mcp\Server\ClientRequest;
use Laravel\Mcp\Server\Notifications\ProgressNotification;
use Laravel\Mcp\Server\ServerNotification;
use Laravel\Mcp\Server\Transport\FakeTransporter;
use Tests\Fixtures\ExampleServer;
use Tests\Fixtures\FakeClientRequest;

it('builds a client request wired to the transport', function (): void {
    $transport = new FakeTransporter;
    $transport->expectResponse(['ok' => true]);

    $server = new ExampleServer($transport);

    $clientRequest = (fn (): ClientRequest => $this->clientRequest(FakeClientRequest::class))->call($server);

    expect($clientRequest)->toBeInstanceOf(FakeClientRequest::class)
        ->and($clientRequest->call('demo/method'))->toBe(['ok' => true]);
});

it('passes negotiated client capabilities into the request', function (): void {
    $server = new ExampleServer(new FakeTransporter);

    // Simulate capabilities negotiated during initialization.
    (fn (): array => $this->clientCapabilities = ['sampling' => []])->call($server);

    $clientRequest = (fn (): ClientRequest => $this->clientRequest(FakeClientRequest::class))->call($server);

    // requireCapability() throws unless resolveClientCapabilities() fed the request.
    $clientRequest->requireCapability('sampling');

    expect(true)->toBeTrue();
});

it('builds a server notification wired to the transport', function (): void {
    $transport = new FakeTransporter;
    $server = new ExampleServer($transport);

    $notification = (fn (): ServerNotification => $this->serverNotification(ProgressNotification::class))->call($server);

    expect($notification)->toBeInstanceOf(ProgressNotification::class);

    $notification->send('tok-1', 50);

    $sent = json_decode($transport->sentNotifications()[0], true);

    expect($sent['method'])->toBe('notifications/progress')
        ->and($sent['params']['progress'])->toBe(50);
});
