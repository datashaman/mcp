<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Methods\Concerns;

use Laravel\Mcp\Exceptions\JsonRpcException;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Transport\JsonRpcRequest;

trait InteractsWithTasks
{
    /**
     * @throws JsonRpcException
     */
    protected function ensureTasksCapability(JsonRpcRequest $request, ServerContext $context, ?string $path = null): void
    {
        $tasks = $context->serverCapabilities[Server::CAPABILITY_TASKS] ?? null;

        if (! is_array($tasks)) {
            throw new JsonRpcException(
                "The method [{$request->method}] was not found.",
                -32601,
                $request->id,
            );
        }

        if ($path === null && $tasks !== []) {
            return;
        }

        $target = data_get($tasks, $path);

        if (is_array($target) || is_object($target) || $target === true) {
            return;
        }

        throw new JsonRpcException(
            "The method [{$request->method}] was not found.",
            -32601,
            $request->id,
        );
    }

    /**
     * @throws JsonRpcException
     */
    protected function taskId(JsonRpcRequest $request): string
    {
        $taskId = $request->params['taskId'] ?? null;

        if (! is_string($taskId) || $taskId === '') {
            throw new JsonRpcException('Invalid task id.', -32602, $request->id);
        }

        return $taskId;
    }
}
