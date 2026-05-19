<?php

declare(strict_types=1);

use Illuminate\Http\Request as HttpRequest;
use Laravel\Mcp\Server\Resources\ResourceSubscriptions;
use Laravel\Mcp\Server\Resources\ResourceUpdatedNotification;
use Laravel\Mcp\Server\Transport\FakeTransporter;
use Laravel\Mcp\Server\Transport\HttpTransport;

it('matches exact and nested resource updates', function (): void {
    $store = [];
    $subscriptions = new ResourceSubscriptions(new FakeTransporter, $store);

    $subscriptions->subscribe('file:///project');

    expect($subscriptions->subscribedTo('file:///project'))->toBeTrue()
        ->and($subscriptions->subscribedTo('file:///project/src/main.rs'))->toBeTrue()
        ->and($subscriptions->subscribedTo('file:///project-other/src/main.rs'))->toBeFalse();
});

it('sends resource updated notifications only for subscribed resources', function (): void {
    $transport = new FakeTransporter;
    $store = [];
    $subscriptions = new ResourceSubscriptions($transport, $store);
    $notification = new ResourceUpdatedNotification($transport, $subscriptions);

    $notification->send('file:///project/src/main.rs');

    expect($transport->sentNotifications())->toBe([]);

    $subscriptions->subscribe('file:///project');
    $notification->send('file:///project/src/main.rs');

    $sent = json_decode($transport->sentNotifications()[0], true);

    expect($sent)->toBe([
        'jsonrpc' => '2.0',
        'method' => 'notifications/resources/updated',
        'params' => [
            'uri' => 'file:///project/src/main.rs',
        ],
    ]);

    $subscriptions->unsubscribe('file:///project');
    $notification->send('file:///project/src/main.rs');

    expect($transport->sentNotifications())->toHaveCount(1);
});

it('persists resource subscriptions per http session', function (): void {
    $firstStore = [];
    $secondStore = [];

    $first = new ResourceSubscriptions(
        new HttpTransport(HttpRequest::create('/mcp', 'POST'), 'session-one'),
        $firstStore,
    );
    $second = new ResourceSubscriptions(
        new HttpTransport(HttpRequest::create('/mcp', 'POST'), 'session-two'),
        $secondStore,
    );

    $first->subscribe('file:///project');

    $reloaded = new ResourceSubscriptions(
        new HttpTransport(HttpRequest::create('/mcp', 'POST'), 'session-one'),
        $firstStore,
    );

    expect($reloaded->subscribedTo('file:///project'))->toBeTrue()
        ->and($second->subscribedTo('file:///project'))->toBeFalse();
});

it('does not cache resource subscriptions for empty http session ids', function (): void {
    $firstStore = [];
    $secondStore = [];

    $first = new ResourceSubscriptions(
        new HttpTransport(HttpRequest::create('/mcp', 'POST'), ''),
        $firstStore,
    );
    $second = new ResourceSubscriptions(
        new HttpTransport(HttpRequest::create('/mcp', 'POST'), ''),
        $secondStore,
    );

    $first->subscribe('file:///project');

    expect($first->subscribedTo('file:///project'))->toBeTrue()
        ->and($second->subscribedTo('file:///project'))->toBeFalse();
});
