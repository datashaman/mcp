<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Notifications;

use Laravel\Mcp\Server\ServerNotification;

/**
 * A `notifications/progress` message reporting how far a long-running,
 * server-side operation has advanced.
 *
 * The {@see $progressToken} ties the update back to the request that started
 * the work — the client supplies it via the originating request's `_meta`.
 *
 * @see https://modelcontextprotocol.io/specification/basic/utilities/progress
 */
class ProgressNotification extends ServerNotification
{
    /**
     * Send a progress update to the client.
     *
     * @param  string|int  $progressToken  The token from the originating request's `_meta.progressToken`.
     * @param  int|float  $progress  Work completed so far; must increase with each notification.
     * @param  int|float|null  $total  Total work expected, when known.
     * @param  string|null  $message  Optional human-readable status description.
     */
    public function send(
        string|int $progressToken,
        int|float $progress,
        int|float|null $total = null,
        ?string $message = null,
    ): void {
        $params = [
            'progressToken' => $progressToken,
            'progress' => $progress,
        ];

        if ($total !== null) {
            $params['total'] = $total;
        }

        if ($message !== null) {
            $params['message'] = $message;
        }

        $this->notify('notifications/progress', $params);
    }
}
