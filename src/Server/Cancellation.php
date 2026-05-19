<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server;

class Cancellation
{
    protected int|string|null $currentRequestId = null;

    /**
     * @var array<string, true>
     */
    protected array $active = [];

    /**
     * @var array<string, string|null>
     */
    protected array $cancelled = [];

    public function start(int|string $requestId): void
    {
        $this->currentRequestId = $requestId;
        $this->active[$this->key($requestId)] = true;
    }

    public function finish(int|string $requestId): void
    {
        $key = $this->key($requestId);

        unset($this->active[$key], $this->cancelled[$key]);

        if ($this->currentRequestId !== null && $this->key($this->currentRequestId) === $key) {
            $this->currentRequestId = null;
        }
    }

    public function cancel(int|string|null $requestId, ?string $reason = null): bool
    {
        if ($requestId === null) {
            return false;
        }

        $key = $this->key($requestId);

        if (! isset($this->active[$key])) {
            return false;
        }

        $this->cancelled[$key] = $reason;

        return true;
    }

    public function cancelled(int|string|null $requestId = null): bool
    {
        $requestId ??= $this->currentRequestId;

        if ($requestId === null) {
            return false;
        }

        return array_key_exists($this->key($requestId), $this->cancelled);
    }

    public function reason(int|string|null $requestId = null): ?string
    {
        $requestId ??= $this->currentRequestId;

        if ($requestId === null) {
            return null;
        }

        return $this->cancelled[$this->key($requestId)] ?? null;
    }

    protected function key(int|string $requestId): string
    {
        return (string) $requestId;
    }
}
