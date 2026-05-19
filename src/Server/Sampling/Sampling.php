<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Sampling;

use Laravel\Mcp\Exceptions\JsonRpcException;
use Laravel\Mcp\Sampling\Message;
use Laravel\Mcp\Sampling\ModelPreferences;
use Laravel\Mcp\Sampling\SamplingTool;
use Laravel\Mcp\Sampling\ToolChoice;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\ClientRequest;

/**
 * Requests an LLM generation from the connected client via `sampling/createMessage`.
 *
 * Sampling lets a server obtain a model completion through the client, so the
 * server needs no model API keys of its own. The client keeps control of model
 * access, selection, and user approval.
 *
 * @see https://modelcontextprotocol.io/specification/2025-11-25/client/sampling
 */
class Sampling extends ClientRequest
{
    /**
     * Request a message generation from the client.
     *
     * @param  array<int, Message>  $messages
     * @param  array<int, string>  $stopSequences
     * @param  array<string, mixed>  $metadata
     * @param  array<int, SamplingTool|array<string, mixed>>  $tools
     * @param  ToolChoice|array{mode?: string}|null  $toolChoice
     *
     * @throws JsonRpcException
     */
    public function createMessage(
        array $messages,
        int $maxTokens,
        ?string $systemPrompt = null,
        ?ModelPreferences $modelPreferences = null,
        ?string $includeContext = null,
        ?float $temperature = null,
        array $stopSequences = [],
        array $metadata = [],
        array $tools = [],
        ToolChoice|array|null $toolChoice = null,
    ): SamplingResult {
        $this->ensureClientCapability(Server::CAPABILITY_SAMPLING);
        $this->ensureContextSupport($includeContext);
        $this->ensureToolSupport($tools, $toolChoice);

        $params = [
            'messages' => array_map(
                static fn (Message $message): array => $message->toArray(),
                array_values($messages),
            ),
            'maxTokens' => $maxTokens,
        ];

        if ($systemPrompt !== null) {
            $params['systemPrompt'] = $systemPrompt;
        }

        if ($modelPreferences instanceof ModelPreferences && ($preferences = $modelPreferences->toArray()) !== []) {
            $params['modelPreferences'] = $preferences;
        }

        if ($includeContext !== null) {
            $params['includeContext'] = $includeContext;
        }

        if ($temperature !== null) {
            $params['temperature'] = $temperature;
        }

        if ($stopSequences !== []) {
            $params['stopSequences'] = array_values($stopSequences);
        }

        if ($metadata !== []) {
            $params['metadata'] = $metadata;
        }

        if ($tools !== []) {
            $params['tools'] = array_map(
                static fn (SamplingTool|array $tool): array => $tool instanceof SamplingTool ? $tool->toArray() : $tool,
                array_values($tools),
            );
        }

        if ($toolChoice !== null) {
            $params['toolChoice'] = $toolChoice instanceof ToolChoice ? $toolChoice->toArray() : $toolChoice;
        }

        return SamplingResult::fromArray($this->request('sampling/createMessage', $params));
    }

    /**
     * @throws JsonRpcException
     */
    protected function ensureContextSupport(?string $includeContext): void
    {
        if ($includeContext === null || $includeContext === 'none') {
            return;
        }

        if (! in_array($includeContext, ['thisServer', 'allServers'], true)) {
            throw new JsonRpcException('Invalid sampling includeContext value.', -32602);
        }

        if (! $this->supportsSamplingFeature('context')) {
            throw new JsonRpcException(
                'Client does not support sampling context inclusion. Ensure the MCP client declares sampling.context before using includeContext values other than none.',
                -32602,
            );
        }
    }

    /**
     * @param  array<int, SamplingTool|array<string, mixed>>  $tools
     * @param  ToolChoice|array{mode?: string}|null  $toolChoice
     *
     * @throws JsonRpcException
     */
    protected function ensureToolSupport(array $tools, ToolChoice|array|null $toolChoice): void
    {
        if ($tools === [] && $toolChoice === null) {
            return;
        }

        if (! $this->supportsSamplingFeature('tools')) {
            throw new JsonRpcException(
                'Client does not support sampling tools. Ensure the MCP client declares sampling.tools before sending tools or toolChoice.',
                -32602,
            );
        }
    }

    protected function supportsSamplingFeature(string $feature): bool
    {
        $sampling = $this->clientCapabilities[Server::CAPABILITY_SAMPLING] ?? null;

        return is_array($sampling) && array_key_exists($feature, $sampling);
    }
}
