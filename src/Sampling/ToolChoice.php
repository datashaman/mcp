<?php

declare(strict_types=1);

namespace Laravel\Mcp\Sampling;

use Illuminate\Contracts\Support\Arrayable;
use InvalidArgumentException;

/**
 * @implements Arrayable<string, mixed>
 */
class ToolChoice implements Arrayable
{
    /**
     * @var array<int, string>
     */
    public const MODES = ['auto', 'required', 'none'];

    public function __construct(
        public readonly string $mode = 'auto',
    ) {
        if (! in_array($mode, self::MODES, true)) {
            throw new InvalidArgumentException("Unsupported sampling tool choice mode [{$mode}].");
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['mode' => $this->mode];
    }
}
