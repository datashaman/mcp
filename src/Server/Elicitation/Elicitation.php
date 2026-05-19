<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Elicitation;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Support\Str;
use Laravel\Mcp\Enums\ProtocolVersion;
use Laravel\Mcp\Exceptions\JsonRpcException;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\ClientRequest;
use Laravel\Mcp\Server\Elicitation\Events\ElicitationReceived;
use Laravel\Mcp\Server\Elicitation\Events\ElicitationSent;
use Laravel\Mcp\Server\Elicitation\Fields\ElicitField;
use Laravel\Mcp\Transport\JsonRpcResponse;

class Elicitation extends ClientRequest
{
    /**
     * Send a form-mode elicitation request.
     *
     * @param  Closure(ElicitSchema): array<string, ElicitField>|array<string, mixed>  $schema
     */
    public function form(string $message, Closure|array $schema): ElicitationResult
    {
        $this->ensureCapability('form');

        $requestedSchema = $schema instanceof Closure
            ? $this->buildSchema($schema)
            : $schema;

        $params = [
            'message' => $message,
            'requestedSchema' => $requestedSchema,
        ];

        if ($this->supportsElicitationModes()) {
            $params = ['mode' => 'form', ...$params];
        }

        return $this->send($params);
    }

    /**
     * Send a URL-mode elicitation request.
     */
    public function url(string $message, string $url, ?string $elicitationId = null): ElicitationResult
    {
        $this->ensureCapability('url');

        $elicitationId ??= Str::uuid()->toString();

        $result = $this->send([
            'mode' => 'url',
            'message' => $message,
            'url' => $url,
            'elicitationId' => $elicitationId,
        ]);

        $result->setElicitationId($elicitationId);

        return $result;
    }

    /**
     * Send a completion notification for URL mode elicitation.
     */
    public function notifyComplete(string $elicitationId): void
    {
        $this->ensureCapability('url');

        $this->transport->sendNotification(JsonRpcResponse::notification(
            method: 'notifications/elicitation/complete',
            params: ['elicitationId' => $elicitationId],
        )->toJson());
    }

    /**
     * @param  Closure(ElicitSchema): array<string, ElicitField>  $callback
     * @return array<string, mixed>
     */
    protected function buildSchema(Closure $callback): array
    {
        $schema = new ElicitSchema;
        $fields = $callback($schema);

        $properties = [];
        $required = [];

        foreach ($fields as $name => $field) {
            $properties[$name] = $field->toArray();

            if ($field->isRequired()) {
                $required[] = $name;
            }
        }

        $result = [
            'type' => 'object',
            'properties' => $properties,
        ];

        if ($required !== []) {
            $result['required'] = $required;
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $params
     *
     * @throws JsonRpcException
     */
    protected function send(array $params): ElicitationResult
    {
        $result = $this->request('elicitation/create', $params);
        $requestId = $this->lastRequestId() ?? '';

        Container::getInstance()->make('events')->dispatch(new ElicitationSent(
            mode: $params['mode'] ?? 'form',
            message: $params['message'],
            requestId: $requestId,
        ));

        $elicitationResult = new ElicitationResult(
            action: $result['action'] ?? 'cancel',
            content: $result['content'] ?? null,
        );

        Container::getInstance()->make('events')->dispatch(new ElicitationReceived(
            action: $elicitationResult->action(),
            requestId: $requestId,
            hasContent: $elicitationResult->all() !== [],
        ));

        return $elicitationResult;
    }

    /**
     * @throws JsonRpcException
     */
    protected function ensureCapability(string $mode): void
    {
        $this->ensureProtocolSupports('elicitation', [
            ProtocolVersion::V2025_06_18->value,
            ProtocolVersion::V2025_11_25->value,
        ]);

        $this->ensureClientCapability(Server::CAPABILITY_ELICITATION);

        $elicitation = $this->clientCapabilities[Server::CAPABILITY_ELICITATION] ?? [];

        if (! $this->supportsElicitationModes()) {
            if ($mode === 'form') {
                return;
            }

            throw new JsonRpcException(
                "Protocol version [{$this->protocolVersion}] does not support elicitation mode [{$mode}].",
                -32602,
            );
        }

        // json_decode('{}', true) === [] in PHP, so empty array = empty object = form-only
        $supportedModes = is_array($elicitation) && $elicitation !== []
            ? array_keys($elicitation)
            : ['form'];

        if (! in_array($mode, $supportedModes, true)) {
            throw new JsonRpcException(
                "Client does not support elicitation mode [{$mode}]. The connected client only supports form mode. Use form() instead, or connect a client that declares URL elicitation support.",
                -32602,
            );
        }
    }

    protected function supportsElicitation(): bool
    {
        return in_array($this->protocolVersion, [
            ProtocolVersion::V2025_06_18->value,
            ProtocolVersion::V2025_11_25->value,
        ], true);
    }

    protected function supportsElicitationModes(): bool
    {
        return $this->protocolVersion === ProtocolVersion::V2025_11_25->value;
    }
}
