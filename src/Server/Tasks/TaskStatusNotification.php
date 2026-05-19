<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Tasks;

use Laravel\Mcp\Server\ServerNotification;

class TaskStatusNotification extends ServerNotification
{
    /**
     * @param  array<string, mixed>  $task
     */
    public function send(array $task): void
    {
        $this->notify('notifications/tasks/status', $task);
    }
}
