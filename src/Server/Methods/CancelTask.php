<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Methods;

use Laravel\Mcp\Exceptions\JsonRpcException;
use Laravel\Mcp\Server\Contracts\Method;
use Laravel\Mcp\Server\Methods\Concerns\InteractsWithTasks;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Server\Tasks\Tasks;
use Laravel\Mcp\Server\Tasks\TaskStatusNotification;
use Laravel\Mcp\Transport\JsonRpcRequest;
use Laravel\Mcp\Transport\JsonRpcResponse;

class CancelTask implements Method
{
    use InteractsWithTasks;

    public function __construct(
        protected Tasks $tasks,
        protected TaskStatusNotification $notification,
    ) {}

    public function handle(JsonRpcRequest $request, ServerContext $context): JsonRpcResponse
    {
        $this->ensureTasksCapability($request, $context, 'cancel');

        $reason = $request->params['reason'] ?? null;

        try {
            $task = $this->tasks->cancel($this->taskId($request), is_string($reason) ? $reason : null);
        } catch (JsonRpcException $jsonRpcException) {
            throw new JsonRpcException($jsonRpcException->getMessage(), $jsonRpcException->getCode(), $request->id);
        }

        try {
            $this->notification->send($task);
        } catch (JsonRpcException) {
            //
        }

        return JsonRpcResponse::result($request->id, $task);
    }
}
