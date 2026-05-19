<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Roots;

use Laravel\Mcp\Exceptions\JsonRpcException;

class Root
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly string $uri,
        public readonly ?string $name = null,
        public readonly array $meta = [],
    ) {}

    /**
     * @param  array<string, mixed>  $root
     *
     * @throws JsonRpcException
     */
    public static function fromArray(array $root): self
    {
        $uri = $root['uri'] ?? null;

        if (! is_string($uri) || ! str_starts_with($uri, 'file://')) {
            throw new JsonRpcException('Client returned an invalid root URI. Root URIs must be file:// URIs.', -32603);
        }

        $name = $root['name'] ?? null;
        $meta = $root['_meta'] ?? [];

        return new self(
            uri: $uri,
            name: is_string($name) ? $name : null,
            meta: is_array($meta) ? $meta : [],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $root = ['uri' => $this->uri];

        if ($this->name !== null) {
            $root['name'] = $this->name;
        }

        if ($this->meta !== []) {
            $root['_meta'] = $this->meta;
        }

        return $root;
    }
}
