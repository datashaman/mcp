<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Resources;

use Illuminate\Container\Container;
use Laravel\Mcp\Server\Contracts\Transport;
use Laravel\Mcp\Server\Transport\HttpTransport;
use Throwable;

class ResourceSubscriptions
{
    /**
     * @var array<int, string>
     */
    protected array $subscriptions;

    /**
     * @param  array<int, string>  $subscriptions
     */
    public function __construct(
        protected Transport $transport,
        array &$subscriptions,
        protected int $ttl = 3600,
    ) {
        $this->subscriptions = &$subscriptions;
    }

    public function subscribe(string $uri): void
    {
        $subscriptions = $this->all();

        if (! in_array($uri, $subscriptions, true)) {
            $subscriptions[] = $uri;
        }

        $this->store($subscriptions);
    }

    public function unsubscribe(string $uri): void
    {
        $this->store(array_values(array_filter(
            $this->all(),
            static fn (string $subscription): bool => $subscription !== $uri,
        )));
    }

    public function subscribedTo(string $uri): bool
    {
        foreach ($this->all() as $subscription) {
            if ($subscription === $uri) {
                return true;
            }

            $prefix = str_ends_with($subscription, '/') ? $subscription : "{$subscription}/";

            if (str_starts_with($uri, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    public function all(): array
    {
        $sessionId = $this->transport->sessionId();

        if ($this->transport instanceof HttpTransport && $sessionId !== null && $sessionId !== '') {
            try {
                $subscriptions = Container::getInstance()->make('cache')->get($this->cacheKey($sessionId));
            } catch (Throwable) {
                return $this->subscriptions;
            }

            if (is_array($subscriptions)) {
                return array_values(array_filter(
                    $subscriptions,
                    is_string(...),
                ));
            }
        }

        return $this->subscriptions;
    }

    /**
     * @param  array<int, string>  $subscriptions
     */
    protected function store(array $subscriptions): void
    {
        $this->subscriptions = $subscriptions;

        $sessionId = $this->transport->sessionId();

        if (! $this->transport instanceof HttpTransport || $sessionId === null || $sessionId === '') {
            return;
        }

        try {
            Container::getInstance()->make('cache')->put(
                $this->cacheKey($sessionId),
                $subscriptions,
                $this->ttl,
            );
        } catch (Throwable) {
            //
        }
    }

    protected function cacheKey(string $sessionId): string
    {
        return "mcp:session:{$sessionId}:resourceSubscriptions";
    }
}
