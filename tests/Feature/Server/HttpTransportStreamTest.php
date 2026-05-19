<?php

use Illuminate\Testing\TestResponse;
use Laravel\Mcp\Exceptions\JsonRpcException;
use Laravel\Mcp\Server\Transport\HttpTransport;

it('emits a server-initiated notification over the event stream', function (): void {
    $transport = new HttpTransport(request(), 'test-session', streamingResponse: true);

    $notification = '{"jsonrpc":"2.0","method":"notifications/message","params":{"level":"info"}}';

    // sendNotification() writes straight to the SSE stream via sendStreamMessage(),
    // which ob_flush()es one buffer level down — capture with a nested buffer.
    ob_start();
    ob_start();
    $transport->sendNotification($notification);
    ob_end_flush();
    $content = ob_get_clean();

    expect($content)->toBe("data: {$notification}\n\n");
});

it('throws when sending a notification without a streaming response', function (): void {
    $transport = new HttpTransport(request(), 'test-session');

    expect(fn () => $transport->sendNotification('{"jsonrpc":"2.0","method":"notifications/message","params":{}}'))
        ->toThrow(JsonRpcException::class);
});

it('throws when sending an invalid request message', function (): void {
    $transport = new HttpTransport(request(), 'test-session', streamingResponse: true);

    expect(fn (): string => $transport->sendRequest('not-json'))
        ->toThrow(JsonRpcException::class, 'Invalid server-to-client request: JSON-RPC id is required.');
});

it('throws when sending a request without a session id', function (): void {
    $transport = new HttpTransport(request(), '', streamingResponse: true);

    expect(fn (): string => $transport->sendRequest('{"jsonrpc":"2.0","id":"request-1","method":"demo","params":{}}'))
        ->toThrow(JsonRpcException::class, 'A server-to-client request requires a non-empty MCP session id.');
});

it('streams iterable responses returned from the stream callback', function (): void {
    $transport = new HttpTransport(request(), 'test-session');

    $transport->stream(fn (): iterable => [
        '{"jsonrpc":"2.0","id":1,"result":[]}',
        '{"jsonrpc":"2.0","id":2,"result":[]}',
    ]);

    $response = $transport->run();

    $testResponse = TestResponse::fromBaseResponse($response);
    $content = $testResponse->streamedContent();

    expect($content)->toContain('data: {"jsonrpc":"2.0","id":1,"result":[]}');
    expect($content)->toContain('data: {"jsonrpc":"2.0","id":2,"result":[]}');
});

it('streams generator responses returned from the stream callback', function (): void {
    $transport = new HttpTransport(request(), 'test-session');

    $transport->stream(function (): Generator {
        yield '{"jsonrpc":"2.0","id":3,"result":[]}';
        yield '{"jsonrpc":"2.0","id":4,"result":[]}';
    });

    $response = $transport->run();

    $testResponse = TestResponse::fromBaseResponse($response);
    $content = $testResponse->streamedContent();

    expect($content)->toContain('data: {"jsonrpc":"2.0","id":3,"result":[]}');
    expect($content)->toContain('data: {"jsonrpc":"2.0","id":4,"result":[]}');
});

it('streams generator responses when Octane is flagged', function (): void {
    $_SERVER['LARAVEL_OCTANE'] = '1';

    $transport = new HttpTransport(request(), 'test-session');

    $transport->stream(function (): Generator {
        yield '{"jsonrpc":"2.0","id":5,"result":[]}';
    });

    $response = $transport->run();

    $testResponse = TestResponse::fromBaseResponse($response);
    $content = $testResponse->streamedContent();

    expect($content)->toContain('data: {"jsonrpc":"2.0","id":5,"result":[]}');

    unset($_SERVER['LARAVEL_OCTANE']);
});

it('does not double emit when stream callback echoes directly', function (): void {
    $transport = new HttpTransport(request(), 'test-session');

    $transport->stream(function (): void {
        echo 'data: {"jsonrpc":"2.0","id":99,"result":[]}';
        echo "\n\n";
    });

    $response = $transport->run();

    $testResponse = TestResponse::fromBaseResponse($response);
    $content = $testResponse->streamedContent();

    expect($content)->toBe("data: {\"jsonrpc\":\"2.0\",\"id\":99,\"result\":[]}\n\n");
});
