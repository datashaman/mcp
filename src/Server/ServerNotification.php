<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server;

use Laravel\Mcp\Server\Contracts\Transport;
use Laravel\Mcp\Transport\JsonRpcResponse;

/**
 * Base class for server-initiated notifications sent to the connected client.
 *
 * MCP features such as progress reporting and logging let a server send a
 * JSON-RPC notification *to* the client. Unlike {@see ClientRequest}, a
 * notification carries no id and expects no response — it is fire-and-forget.
 * This class owns the shared envelope work: building the notification and
 * dispatching it over the transport. Concrete subclasses expose
 * feature-specific methods on top of {@see notify()}.
 */
abstract class ServerNotification
{
    public function __construct(
        protected Transport $transport,
    ) {}

    /**
     * Send a JSON-RPC notification to the client.
     *
     * @param  array<string, mixed>  $params
     */
    protected function notify(string $method, array $params): void
    {
        $this->transport->sendNotification(
            JsonRpcResponse::notification($method, $params)->toJson(),
        );
    }
}
