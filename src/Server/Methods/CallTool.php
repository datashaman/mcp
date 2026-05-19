<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Methods;

use Generator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Container\Container;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Exceptions\JsonRpcException;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Contracts\Errable;
use Laravel\Mcp\Server\Contracts\Method;
use Laravel\Mcp\Server\Methods\Concerns\InteractsWithResponses;
use Laravel\Mcp\Server\Methods\Concerns\InteractsWithTasks;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Server\Tasks\Tasks;
use Laravel\Mcp\Server\Tasks\TaskStatusNotification;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Support\ValidationMessages;
use Laravel\Mcp\Transport\JsonRpcRequest;
use Laravel\Mcp\Transport\JsonRpcResponse;

class CallTool implements Errable, Method
{
    use InteractsWithResponses;
    use InteractsWithTasks;

    public function __construct(
        protected ?Tasks $tasks = null,
        protected ?TaskStatusNotification $taskStatusNotification = null,
    ) {}

    /**
     * @return JsonRpcResponse|Generator<JsonRpcResponse>
     *
     * @throws JsonRpcException
     */
    public function handle(JsonRpcRequest $request, ServerContext $context): Generator|JsonRpcResponse
    {
        if (is_null($request->get('name'))) {
            throw new JsonRpcException(
                'Missing [name] parameter.',
                -32602,
                $request->id,
            );
        }

        $tool = $context
            ->tools()
            ->first(
                fn ($tool): bool => $tool->name() === $request->params['name'],
                fn () => throw new JsonRpcException(
                    "Tool [{$request->params['name']}] not found.",
                    -32602,
                    $request->id,
                ));

        if (is_array($request->params['task'] ?? null)) {
            return $this->handleTaskAugmentedCall($request, $context, $tool);
        }

        $response = $this->invokeTool($tool);

        return is_iterable($response)
            ? $this->toJsonRpcStreamedResponse($request, $response, $this->serializable($tool))
            : $this->toJsonRpcResponse($request, $response, $this->serializable($tool));
    }

    /**
     * @throws JsonRpcException
     */
    protected function handleTaskAugmentedCall(JsonRpcRequest $request, ServerContext $context, Tool $tool): JsonRpcResponse
    {
        $this->ensureTasksCapability($request, $context, 'requests.tools.call');

        $taskMetadata = $request->params['task'];
        $ttl = $taskMetadata['ttl'] ?? null;

        if ($ttl !== null && ! is_int($ttl)) {
            throw new JsonRpcException('Invalid task ttl.', -32602, $request->id);
        }

        $tasks = $this->tasks ?? Container::getInstance()->make(Tasks::class);
        $task = $tasks->create($ttl);
        $this->notifyTaskStatus($task);

        $response = $this->invokeTool($tool);

        $result = is_iterable($response)
            ? $this->collectTaskResult($request, $response, $this->serializable($tool))
            : $this->taskResultFromResponse($request, $response, $this->serializable($tool));

        $task = $tasks->complete(
            taskId: $task['taskId'],
            result: $result,
            statusMessage: 'Tool call completed.',
            failed: ($result['isError'] ?? false) === true,
        );
        $this->notifyTaskStatus($task);

        return JsonRpcResponse::result($request->id, [
            'task' => $task,
        ]);
    }

    protected function invokeTool(Tool $tool): mixed
    {
        try {
            // @phpstan-ignore-next-line
            return Container::getInstance()->call([$tool, 'handle']);
        } catch (AuthenticationException|AuthorizationException $authException) {
            return Response::error($authException->getMessage());
        } catch (ValidationException $validationException) {
            return Response::error(ValidationMessages::from($validationException));
        }
    }

    /**
     * @param  iterable<Response|ResponseFactory|string>  $response
     * @return array<string, mixed>
     */
    protected function collectTaskResult(JsonRpcRequest $request, iterable $response, callable $serializable): array
    {
        $result = null;

        foreach ($this->toJsonRpcStreamedResponse($request, $response, $serializable) as $message) {
            $payload = $message->toArray();

            if (isset($payload['result']) && is_array($payload['result'])) {
                $result = $payload['result'];
            }
        }

        if (! is_array($result)) {
            throw new JsonRpcException('Task did not produce a result.', -32603, $request->id);
        }

        return $result;
    }

    /**
     * @param  Response|ResponseFactory|array<int, Response|ResponseFactory|string>|string  $response
     * @return array<string, mixed>
     */
    protected function taskResultFromResponse(JsonRpcRequest $request, Response|ResponseFactory|array|string $response, callable $serializable): array
    {
        $payload = $this->toJsonRpcResponse($request, $response, $serializable)->toArray();

        if (isset($payload['result']) && is_array($payload['result'])) {
            return $payload['result'];
        }

        if (isset($payload['error']) && is_array($payload['error'])) {
            return [
                'content' => [[
                    'type' => 'text',
                    'text' => is_string($payload['error']['message'] ?? null) ? $payload['error']['message'] : 'Tool call failed.',
                ]],
                'isError' => true,
            ];
        }

        throw new JsonRpcException('Invalid task result.', -32603, $request->id);
    }

    /**
     * @param  array<string, mixed>  $task
     */
    protected function notifyTaskStatus(array $task): void
    {
        try {
            ($this->taskStatusNotification ?? Container::getInstance()->make(TaskStatusNotification::class))->send($task);
        } catch (JsonRpcException) {
            //
        }
    }

    /**
     * @return callable(ResponseFactory): array<string, mixed>
     */
    protected function serializable(Tool $tool): callable
    {
        return fn (ResponseFactory $factory): array => $factory->mergeStructuredContent(
            $factory->mergeMeta([
                'content' => $factory->responses()->map(fn (Response $response): array => $response->content()->toTool($tool))->all(),
                'isError' => $factory->responses()->contains(fn (Response $response): bool => $response->isError()),
            ])
        );
    }
}
