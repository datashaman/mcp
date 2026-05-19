<?php

use Laravel\Mcp\Exceptions\JsonRpcException;
use Laravel\Mcp\Server\Tasks\Tasks;
use Laravel\Mcp\Server\Transport\FakeTransporter;

it('does not cancel completed tasks', function (): void {
    $store = [];
    $tasks = new Tasks(new FakeTransporter, $store);
    $task = $tasks->create();

    $tasks->complete($task['taskId'], [
        'content' => [],
        'isError' => false,
    ]);

    expect(fn (): array => $tasks->cancel($task['taskId']))
        ->toThrow(JsonRpcException::class, "Task [{$task['taskId']}] is already terminal and cannot be cancelled.")
        ->and($tasks->get($task['taskId'])['status'])->toBe('completed');
});

it('does not cancel failed tasks', function (): void {
    $store = [];
    $tasks = new Tasks(new FakeTransporter, $store);
    $task = $tasks->create();

    $tasks->complete($task['taskId'], [
        'content' => [],
        'isError' => true,
    ], failed: true);

    expect(fn (): array => $tasks->cancel($task['taskId']))
        ->toThrow(JsonRpcException::class, "Task [{$task['taskId']}] is already terminal and cannot be cancelled.")
        ->and($tasks->get($task['taskId'])['status'])->toBe('failed');
});
