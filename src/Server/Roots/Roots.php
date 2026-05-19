<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Roots;

use Laravel\Mcp\Exceptions\JsonRpcException;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\ClientRequest;

class Roots extends ClientRequest
{
    /**
     * Request the connected client's available filesystem roots.
     *
     * @return array<int, Root>
     *
     * @throws JsonRpcException
     */
    public function list(): array
    {
        $this->ensureClientCapability(Server::CAPABILITY_ROOTS);

        $result = $this->request('roots/list', []);
        $roots = $result['roots'] ?? [];

        if (! is_array($roots)) {
            throw new JsonRpcException('Client returned an invalid roots/list result.', -32603);
        }

        return array_map(
            static function (mixed $root): Root {
                if (! is_array($root)) {
                    throw new JsonRpcException('Client returned an invalid root entry.', -32603);
                }

                return Root::fromArray($root);
            },
            array_values($roots),
        );
    }
}
