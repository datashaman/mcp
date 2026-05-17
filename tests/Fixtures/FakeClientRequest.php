<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Laravel\Mcp\Server\ClientRequest;

/**
 * Concrete {@see ClientRequest} used to exercise the shared base in tests.
 */
class FakeClientRequest extends ClientRequest
{
    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function call(string $method, array $params = []): array
    {
        return $this->request($method, $params);
    }

    public function requireCapability(string $capability): void
    {
        $this->ensureClientCapability($capability);
    }

    /**
     * @param  array<int, string>  $protocolVersions
     */
    public function requireProtocol(string $feature, array $protocolVersions): void
    {
        $this->ensureProtocolSupports($feature, $protocolVersions);
    }
}
