<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Concerns;

use Laravel\Mcp\Server\Attributes\Icon;
use ReflectionAttribute;
use ReflectionClass;

trait HasIcons
{
    /**
     * @var array<int, array{src: string, mimeType?: string, sizes?: array<int, string>, theme?: string}>
     */
    protected array $icons = [];

    /**
     * @var array<class-string, array<int, array{src: string, mimeType?: string, sizes?: array<int, string>, theme?: string}>|null>
     */
    protected static array $iconAttributeCache = [];

    /**
     * @return array<int, array{src: string, mimeType?: string, sizes?: array<int, string>, theme?: string}>
     */
    public function icons(): array
    {
        $class = static::class;

        if (! array_key_exists($class, static::$iconAttributeCache)) {
            $attributes = (new ReflectionClass($this))
                ->getAttributes(Icon::class);

            static::$iconAttributeCache[$class] = $attributes === []
                ? null
                : array_map(
                    static fn (ReflectionAttribute $attribute): array => $attribute->newInstance()->toArray(),
                    $attributes,
                );
        }

        if (static::$iconAttributeCache[$class] !== null) {
            return static::$iconAttributeCache[$class];
        }

        return $this->icons;
    }

    /**
     * @template T of array<string, mixed>
     *
     * @param  T  $baseArray
     * @return T&array{icons?: array<int, array{src: string, mimeType?: string, sizes?: array<int, string>, theme?: string}>}
     */
    protected function mergeIcons(array $baseArray): array
    {
        $icons = $this->icons();

        return $icons === []
            ? $baseArray
            : [...$baseArray, 'icons' => $icons];
    }
}
