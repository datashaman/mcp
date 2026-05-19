<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Roots\Events;

class RootsListChanged
{
    /**
     * @param  array<string, mixed>  $params
     */
    public function __construct(
        public readonly array $params = [],
        public readonly ?string $sessionId = null,
    ) {}
}
