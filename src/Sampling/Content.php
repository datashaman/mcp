<?php

declare(strict_types=1);

namespace Laravel\Mcp\Sampling;

use Illuminate\Contracts\Support\Arrayable;
use InvalidArgumentException;

/**
 * A single content block within a sampling message or result.
 *
 * Mirrors the MCP sampling content types: text, image, audio, tool_use,
 * and tool_result.
 *
 * @implements Arrayable<string, mixed>
 */
class Content implements Arrayable
{
    /**
     * @param  array<string, mixed>  $input
     * @param  array<int, Content|array<array-key, mixed>>  $content
     * @param  array<string, mixed>|null  $structuredContent
     * @param  array<string, mixed>  $annotations
     * @param  array<string, mixed>  $meta
     */
    private function __construct(
        public readonly string $type,
        public readonly ?string $text = null,
        public readonly ?string $data = null,
        public readonly ?string $mimeType = null,
        public readonly ?string $id = null,
        public readonly ?string $name = null,
        public readonly array $input = [],
        public readonly ?string $toolUseId = null,
        public readonly array $content = [],
        public readonly ?array $structuredContent = null,
        public readonly ?bool $isError = null,
        public readonly array $annotations = [],
        public readonly array $meta = [],
    ) {}

    /**
     * @param  array<string, mixed>  $annotations
     * @param  array<string, mixed>  $meta
     */
    public static function text(string $text, array $annotations = [], array $meta = []): self
    {
        return new self(type: 'text', text: $text, annotations: $annotations, meta: $meta);
    }

    /**
     * @param  array<string, mixed>  $annotations
     * @param  array<string, mixed>  $meta
     */
    public static function image(string $data, string $mimeType, array $annotations = [], array $meta = []): self
    {
        return new self(type: 'image', data: $data, mimeType: $mimeType, annotations: $annotations, meta: $meta);
    }

    /**
     * @param  array<string, mixed>  $annotations
     * @param  array<string, mixed>  $meta
     */
    public static function audio(string $data, string $mimeType, array $annotations = [], array $meta = []): self
    {
        return new self(type: 'audio', data: $data, mimeType: $mimeType, annotations: $annotations, meta: $meta);
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $meta
     */
    public static function toolUse(string $id, string $name, array $input = [], array $meta = []): self
    {
        return new self(type: 'tool_use', id: $id, name: $name, input: $input, meta: $meta);
    }

    /**
     * @param  array<int, Content|array<array-key, mixed>>  $content
     * @param  array<string, mixed>|null  $structuredContent
     * @param  array<string, mixed>  $meta
     */
    public static function toolResult(
        string $toolUseId,
        array $content,
        ?array $structuredContent = null,
        ?bool $isError = null,
        array $meta = [],
    ): self {
        return new self(
            type: 'tool_result',
            toolUseId: $toolUseId,
            content: $content,
            structuredContent: $structuredContent,
            isError: $isError,
            meta: $meta,
        );
    }

    /**
     * @param  array<string, mixed>  $content
     */
    public static function fromArray(array $content): self
    {
        return match ($content['type'] ?? null) {
            'text' => self::text(
                (string) ($content['text'] ?? ''),
                self::arrayValue($content, 'annotations'),
                self::arrayValue($content, '_meta'),
            ),
            'image' => self::image(
                (string) ($content['data'] ?? ''),
                (string) ($content['mimeType'] ?? ''),
                self::arrayValue($content, 'annotations'),
                self::arrayValue($content, '_meta'),
            ),
            'audio' => self::audio(
                (string) ($content['data'] ?? ''),
                (string) ($content['mimeType'] ?? ''),
                self::arrayValue($content, 'annotations'),
                self::arrayValue($content, '_meta'),
            ),
            'tool_use' => self::toolUse(
                (string) ($content['id'] ?? ''),
                (string) ($content['name'] ?? ''),
                self::arrayValue($content, 'input'),
                self::arrayValue($content, '_meta'),
            ),
            'tool_result' => self::toolResult(
                (string) ($content['toolUseId'] ?? ''),
                self::contentListValue($content, 'content'),
                is_array($content['structuredContent'] ?? null) ? $content['structuredContent'] : null,
                is_bool($content['isError'] ?? null) ? $content['isError'] : null,
                self::arrayValue($content, '_meta'),
            ),
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
        $content = match ($this->type) {
            'text' => ['type' => 'text', 'text' => $this->text],
            'tool_use' => [
                'type' => 'tool_use',
                'id' => $this->id,
                'name' => $this->name,
                'input' => $this->input,
            ],
            'tool_result' => [
                'type' => 'tool_result',
                'toolUseId' => $this->toolUseId,
                'content' => array_map(
                    static fn (Content|array $content): array => $content instanceof Content ? $content->toArray() : $content,
                    array_values($this->content),
                ),
            ],
            default => ['type' => $this->type, 'data' => $this->data, 'mimeType' => $this->mimeType],
        };

        if ($this->structuredContent !== null) {
            $content['structuredContent'] = $this->structuredContent;
        }

        if ($this->isError !== null) {
            $content['isError'] = $this->isError;
        }

        if ($this->annotations !== []) {
            $content['annotations'] = $this->annotations;
        }

        if ($this->meta !== []) {
            $content['_meta'] = $this->meta;
        }

        return $content;
    }

    /**
     * @param  array<string, mixed>  $source
     * @return array<string, mixed>
     */
    protected static function arrayValue(array $source, string $key): array
    {
        return is_array($source[$key] ?? null) ? $source[$key] : [];
    }

    /**
     * @param  array<string, mixed>  $source
     * @return array<int, array<array-key, mixed>>
     */
    protected static function contentListValue(array $source, string $key): array
    {
        $value = $source[$key] ?? null;

        return is_array($value) && array_is_list($value) ? $value : [];
    }
}
