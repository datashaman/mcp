<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Notifications;

use Laravel\Mcp\Server\ServerNotification;

class ToolListChangedNotification extends ServerNotification
{
    public function send(): void
    {
        $this->notify('notifications/tools/list_changed', []);
    }
}
