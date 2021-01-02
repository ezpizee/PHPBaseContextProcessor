<?php

namespace Ezpizee\ContextProcessor;

use Ezpizee\Utils\Request;
use Ezpizee\Utils\RequestEndpointValidator;

abstract class Base
{
    protected $context = [
        'status' => 'OK',
        'message' => 'SUCCESS',
        'code' => 200,
        'data' => null,
        'debug' => null
    ];

    /**
     * @var Request
     */
    protected $request;

    protected $requestData = [];

    public function __construct()
    {
    }

    abstract public function requiredAccessToken(): bool;

    abstract public function methods(): array;

    abstract public function exec(): void;

    abstract public function validRequiredParams(): bool;

    abstract public function isSystemUser(): bool;

    public function setRequest(Request $request)
    {
        $this->request = $request;
        $this->requestData = $request->getRequestParamsAsArray();
    }

    public function setRequestData(array $data): void
    {
        $this->requestData = $data;
    }

    protected final function getUriParam($key): string
    {
        return RequestEndpointValidator::getUriParam($key);
    }

    public final function setContextData(array $data): void
    {
        $this->context['data'] = $data;
    }

    public final function setContextCode(int $code): void
    {
        $this->context['code'] = $code;
    }

    public final function setContextMessage(string $msg): void
    {
        $this->context['message'] = $msg;
    }

    public final function setContextDebug(array $debug): void
    {
        $this->context['debug'] = $debug;
    }

    public final function getContext(): array
    {
        return $this->context;
    }

    public final function getContextCode(): int
    {
        return is_string($this->context['code']) ? (int)$this->context['code'] : $this->context['code'];
    }

    public final function getContextMessage(): string
    {
        return $this->context['message'];
    }

    public final function getContextData(): array
    {
        return empty($this->context['data']) ? [] : $this->context['data'];
    }

    public final function getContextDebug(): array
    {
        return empty($this->context['debug']) ? [] : $this->context['debug'];
    }
}
