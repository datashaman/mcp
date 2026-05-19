<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Tasks;

use Illuminate\Container\Container;
use Illuminate\Support\Str;
use Laravel\Mcp\Exceptions\JsonRpcException;
use Laravel\Mcp\Server\Contracts\Transport;
use Laravel\Mcp\Server\Transport\HttpTransport;
use Throwable;

class Tasks
{
    /**
     * @var array<string, array<string, mixed>>
     */
    protected array $tasks;

    /**
     * @param  array<string, array<string, mixed>>  $tasks
     */
    public function __construct(
        protected Transport $transport,
        array &$tasks,
        protected int $ttl = 3600,
    ) {
        $this->tasks = &$tasks;
    }

    /**
     * @return array<string, mixed>
     */
    public function create(?int $ttl = null): array
    {
        $task = [
            'taskId' => Str::uuid()->toString(),
            'status' => TaskStatus::WORKING,
            'createdAt' => $this->timestamp(),
            'lastUpdatedAt' => $this->timestamp(),
            'ttl' => $ttl,
        ];

        $tasks = $this->allWithPayloads();
        $tasks[$task['taskId']] = [
            ...$task,
            'result' => null,
        ];

        $this->store($tasks);

        return $task;
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    public function complete(string $taskId, array $result, ?string $statusMessage = null, bool $failed = false): array
    {
        return $this->update($taskId, [
            'status' => $failed ? TaskStatus::FAILED : TaskStatus::COMPLETED,
            'statusMessage' => $statusMessage,
            'result' => $result,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function cancel(string $taskId, ?string $reason = null): array
    {
        $task = $this->getTaskWithPayload($taskId);

        if (in_array($task['status'] ?? null, [TaskStatus::COMPLETED, TaskStatus::FAILED], true)) {
            throw new JsonRpcException("Task [{$taskId}] is already terminal and cannot be cancelled.", -32002);
        }

        if (($task['status'] ?? null) === TaskStatus::CANCELLED) {
            return $this->publicTask($task);
        }

        return $this->update($taskId, [
            'status' => TaskStatus::CANCELLED,
            'statusMessage' => $reason,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $taskId): array
    {
        $tasks = $this->allWithPayloads();

        if (! isset($tasks[$taskId])) {
            throw new JsonRpcException("Task [{$taskId}] not found.", -32002);
        }

        return $this->publicTask($tasks[$taskId]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        return array_values(array_map(
            $this->publicTask(...),
            $this->allWithPayloads(),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function result(string $taskId): array
    {
        $tasks = $this->allWithPayloads();

        if (! isset($tasks[$taskId])) {
            throw new JsonRpcException("Task [{$taskId}] not found.", -32002);
        }

        $task = $tasks[$taskId];

        if (! in_array($task['status'] ?? null, [TaskStatus::COMPLETED, TaskStatus::FAILED], true)) {
            throw new JsonRpcException("Task [{$taskId}] has no result available.", -32002);
        }

        $result = $task['result'] ?? null;

        if (! is_array($result)) {
            throw new JsonRpcException("Task [{$taskId}] has no result available.", -32002);
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $updates
     * @return array<string, mixed>
     */
    protected function update(string $taskId, array $updates): array
    {
        $tasks = $this->allWithPayloads();
        $existing = $this->getTaskWithPayload($taskId, $tasks);

        $task = [
            ...$existing,
            ...array_filter($updates, static fn (mixed $value): bool => $value !== null),
            'lastUpdatedAt' => $this->timestamp(),
        ];

        $tasks[$taskId] = $task;
        $this->store($tasks);

        return $this->publicTask($task);
    }

    /**
     * @param  array<string, array<string, mixed>>|null  $tasks
     * @return array<string, mixed>
     */
    protected function getTaskWithPayload(string $taskId, ?array $tasks = null): array
    {
        $tasks ??= $this->allWithPayloads();

        if (! isset($tasks[$taskId])) {
            throw new JsonRpcException("Task [{$taskId}] not found.", -32002);
        }

        return $tasks[$taskId];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function allWithPayloads(): array
    {
        $sessionId = $this->transport->sessionId();

        if ($this->transport instanceof HttpTransport && $sessionId !== null) {
            try {
                $tasks = Container::getInstance()->make('cache')->get($this->cacheKey($sessionId));
            } catch (Throwable) {
                return $this->tasks;
            }

            if (is_array($tasks)) {
                return array_filter($tasks, is_array(...));
            }
        }

        return $this->tasks;
    }

    /**
     * @param  array<string, array<string, mixed>>  $tasks
     */
    protected function store(array $tasks): void
    {
        $this->tasks = $tasks;

        $sessionId = $this->transport->sessionId();

        if (! $this->transport instanceof HttpTransport || $sessionId === null) {
            return;
        }

        try {
            Container::getInstance()->make('cache')->put(
                $this->cacheKey($sessionId),
                $tasks,
                $this->ttl,
            );
        } catch (Throwable) {
            //
        }
    }

    /**
     * @param  array<string, mixed>  $task
     * @return array<string, mixed>
     */
    protected function publicTask(array $task): array
    {
        unset($task['result']);

        return $task;
    }

    protected function timestamp(): string
    {
        return gmdate('Y-m-d\TH:i:s\Z');
    }

    protected function cacheKey(string $sessionId): string
    {
        return "mcp:session:{$sessionId}:tasks";
    }
}
