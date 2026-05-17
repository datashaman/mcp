<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Laravel\Mcp\Server\ServerNotification;

/**
 * Concrete {@see ServerNotification} used to exercise the shared base in tests.
 */
class FakeServerNotification extends ServerNotification
{
    /**
     * @param  array<string, mixed>  $params
     */
    public function emit(string $method, array $params = []): void
    {
        $this->notify($method, $params);
    }
}
