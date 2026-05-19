<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Methods;

use Laravel\Mcp\Exceptions\JsonRpcException;
use Laravel\Mcp\Server\Contracts\Method;
use Laravel\Mcp\Server\Methods\Concerns\InteractsWithTasks;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Server\Tasks\Tasks;
use Laravel\Mcp\Transport\JsonRpcRequest;
use Laravel\Mcp\Transport\JsonRpcResponse;

class GetTask implements Method
{
    use InteractsWithTasks;

    public function __construct(
        protected Tasks $tasks,
    ) {}

    public function handle(JsonRpcRequest $request, ServerContext $context): JsonRpcResponse
    {
        $this->ensureTasksCapability($request, $context);

        try {
            return JsonRpcResponse::result($request->id, $this->tasks->get($this->taskId($request)));
        } catch (JsonRpcException $jsonRpcException) {
            throw new JsonRpcException($jsonRpcException->getMessage(), $jsonRpcException->getCode(), $request->id);
        }
    }
}
