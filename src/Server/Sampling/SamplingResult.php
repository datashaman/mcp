<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Sampling;

use Laravel\Mcp\Sampling\Content;

/**
 * The result of a `sampling/createMessage` request returned by the client.
 */
class SamplingResult
{
    /**
     * @param  array<int, Content>  $contentBlocks
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly string $role,
        public readonly Content $content,
        public readonly array $contentBlocks = [],
        public readonly ?string $model = null,
        public readonly ?string $stopReason = null,
        public readonly array $meta = [],
    ) {}

    /**
     * @param  array<string, mixed>  $result
     */
    public static function fromArray(array $result): self
    {
        $content = $result['content'] ?? ['type' => 'text', 'text' => ''];
        $contentBlocks = self::parseContentBlocks($content);
        $firstContent = $contentBlocks[0] ?? Content::text('');

        return new self(
            role: is_string($result['role'] ?? null) ? $result['role'] : 'assistant',
            content: $firstContent,
            contentBlocks: $contentBlocks,
            model: is_string($result['model'] ?? null) ? $result['model'] : null,
            stopReason: is_string($result['stopReason'] ?? null) ? $result['stopReason'] : null,
            meta: is_array($result['_meta'] ?? null) ? $result['_meta'] : [],
        );
    }

    /**
     * Convenience accessor for the textual content of the result.
     */
    public function text(): ?string
    {
        return $this->content->text;
    }

    /**
     * @return array<int, Content>
     */
    protected static function parseContentBlocks(mixed $content): array
    {
        if (! is_array($content)) {
            return [Content::text('')];
        }

        if (array_is_list($content)) {
            return array_map(
                static fn (mixed $block): Content => Content::fromArray(is_array($block) ? $block : []),
                $content,
            );
        }

        return [Content::fromArray($content)];
    }
}
