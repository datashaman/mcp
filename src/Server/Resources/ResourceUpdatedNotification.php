<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Resources;

use Laravel\Mcp\Server\Contracts\Transport;
use Laravel\Mcp\Server\ServerNotification;

class ResourceUpdatedNotification extends ServerNotification
{
    public function __construct(
        Transport $transport,
        protected ResourceSubscriptions $subscriptions,
    ) {
        parent::__construct($transport);
    }

    public function send(string $uri): void
    {
        if (! $this->subscriptions->subscribedTo($uri)) {
            return;
        }

        $this->notify('notifications/resources/updated', [
            'uri' => $uri,
        ]);
    }
}
