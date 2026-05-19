<?php

declare(strict_types=1);

namespace Laravel\Mcp\Transport;

use Laravel\Mcp\Exceptions\JsonRpcException;

class JsonRpcParams
{
    /**
     * @return array<string, mixed>
     */
    public static function from(mixed $params, int|string|null $requestId = null): array
    {
        if ($params === null) {
            return [];
        }

        if (is_array($params)) {
            return $params;
        }

        throw new JsonRpcException('Invalid Request: The [params] member must be an object or array.', -32600, $requestId);
    }
}
