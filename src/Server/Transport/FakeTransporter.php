<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Transport;

use Closure;
use Illuminate\Http\Response;
use Laravel\Mcp\Server\Contracts\Transport;
use LogicException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FakeTransporter implements Transport
{
    /**
     * @var array<int, string>
     */
    protected array $queuedResponses = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    protected array $sentRequests = [];

    /**
     * @var array<int, string>
     */
    protected array $sentMessages = [];

    /**
     * @var array<int, string>
     */
    protected array $sentNotifications = [];

    public function onReceive(Closure $handler): void
    {
        //
    }

    public function send(string $message, ?string $sessionId = null): void
    {
        $this->sentMessages[] = $message;
    }

    public function run(): Response|StreamedResponse
    {
        throw new LogicException('Not implemented.');
    }

    public function sessionId(): ?string
    {
        return uniqid();
    }

    public function stream(Closure $stream): void
    {
        //
    }

    /**
     * Queue a JSON-RPC result to be returned by the next sendRequest() call.
     *
     * @param  array<string, mixed>  $result
     */
    public function expectResponse(array $result): void
    {
        $this->queuedResponses[] = (string) json_encode([
            'jsonrpc' => '2.0',
            'id' => '_placeholder_',
            'result' => $result,
        ]);
    }

    public function sendRequest(string $message): string
    {
        $request = json_decode($message, true);
        $this->sentRequests[] = $request;

        if ($this->queuedResponses === []) {
            throw new LogicException('No responses queued. Call expectResponse() first.');
        }

        $response = json_decode(array_shift($this->queuedResponses), true);
        $response['id'] = $request['id'];

        return (string) json_encode($response);
    }

    public function sendNotification(string $message): void
    {
        $this->sentNotifications[] = $message;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function sentRequests(): array
    {
        return $this->sentRequests;
    }

    /**
     * @return array<int, string>
     */
    public function sentMessages(): array
    {
        return $this->sentMessages;
    }

    /**
     * @return array<int, string>
     */
    public function sentNotifications(): array
    {
        return $this->sentNotifications;
    }
}
