<?php

declare(strict_types=1);

use Laravel\Mcp\Exceptions\JsonRpcException;
use Laravel\Mcp\Server\Methods\SubscribeResource;
use Laravel\Mcp\Server\Methods\UnsubscribeResource;
use Laravel\Mcp\Server\Resources\ResourceSubscriptions;
use Laravel\Mcp\Server\Transport\FakeTransporter;
use Laravel\Mcp\Transport\JsonRpcRequest;
use Laravel\Mcp\Transport\JsonRpcResponse;

it('subscribes to resource updates', function (): void {
    $store = [];
    $subscriptions = new ResourceSubscriptions(new FakeTransporter, $store);
    $method = new SubscribeResource($subscriptions);

    $response = $method->handle(
        new JsonRpcRequest(id: 1, method: 'resources/subscribe', params: ['uri' => 'file:///project/src/main.rs']),
        $this->getServerContext(['serverCapabilities' => ['resources' => ['subscribe' => true]]]),
    );

    expect(json_decode($response->toJson(), true))->toBe([
        'jsonrpc' => '2.0',
        'id' => 1,
        'result' => [],
    ])->and($subscriptions->all())->toBe(['file:///project/src/main.rs']);
});

it('unsubscribes from resource updates', function (): void {
    $store = ['file:///project/src/main.rs'];
    $subscriptions = new ResourceSubscriptions(new FakeTransporter, $store);
    $method = new UnsubscribeResource($subscriptions);

    $response = $method->handle(
        new JsonRpcRequest(id: 2, method: 'resources/unsubscribe', params: ['uri' => 'file:///project/src/main.rs']),
        $this->getServerContext(['serverCapabilities' => ['resources' => ['subscribe' => true]]]),
    );

    expect(json_decode($response->toJson(), true))->toBe([
        'jsonrpc' => '2.0',
        'id' => 2,
        'result' => [],
    ])->and($subscriptions->all())->toBe([]);
});

it('handles duplicate subscribe and unsubscribe requests idempotently', function (): void {
    $store = [];
    $subscriptions = new ResourceSubscriptions(new FakeTransporter, $store);
    $context = $this->getServerContext(['serverCapabilities' => ['resources' => ['subscribe' => true]]]);

    $subscribe = new SubscribeResource($subscriptions);
    $unsubscribe = new UnsubscribeResource($subscriptions);

    $subscribe->handle(new JsonRpcRequest(id: 1, method: 'resources/subscribe', params: ['uri' => 'file:///project']), $context);
    $subscribe->handle(new JsonRpcRequest(id: 2, method: 'resources/subscribe', params: ['uri' => 'file:///project']), $context);

    $unsubscribe->handle(new JsonRpcRequest(id: 3, method: 'resources/unsubscribe', params: ['uri' => 'file:///project']), $context);
    $unsubscribe->handle(new JsonRpcRequest(id: 4, method: 'resources/unsubscribe', params: ['uri' => 'file:///project']), $context);

    expect($subscriptions->all())->toBe([]);
});

it('rejects subscription requests when the capability is disabled', function (): void {
    $store = [];
    $subscriptions = new ResourceSubscriptions(new FakeTransporter, $store);
    $method = new SubscribeResource($subscriptions);

    expect(fn (): JsonRpcResponse => $method->handle(
        new JsonRpcRequest(id: 1, method: 'resources/subscribe', params: ['uri' => 'file:///project']),
        $this->getServerContext(['serverCapabilities' => ['resources' => ['listChanged' => false]]]),
    ))->toThrow(JsonRpcException::class, 'The method [resources/subscribe] was not found.');
});

it('rejects invalid resource subscription URIs', function (): void {
    $store = [];
    $subscriptions = new ResourceSubscriptions(new FakeTransporter, $store);
    $method = new SubscribeResource($subscriptions);

    expect(fn (): JsonRpcResponse => $method->handle(
        new JsonRpcRequest(id: 1, method: 'resources/subscribe', params: []),
        $this->getServerContext(['serverCapabilities' => ['resources' => ['subscribe' => true]]]),
    ))->toThrow(JsonRpcException::class, 'Invalid resource URI.');
});
