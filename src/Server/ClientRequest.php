<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server;

use Illuminate\Support\Str;
use Laravel\Mcp\Enums\ProtocolVersion;
use Laravel\Mcp\Exceptions\JsonRpcException;
use Laravel\Mcp\Server\Contracts\Transport;
use Laravel\Mcp\Transport\JsonRpcResponse;

/**
 * Base class for server-initiated requests sent to the connected client.
 *
 * MCP features such as elicitation and sampling let a server send a JSON-RPC
 * request *to* the client and block for its response. This class owns the
 * shared envelope work: building the request, dispatching it over the
 * transport, matching the response id, and surfacing client errors. Concrete
 * subclasses expose feature-specific methods on top of {@see request()}.
 */
abstract class ClientRequest
{
    protected ?string $lastRequestId = null;

    /**
     * @param  array<string, mixed>|null  $clientCapabilities
     */
    public function __construct(
        protected Transport $transport,
        protected ?array $clientCapabilities = null,
        protected ?string $protocolVersion = null,
    ) {
        $this->protocolVersion ??= ProtocolVersion::LATEST->value;
    }

    /**
     * Send a JSON-RPC request to the client and return the decoded result.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     *
     * @throws JsonRpcException
     */
    protected function request(string $method, array $params): array
    {
        $id = Str::uuid()->toString();
        $this->lastRequestId = $id;

        $rawResponse = $this->transport->sendRequest(
            JsonRpcResponse::request($id, $method, $params)->toJson(),
        );

        $response = json_decode($rawResponse, true);

        if (! is_array($response) || ($response['id'] ?? null) !== $id) {
            throw new JsonRpcException(
                "Client response id mismatch: expected [{$id}].",
                -32603,
            );
        }

        if (isset($response['error']) && is_array($response['error'])) {
            throw new JsonRpcException(
                message: is_string($response['error']['message'] ?? null) ? $response['error']['message'] : 'Client request failed.',
                code: is_int($response['error']['code'] ?? null) ? $response['error']['code'] : -32603,
                data: is_array($response['error']['data'] ?? null) ? $response['error']['data'] : null,
            );
        }

        return is_array($response['result'] ?? null) ? $response['result'] : [];
    }

    protected function lastRequestId(): ?string
    {
        return $this->lastRequestId;
    }

    /**
     * Ensure the connected client declared the given capability during initialization.
     *
     * @throws JsonRpcException
     */
    protected function ensureClientCapability(string $capability): void
    {
        if (! array_key_exists($capability, $this->clientCapabilities ?? [])) {
            throw new JsonRpcException(
                "Client does not support [{$capability}]. Ensure the MCP client declares it in its capabilities during initialization.",
                -32602,
            );
        }
    }

    /**
     * Ensure the negotiated protocol version is one that supports the named feature.
     *
     * @param  array<int, string>  $protocolVersions
     *
     * @throws JsonRpcException
     */
    protected function ensureProtocolSupports(string $feature, array $protocolVersions): void
    {
        if (! in_array($this->protocolVersion, $protocolVersions, true)) {
            throw new JsonRpcException(
                "Protocol version [{$this->protocolVersion}] does not support [{$feature}].",
                -32602,
            );
        }
    }
}
