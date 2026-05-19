<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Methods;

use Laravel\Mcp\Exceptions\JsonRpcException;
use Laravel\Mcp\Server\Contracts\Method;
use Laravel\Mcp\Server\Resources\ResourceSubscriptions;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Transport\JsonRpcRequest;
use Laravel\Mcp\Transport\JsonRpcResponse;

class UnsubscribeResource implements Method
{
    public function __construct(
        protected ResourceSubscriptions $subscriptions,
    ) {}

    /**
     * @throws JsonRpcException
     */
    public function handle(JsonRpcRequest $request, ServerContext $context): JsonRpcResponse
    {
        $this->ensureSupported($request, $context);

        $uri = $request->params['uri'] ?? null;

        if (! is_string($uri) || $uri === '') {
            throw new JsonRpcException('Invalid resource URI.', -32602, $request->id);
        }

        $this->subscriptions->unsubscribe($uri);

        return JsonRpcResponse::result($request->id, []);
    }

    /**
     * @throws JsonRpcException
     */
    protected function ensureSupported(JsonRpcRequest $request, ServerContext $context): void
    {
        $resources = $context->serverCapabilities['resources'] ?? [];

        if (is_array($resources) && ($resources['subscribe'] ?? false) === true) {
            return;
        }

        throw new JsonRpcException(
            "The method [{$request->method}] was not found.",
            -32601,
            $request->id,
        );
    }
}
