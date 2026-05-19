<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Logging;

class LogLevel
{
    /**
     * @var array<string, int>
     */
    public const LEVELS = [
        'debug' => 0,
        'info' => 1,
        'notice' => 2,
        'warning' => 3,
        'error' => 4,
        'critical' => 5,
        'alert' => 6,
        'emergency' => 7,
    ];

    public const DEFAULT = 'debug';

    public static function isValid(string $level): bool
    {
        return array_key_exists($level, self::LEVELS);
    }

    public static function shouldSend(string $level, string $threshold): bool
    {
        if (! self::isValid($level) || ! self::isValid($threshold)) {
            return false;
        }

        return self::LEVELS[$level] >= self::LEVELS[$threshold];
    }
}
