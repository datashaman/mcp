<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class Icon
{
    /**
     * @param  array<int, string>  $sizes
     */
    public function __construct(
        public string $src,
        public ?string $mimeType = null,
        public array $sizes = [],
        public ?string $theme = null,
    ) {}

    /**
     * @return array{src: string, mimeType?: string, sizes?: array<int, string>, theme?: string}
     */
    public function toArray(): array
    {
        $icon = ['src' => $this->src];

        if ($this->mimeType !== null) {
            $icon['mimeType'] = $this->mimeType;
        }

        if ($this->sizes !== []) {
            $icon['sizes'] = $this->sizes;
        }

        if ($this->theme !== null) {
            $icon['theme'] = $this->theme;
        }

        return $icon;
    }
}
