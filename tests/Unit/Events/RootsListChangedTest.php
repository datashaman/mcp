<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Laravel\Mcp\Server\Roots\Events\RootsListChanged;
use Tests\Fixtures\ArrayTransport;
use Tests\Fixtures\ExampleServer;

it('dispatches roots list changed events for incoming notifications', function (): void {
    Event::fake([RootsListChanged::class]);

    $transport = new ArrayTransport;
    $server = new ExampleServer($transport);

    $server->start();

    ($transport->handler)(json_encode([
        'jsonrpc' => '2.0',
        'method' => 'notifications/roots/list_changed',
        'params' => [
            '_meta' => ['reason' => 'workspaceChanged'],
        ],
    ]));

    expect($transport->sent)->toHaveCount(0);

    Event::assertDispatched(RootsListChanged::class, fn (RootsListChanged $event): bool => $event->params === [
        '_meta' => ['reason' => 'workspaceChanged'],
    ] && $event->sessionId === $transport->sessionId);
});
