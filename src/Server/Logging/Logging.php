<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Logging;

use Laravel\Mcp\Exceptions\JsonRpcException;
use Laravel\Mcp\Server\Contracts\Transport;
use Laravel\Mcp\Server\ServerNotification;

class Logging extends ServerNotification
{
    public function __construct(
        Transport $transport,
        protected bool $enabled = false,
        protected string $threshold = LogLevel::DEFAULT,
    ) {
        parent::__construct($transport);
    }

    /**
     * Send a structured MCP log message if logging is enabled and the level is at
     * or above the configured threshold. Servers default to `debug`, which emits
     * all levels until the client sends `logging/setLevel`.
     *
     * @throws JsonRpcException
     */
    public function send(string $level, mixed $data, ?string $logger = null): void
    {
        if (! LogLevel::isValid($level)) {
            throw new JsonRpcException("Invalid logging level [{$level}].", -32602);
        }

        if (! $this->enabled || ! LogLevel::shouldSend($level, $this->threshold)) {
            return;
        }

        $params = [
            'level' => $level,
            'data' => $data,
        ];

        if ($logger !== null) {
            $params['logger'] = $logger;
        }

        $this->notify('notifications/message', $params);
    }

    public function debug(mixed $data, ?string $logger = null): void
    {
        $this->send('debug', $data, $logger);
    }

    public function info(mixed $data, ?string $logger = null): void
    {
        $this->send('info', $data, $logger);
    }

    public function notice(mixed $data, ?string $logger = null): void
    {
        $this->send('notice', $data, $logger);
    }

    public function warning(mixed $data, ?string $logger = null): void
    {
        $this->send('warning', $data, $logger);
    }

    public function error(mixed $data, ?string $logger = null): void
    {
        $this->send('error', $data, $logger);
    }

    public function critical(mixed $data, ?string $logger = null): void
    {
        $this->send('critical', $data, $logger);
    }

    public function alert(mixed $data, ?string $logger = null): void
    {
        $this->send('alert', $data, $logger);
    }

    public function emergency(mixed $data, ?string $logger = null): void
    {
        $this->send('emergency', $data, $logger);
    }
}
