<?php

declare(strict_types=1);

namespace Laravel\Mcp;

use Illuminate\Container\Container;
use Illuminate\Support\Str;
use Laravel\Mcp\Enums\ProtocolVersion;
use Laravel\Mcp\Events\SessionInitialized;
use Laravel\Mcp\Exceptions\JsonRpcException;
use Laravel\Mcp\Server\AppResource;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\ClientRequest;
use Laravel\Mcp\Server\Concerns\ReadsAttributes;
use Laravel\Mcp\Server\Contracts\Method;
use Laravel\Mcp\Server\Contracts\Transport;
use Laravel\Mcp\Server\Elicitation\Elicitation;
use Laravel\Mcp\Server\Methods\CallTool;
use Laravel\Mcp\Server\Methods\CompletionComplete;
use Laravel\Mcp\Server\Methods\GetPrompt;
use Laravel\Mcp\Server\Methods\Initialize;
use Laravel\Mcp\Server\Methods\ListPrompts;
use Laravel\Mcp\Server\Methods\ListResources;
use Laravel\Mcp\Server\Methods\ListResourceTemplates;
use Laravel\Mcp\Server\Methods\ListTools;
use Laravel\Mcp\Server\Methods\Ping;
use Laravel\Mcp\Server\Methods\ReadResource;
use Laravel\Mcp\Server\Notifications\ProgressNotification;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Server\Sampling\Sampling;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Server\ServerNotification;
use Laravel\Mcp\Server\Testing\PendingTestResponse;
use Laravel\Mcp\Server\Testing\TestResponse;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Transport\HttpTransport;
use Laravel\Mcp\Transport\JsonRpcNotification;
use Laravel\Mcp\Transport\JsonRpcRequest;
use Laravel\Mcp\Transport\JsonRpcResponse;
use stdClass;
use Throwable;

/**
 * @mixin PendingTestResponse
 */
abstract class Server
{
    use ReadsAttributes;

    public const CAPABILITY_TOOLS = 'tools';

    public const CAPABILITY_RESOURCES = 'resources';

    public const CAPABILITY_PROMPTS = 'prompts';

    public const CAPABILITY_COMPLETIONS = 'completions';

    public const CAPABILITY_SAMPLING = 'sampling';

    public const CAPABILITY_ELICITATION = 'elicitation';

    public const CAPABILITY_UI = 'io.modelcontextprotocol/ui';

    protected string $name = 'Laravel MCP Server';

    protected string $version = '0.0.1';

    protected string $instructions = <<<'MARKDOWN'
        This MCP server lets AI agents interact with our Laravel application.
    MARKDOWN;

    /**
     * @var array<int, string>
     */
    protected array $supportedProtocolVersion = [];

    /**
     * @var array<string, array<string, bool>|stdClass|string>
     */
    protected array $capabilities = [
        self::CAPABILITY_TOOLS => [
            'listChanged' => false,
        ],
        self::CAPABILITY_RESOURCES => [
            'listChanged' => false,
        ],
        self::CAPABILITY_PROMPTS => [
            'listChanged' => false,
        ],
    ];

    /**
     * @var array<int, Tool|class-string<Tool>>
     */
    protected array $tools = [];

    /**
     * @var array<int, Resource|class-string<Resource>>
     */
    protected array $resources = [];

    /**
     * @var array<int, Prompt|class-string<Prompt>>
     */
    protected array $prompts = [];

    /**
     * Capabilities declared by the connected client during initialization.
     *
     * @var array<string, mixed>
     */
    protected array $clientCapabilities = [];

    /**
     * Protocol version negotiated with the connected client.
     */
    protected ?string $protocolVersion = null;

    public int $maxPaginationLength = 50;

    public int $defaultPaginationLength = 15;

    /**
     * @var array<string, class-string<Method>>
     */
    protected array $methods = [
        'tools/list' => ListTools::class,
        'tools/call' => CallTool::class,
        'resources/list' => ListResources::class,
        'resources/read' => ReadResource::class,
        'resources/templates/list' => ListResourceTemplates::class,
        'prompts/list' => ListPrompts::class,
        'prompts/get' => GetPrompt::class,
        'completion/complete' => CompletionComplete::class,
        'ping' => Ping::class,
    ];

    public function __construct(
        protected Transport $transport,
    ) {
        //
    }

    /**
     * Add or modify a server capability.
     *
     * Using dot notation like "feature.enabled" will create a nested capability array.
     * Passing a single key like "anotherFeature" will register an empty object capability.
     */
    public function addCapability(string $key, bool $value = true): void
    {
        if (str_contains($key, '.')) {
            [$root, $child] = explode('.', $key, 2);
            $existing = $this->capabilities[$root] ?? [];

            if (! is_array($existing)) {
                $existing = [];
            }

            $existing[$child] = $value;
            $this->capabilities[$root] = $existing;

            return;
        }

        // Represent empty capability as an object when JSON encoded
        $this->capabilities[$key] = (object) [];
    }

    /**
     * Register a custom JSON-RPC method handler.
     *
     * @param  class-string<Method>  $handler
     */
    public function addMethod(string $method, string $handler): void
    {
        $this->methods[$method] = $handler;
    }

    public function start(): void
    {
        $this->boot();
        $this->detectUiCapability();

        $this->transport->onReceive($this->handle(...));
    }

    protected function boot(): void
    {
        //
    }

    public function handle(string $rawMessage): void
    {
        $context = $this->createContext();

        try {
            $jsonRequest = json_decode($rawMessage, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new JsonRpcException('Parse error: Invalid JSON was received by the server.', -32700);
            }

            // Route client responses to a server-initiated request into the cache so a
            // blocked HttpTransport::sendRequest() poll on another process can pick them up.
            // Only HTTP needs this: stdio's sendRequest() reads the reply from STDIN directly.
            if ($this->transport instanceof HttpTransport && is_array($jsonRequest) && $this->isJsonRpcResponse($jsonRequest)) {
                $sessionId = $this->transport->sessionId();
                $cacheKey = "mcp:response:{$sessionId}:{$jsonRequest['id']}";
                Container::getInstance()->make('cache')->put($cacheKey, $rawMessage, 120);

                return;
            }

            $request = isset($jsonRequest['id'])
                ? JsonRpcRequest::from($jsonRequest, $this->transport->sessionId())
                : JsonRpcNotification::from($jsonRequest);

            if ($request instanceof JsonRpcNotification) {
                return;
            }

            if ($request->method === 'initialize') {
                $this->handleInitializeMessage($request, $context);

                return;
            }

            if (! isset($this->methods[$request->method])) {
                throw new JsonRpcException(
                    "The method [{$request->method}] was not found.",
                    -32601,
                    $request->id,
                );
            }

            $this->handleMessage($request, $context);
        } catch (JsonRpcException $e) {
            $this->transport->send($e->toJsonRpcResponse()->toJson());
        } catch (Throwable $e) {
            report($e);

            $config = Container::getInstance()->make('config');

            if ($config->get('app.debug', false)) {
                throw $e;
            }

            $jsonRpcResponse = JsonRpcResponse::error(
                $request->id ?? null,
                -32603,
                'Something went wrong while processing the request.',
            );

            $this->transport->send($jsonRpcResponse->toJson());
        }
    }

    public function createContext(): ServerContext
    {
        $name = $this->resolveAttribute(Name::class);
        $version = $this->resolveAttribute(Version::class);
        $instructions = $this->resolveAttribute(Instructions::class);

        return new ServerContext(
            supportedProtocolVersions: $this->supportedProtocolVersion ?: ProtocolVersion::supported(),
            serverCapabilities: $this->capabilities,
            serverName: $name !== null ? $name->value : $this->name,
            serverVersion: $version !== null ? $version->value : $this->version,
            instructions: $instructions !== null ? $instructions->value : $this->instructions,
            maxPaginationLength: $this->maxPaginationLength,
            defaultPaginationLength: $this->defaultPaginationLength,
            tools: $this->tools,
            resources: $this->resources,
            prompts: $this->prompts,
        );
    }

    /**
     * @throws JsonRpcException
     */
    protected function handleMessage(JsonRpcRequest $request, ServerContext $context): void
    {
        $response = $this->runMethodHandle($request, $context);

        if (! is_iterable($response)) {
            $this->transport->send($response->toJson());

            return;
        }

        $this->transport->stream(function () use ($response): void {
            foreach ($response as $message) {
                $this->transport->send($message->toJson());
            }
        });
    }

    /**
     * @return iterable<JsonRpcResponse>|JsonRpcResponse
     *
     * @throws JsonRpcException
     */
    protected function runMethodHandle(JsonRpcRequest $request, ServerContext $context): iterable|JsonRpcResponse
    {
        $container = Container::getInstance();

        /** @var Method $methodClass */
        $methodClass = $container->make(
            $this->methods[$request->method],
        );

        $container->instance('mcp.request', $request->toRequest());

        $sampling = new Sampling(
            $this->transport,
            $this->resolveClientCapabilities(),
            $this->resolveProtocolVersion($context),
        );
        $container->instance(Sampling::class, $sampling);

        $elicitation = new Elicitation(
            $this->transport,
            $this->resolveClientCapabilities(),
            $this->resolveProtocolVersion($context),
        );
        $container->instance(Elicitation::class, $elicitation);

        $container->instance(ProgressNotification::class, new ProgressNotification($this->transport));

        try {
            $response = $methodClass->handle($request, $context);
        } finally {
            $container->forgetInstance('mcp.request');
            $container->forgetInstance(Sampling::class);
            $container->forgetInstance(Elicitation::class);
            $container->forgetInstance(ProgressNotification::class);
        }

        return $response;
    }

    protected function handleInitializeMessage(JsonRpcRequest $request, ServerContext $context): void
    {
        $response = (new Initialize)->handle($request, $context);

        $sessionId = $this->generateSessionId();

        $this->clientCapabilities = $request->params['capabilities'] ?? [];
        $this->protocolVersion = $response->toArray()['result']['protocolVersion'] ?? null;

        if ($this->transport instanceof HttpTransport && $this->clientCapabilities !== []) {
            $this->storeHttpSessionState($sessionId);
        }

        Container::getInstance()->make('events')->dispatch(new SessionInitialized(
            sessionId: $sessionId,
            clientInfo: $request->params['clientInfo'] ?? null,
            protocolVersion: $request->params['protocolVersion'] ?? null,
            clientCapabilities: $request->params['capabilities'] ?? null,
        ));

        $this->transport->send($response->toJson(), $sessionId);
    }

    /**
     * Determine whether a decoded JSON-RPC message is a response (result or error)
     * rather than a request or notification.
     *
     * @param  array<string, mixed>  $message
     */
    protected function isJsonRpcResponse(array $message): bool
    {
        return isset($message['id'])
            && array_key_exists('method', $message) === false
            && (array_key_exists('result', $message) || array_key_exists('error', $message));
    }

    /**
     * Create a server-initiated request bound to the connected client.
     *
     * Wires the transport together with the capabilities and protocol version
     * negotiated during initialization, so the request can gate itself with
     * {@see ClientRequest::ensureClientCapability()} and
     * {@see ClientRequest::ensureProtocolSupports()}.
     *
     * @template TRequest of ClientRequest
     *
     * @param  class-string<TRequest>  $class
     * @return TRequest
     *
     * @throws JsonRpcException
     */
    protected function clientRequest(string $class): ClientRequest
    {
        return new $class(
            $this->transport,
            $this->resolveClientCapabilities(),
            $this->resolveProtocolVersion($this->createContext()),
        );
    }

    /**
     * Create a server-initiated notification bound to the connected client.
     *
     * @template TNotification of ServerNotification
     *
     * @param  class-string<TNotification>  $class
     * @return TNotification
     */
    protected function serverNotification(string $class): ServerNotification
    {
        return new $class($this->transport);
    }

    /**
     * Resolve the connected client's capabilities, falling back to the HTTP session cache
     * for stateless requests that occur after initialization.
     *
     * @return array<string, mixed>
     */
    protected function resolveClientCapabilities(): array
    {
        $sessionId = $this->transport->sessionId();

        if ($this->transport instanceof HttpTransport && $sessionId !== null) {
            try {
                $capabilities = Container::getInstance()->make('cache')->get($this->clientCapabilitiesCacheKey($sessionId));
            } catch (Throwable) {
                return $this->clientCapabilities;
            }

            if (is_array($capabilities)) {
                return $capabilities;
            }
        }

        return $this->clientCapabilities;
    }

    /**
     * Resolve the protocol version negotiated with the connected client.
     *
     * @throws JsonRpcException
     */
    protected function resolveProtocolVersion(ServerContext $context): string
    {
        if ($this->transport instanceof HttpTransport) {
            $protocolVersion = $this->transport->protocolVersion();

            if ($protocolVersion !== null) {
                if (! in_array($protocolVersion, $context->supportedProtocolVersions, true)) {
                    throw new JsonRpcException(
                        message: 'Unsupported protocol version',
                        code: -32602,
                        data: [
                            'supported' => $context->supportedProtocolVersions,
                            'requested' => $protocolVersion,
                        ],
                    );
                }

                return $protocolVersion;
            }

            $sessionId = $this->transport->sessionId();

            if ($sessionId !== null && $sessionId !== '') {
                try {
                    $protocolVersion = Container::getInstance()->make('cache')->get($this->protocolVersionCacheKey($sessionId));
                } catch (Throwable) {
                    $protocolVersion = null;
                }

                if (is_string($protocolVersion) && in_array($protocolVersion, $context->supportedProtocolVersions, true)) {
                    return $protocolVersion;
                }
            }

            return ProtocolVersion::V2025_03_26->value;
        }

        return $this->protocolVersion ?? $context->supportedProtocolVersions[0];
    }

    /**
     * Persist the negotiated client capabilities and protocol version so subsequent
     * stateless HTTP requests can resolve them.
     */
    protected function storeHttpSessionState(string $sessionId): void
    {
        $cache = Container::getInstance()->make('cache');
        $ttl = $this->httpSessionTtl();

        if ($this->clientCapabilities !== []) {
            $cache->put($this->clientCapabilitiesCacheKey($sessionId), $this->clientCapabilities, $ttl);
        }

        if ($this->protocolVersion !== null) {
            $cache->put($this->protocolVersionCacheKey($sessionId), $this->protocolVersion, $ttl);
        }
    }

    protected function httpSessionTtl(): int
    {
        $ttl = Container::getInstance()->make('config')->get('mcp.http_session_ttl', 3600);

        if (! is_int($ttl)) {
            return 3600;
        }

        return max($ttl, 121);
    }

    protected function clientCapabilitiesCacheKey(string $sessionId): string
    {
        return "mcp:session:{$sessionId}:clientCapabilities";
    }

    protected function protocolVersionCacheKey(string $sessionId): string
    {
        return "mcp:session:{$sessionId}:protocolVersion";
    }

    protected function generateSessionId(): string
    {
        return Str::uuid()->toString();
    }

    protected function detectUiCapability(): void
    {
        if (array_key_exists(self::CAPABILITY_UI, $this->capabilities)) {
            return;
        }

        foreach ($this->resources as $resource) {
            if (is_subclass_of($resource, AppResource::class)) {
                $this->addCapability(self::CAPABILITY_UI);

                return;
            }
        }
    }

    /**
     * @param  array<array-key, mixed>  $arguments
     */
    public static function __callStatic(string $name, array $arguments): PendingTestResponse|TestResponse
    {
        $pendingTestResponse = new PendingTestResponse(
            Container::getInstance(),
            static::class,
        );

        return $pendingTestResponse->$name(...$arguments);
    }
}
