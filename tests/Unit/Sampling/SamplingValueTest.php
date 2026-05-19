<?php

declare(strict_types=1);

use Laravel\Mcp\Sampling\Content;
use Laravel\Mcp\Sampling\Message;
use Laravel\Mcp\Sampling\ModelPreferences;
use Laravel\Mcp\Sampling\SamplingTool;
use Laravel\Mcp\Sampling\ToolChoice;

it('builds text content', function (): void {
    expect(Content::text('hello')->toArray())
        ->toBe(['type' => 'text', 'text' => 'hello']);
});

it('builds image and audio content', function (): void {
    expect(Content::image('aGk=', 'image/png')->toArray())
        ->toBe(['type' => 'image', 'data' => 'aGk=', 'mimeType' => 'image/png'])
        ->and(Content::audio('aGk=', 'audio/wav')->toArray())
        ->toBe(['type' => 'audio', 'data' => 'aGk=', 'mimeType' => 'audio/wav']);
});

it('round-trips content through fromArray', function (): void {
    $content = Content::fromArray(['type' => 'text', 'text' => 'hi']);

    expect($content->type)->toBe('text')->and($content->text)->toBe('hi');
});

it('builds tool use and tool result content', function (): void {
    expect(Content::toolUse('call_123', 'get_weather', ['city' => 'Paris'])->toArray())
        ->toBe([
            'type' => 'tool_use',
            'id' => 'call_123',
            'name' => 'get_weather',
            'input' => ['city' => 'Paris'],
        ])
        ->and(Content::toolResult(
            toolUseId: 'call_123',
            content: [Content::text('18C')],
            structuredContent: ['temperature' => 18],
            isError: false,
            meta: ['cached' => true],
        )->toArray())->toBe([
            'type' => 'tool_result',
            'toolUseId' => 'call_123',
            'content' => [
                ['type' => 'text', 'text' => '18C'],
            ],
            'structuredContent' => ['temperature' => 18],
            'isError' => false,
            '_meta' => ['cached' => true],
        ]);
});

it('rejects an unknown content type', function (): void {
    expect(fn (): Content => Content::fromArray(['type' => 'video']))
        ->toThrow(InvalidArgumentException::class);
});

it('builds user and assistant messages', function (): void {
    expect(Message::user('hi')->toArray())
        ->toBe(['role' => 'user', 'content' => ['type' => 'text', 'text' => 'hi']])
        ->and(Message::assistant(Content::text('yo'))->role)
        ->toBe('assistant');
});

it('builds messages with content arrays and meta', function (): void {
    expect(Message::assistant([
        Content::toolUse('call_123', 'get_weather', ['city' => 'Paris']),
    ], meta: ['turn' => 1])->toArray())->toBe([
        'role' => 'assistant',
        'content' => [
            [
                'type' => 'tool_use',
                'id' => 'call_123',
                'name' => 'get_weather',
                'input' => ['city' => 'Paris'],
            ],
        ],
        '_meta' => ['turn' => 1],
    ]);
});

it('serializes sampling tools and tool choice', function (): void {
    $tool = new SamplingTool(
        name: 'get_weather',
        inputSchema: [
            'type' => 'object',
            'properties' => ['city' => ['type' => 'string']],
            'required' => ['city'],
        ],
        description: 'Get current weather',
        title: 'Weather',
        meta: ['provider' => 'local'],
    );

    expect($tool->toArray())->toBe([
        'name' => 'get_weather',
        'inputSchema' => [
            'type' => 'object',
            'properties' => ['city' => ['type' => 'string']],
            'required' => ['city'],
        ],
        'description' => 'Get current weather',
        'title' => 'Weather',
        '_meta' => ['provider' => 'local'],
    ])->and((new ToolChoice('required'))->toArray())->toBe(['mode' => 'required']);
});

it('serializes model preferences, omitting null priorities', function (): void {
    $preferences = new ModelPreferences(
        hints: ['claude-3-sonnet', 'claude'],
        intelligencePriority: 0.8,
    );

    expect($preferences->toArray())->toBe([
        'hints' => [['name' => 'claude-3-sonnet'], ['name' => 'claude']],
        'intelligencePriority' => 0.8,
    ]);
});

it('serializes empty model preferences as an empty array', function (): void {
    expect((new ModelPreferences)->toArray())->toBe([]);
});
