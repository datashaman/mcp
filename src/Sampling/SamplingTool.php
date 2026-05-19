<?php

declare(strict_types=1);

namespace Laravel\Mcp\Sampling;

use Illuminate\Contracts\Support\Arrayable;

/**
 * @implements Arrayable<string, mixed>
 */
class SamplingTool implements Arrayable
{
    /**
     * @param  array<string, mixed>  $inputSchema
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly string $name,
        public readonly array $inputSchema,
        public readonly ?string $description = null,
        public readonly ?string $title = null,
        public readonly array $meta = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $tool = [
            'name' => $this->name,
            'inputSchema' => $this->inputSchema,
        ];

        if ($this->description !== null) {
            $tool['description'] = $this->description;
        }

        if ($this->title !== null) {
            $tool['title'] = $this->title;
        }

        if ($this->meta !== []) {
            $tool['_meta'] = $this->meta;
        }

        return $tool;
    }
}
