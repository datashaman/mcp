<?php

declare(strict_types=1);

namespace Laravel\Mcp\Sampling;

use Illuminate\Contracts\Support\Arrayable;

/**
 * A single message in a sampling conversation.
 *
 * MCP sampling uses two roles, "user" and "assistant".
 *
 * @implements Arrayable<string, mixed>
 */
class Message implements Arrayable
{
    public function __construct(
        public readonly string $role,
        public readonly Content $content,
    ) {}

    public static function user(Content|string $content): self
    {
        return new self('user', self::toContent($content));
    }

    public static function assistant(Content|string $content): self
    {
        return new self('assistant', self::toContent($content));
    }

    private static function toContent(Content|string $content): Content
    {
        return $content instanceof Content ? $content : Content::text($content);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'role' => $this->role,
            'content' => $this->content->toArray(),
        ];
    }
}
