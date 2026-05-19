<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Notifications;

use Laravel\Mcp\Server\ServerNotification;

class PromptListChangedNotification extends ServerNotification
{
    public function send(): void
    {
        $this->notify('notifications/prompts/list_changed', []);
    }
}
