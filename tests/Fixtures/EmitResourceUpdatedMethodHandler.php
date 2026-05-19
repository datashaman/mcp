<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Laravel\Mcp\Server\Contracts\Method;
use Laravel\Mcp\Server\Resources\ResourceUpdatedNotification;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Transport\JsonRpcRequest;
use Laravel\Mcp\Transport\JsonRpcResponse;

class EmitResourceUpdatedMethodHandler implements Method
{
    public function __construct(
        protected ResourceUpdatedNotification $notification,
    ) {}

    public function handle(JsonRpcRequest $request, ServerContext $context): JsonRpcResponse
    {
        $uri = $request->params['uri'] ?? null;

        if (is_string($uri)) {
            $this->notification->send($uri);
        }

        return JsonRpcResponse::result($request->id, []);
    }
}
