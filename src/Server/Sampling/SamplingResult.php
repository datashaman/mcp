<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Sampling;

use Laravel\Mcp\Sampling\Content;

/**
 * The result of a `sampling/createMessage` request returned by the client.
 */
class SamplingResult
{
    public function __construct(
        public readonly string $role,
        public readonly Content $content,
        public readonly ?string $model = null,
        public readonly ?string $stopReason = null,
    ) {}

    /**
     * @param  array<string, mixed>  $result
     */
    public static function fromArray(array $result): self
    {
        $content = $result['content'] ?? ['type' => 'text', 'text' => ''];

        return new self(
            role: is_string($result['role'] ?? null) ? $result['role'] : 'assistant',
            content: Content::fromArray(is_array($content) ? $content : []),
            model: is_string($result['model'] ?? null) ? $result['model'] : null,
            stopReason: is_string($result['stopReason'] ?? null) ? $result['stopReason'] : null,
        );
    }

    /**
     * Convenience accessor for the textual content of the result.
     */
    public function text(): ?string
    {
        return $this->content->text;
    }
}
