<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Sampling;

use Laravel\Mcp\Exceptions\JsonRpcException;
use Laravel\Mcp\Sampling\Message;
use Laravel\Mcp\Sampling\ModelPreferences;
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
     *
     * @throws JsonRpcException
     */
    public function createMessage(
        array $messages,
        int $maxTokens,
        ?string $systemPrompt = null,
        ?ModelPreferences $modelPreferences = null,
    ): SamplingResult {
        $this->ensureClientCapability(Server::CAPABILITY_SAMPLING);

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

        if ($modelPreferences !== null && ($preferences = $modelPreferences->toArray()) !== []) {
            $params['modelPreferences'] = $preferences;
        }

        return SamplingResult::fromArray($this->request('sampling/createMessage', $params));
    }
}
