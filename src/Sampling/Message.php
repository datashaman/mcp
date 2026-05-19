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
    /**
     * @param  Content|array<int, Content>  $content
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly string $role,
        public readonly Content|array $content,
        public readonly array $meta = [],
    ) {}

    /**
     * @param  Content|array<int, Content>|string  $content
     * @param  array<string, mixed>  $meta
     */
    public static function user(Content|array|string $content, array $meta = []): self
    {
        return new self('user', self::toContent($content), $meta);
    }

    /**
     * @param  Content|array<int, Content>|string  $content
     * @param  array<string, mixed>  $meta
     */
    public static function assistant(Content|array|string $content, array $meta = []): self
    {
        return new self('assistant', self::toContent($content), $meta);
    }

    /**
     * @param  Content|array<int, Content>|string  $content
     * @return Content|array<int, Content>
     */
    private static function toContent(Content|array|string $content): Content|array
    {
        return is_string($content) ? Content::text($content) : $content;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $message = [
            'role' => $this->role,
            'content' => is_array($this->content)
                ? array_map(static fn (Content $content): array => $content->toArray(), array_values($this->content))
                : $this->content->toArray(),
        ];

        if ($this->meta !== []) {
            $message['_meta'] = $this->meta;
        }

        return $message;
    }
}
