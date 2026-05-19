<?php

declare(strict_types=1);

namespace Laravel\Mcp\Sampling;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Server preferences for how the client should select a model when sampling.
 *
 * Priorities are advisory and normalized to the 0..1 range; hints are model
 * name substrings the client may map to an equivalent model it has access to.
 *
 * @implements Arrayable<string, mixed>
 */
class ModelPreferences implements Arrayable
{
    /**
     * @param  array<int, string>  $hints
     */
    public function __construct(
        public readonly array $hints = [],
        public readonly ?float $costPriority = null,
        public readonly ?float $speedPriority = null,
        public readonly ?float $intelligencePriority = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $preferences = [];

        if ($this->hints !== []) {
            $preferences['hints'] = array_map(
                static fn (string $hint): array => ['name' => $hint],
                array_values($this->hints),
            );
        }

        foreach ([
            'costPriority' => $this->costPriority,
            'speedPriority' => $this->speedPriority,
            'intelligencePriority' => $this->intelligencePriority,
        ] as $key => $value) {
            if ($value !== null) {
                $preferences[$key] = $value;
            }
        }

        return $preferences;
    }
}
