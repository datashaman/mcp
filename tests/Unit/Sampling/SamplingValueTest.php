<?php

declare(strict_types=1);

use Laravel\Mcp\Sampling\Content;
use Laravel\Mcp\Sampling\Message;
use Laravel\Mcp\Sampling\ModelPreferences;

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
