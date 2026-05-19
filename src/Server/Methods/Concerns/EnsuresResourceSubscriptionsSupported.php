<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Methods\Concerns;

use Laravel\Mcp\Exceptions\JsonRpcException;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Transport\JsonRpcRequest;

trait EnsuresResourceSubscriptionsSupported
{
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
