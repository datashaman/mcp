<?php

declare(strict_types=1);

namespace Laravel\Mcp\Sampling;

use Illuminate\Contracts\Support\Arrayable;
use InvalidArgumentException;

/**
 * A single content block within a sampling message or result.
 *
 * Mirrors the MCP sampling content types: text, image, and audio.
 *
 * @implements Arrayable<string, mixed>
 */
class Content implements Arrayable
{
    private function __construct(
        public readonly string $type,
        public readonly ?string $text = null,
        public readonly ?string $data = null,
        public readonly ?string $mimeType = null,
    ) {}

    public static function text(string $text): self
    {
        return new self(type: 'text', text: $text);
    }

    public static function image(string $data, string $mimeType): self
    {
        return new self(type: 'image', data: $data, mimeType: $mimeType);
    }

    public static function audio(string $data, string $mimeType): self
    {
        return new self(type: 'audio', data: $data, mimeType: $mimeType);
    }

    /**
     * @param  array<string, mixed>  $content
     */
    public static function fromArray(array $content): self
    {
        return match ($content['type'] ?? null) {
            'text' => self::text((string) ($content['text'] ?? '')),
            'image' => self::image((string) ($content['data'] ?? ''), (string) ($content['mimeType'] ?? '')),
            'audio' => self::audio((string) ($content['data'] ?? ''), (string) ($content['mimeType'] ?? '')),
            default => throw new InvalidArgumentException(
                'Unsupported sampling content type ['.json_encode($content['type'] ?? null).'].',
            ),
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return match ($this->type) {
            'text' => ['type' => 'text', 'text' => $this->text],
            default => ['type' => $this->type, 'data' => $this->data, 'mimeType' => $this->mimeType],
        };
    }
}
