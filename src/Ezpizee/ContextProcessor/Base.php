<?php

namespace Ezpizee\ContextProcessor;

use Ezpizee\Utils\PHPAuth;
use Ezpizee\Utils\Request;
use Ezpizee\Utils\RequestEndpointValidator;
use Ezpizee\Utils\Response;
use Ezpizee\Utils\ResponseCodes;
use Psr\Http\Message\ResponseInterface;

abstract class Base
{
    private static $serviceName = '';

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

    protected $requiredFieldsConfigData = [];

    public function __construct() {}

    abstract protected function allowedMethods(): array;

    abstract protected function requiredAccessToken(): bool;
    abstract protected function isValidAccessToken(): bool;

    abstract protected function validRequiredParams(): bool;
    abstract protected function defaultRequiredParamsValidator(string $configFilePath=''): bool;

    abstract protected function isSystemUserOnly(): bool;
    abstract protected function isSystemUser(string $user, string $pwd): bool;

    abstract protected function processContext(): void;

    abstract protected function subRequest(string $method,
                                           string $path,
                                           string $query = '',
                                           array $headers = [],
                                           array $cookies = [],
                                           string $bodyContent = '',
                                           ResponseInterface $response = null): Response;

    protected final function getUriParam($key): string {return RequestEndpointValidator::getUriParam($key);}

    protected final function displayRequiredFields(): void
    {
        if ($this->request->getRequestParam('display') === 'required-fields') {
            header('Content-Type: application/json');
            die(json_encode($this->requiredFieldsConfigData));
        }
    }

    public final function getContext(): array
    {
        if (in_array($this->request->method(), $this->allowedMethods())) {
            $invalidAccessToken = false;
            if ($this->requiredAccessToken()) {
                $invalidAccessToken = !$this->isValidAccessToken();
            }
            if (!$invalidAccessToken) {
                if ($this->validRequiredParams()) {
                    if ($this->isSystemUserOnly()) {
                        if (!$this->isSystemUser(PHPAuth::getUsername(), PHPAuth::getPassword())) {
                            $this->context['status'] = ResponseCodes::STATUS_ERROR;
                            $this->context['code'] = ResponseCodes::CODE_ERROR_ITEM_NOT_FOUND;
                            $this->context['message'] = ResponseCodes::MESSAGE_ERROR_ITEM_NOT_FOUND;
                            if (defined('DEBUG') && DEBUG) {
                                $this->setContextDebug('This request is for system user only.');
                            }
                        }
                        else {
                            $this->displayRequiredFields();
                            $this->processContext();
                        }
                    }
                    else {
                        $this->displayRequiredFields();
                        $this->processContext();
                    }
                }
                else {
                    $this->context['status'] = ResponseCodes::STATUS_ERROR;
                    $this->context['code'] = ResponseCodes::CODE_ERROR_INVALID_FIELD;
                    $this->context['message'] = ResponseCodes::MESSAGE_ERROR_INVALID_FIELD;
                }
            }
            else {
                $this->context['status'] = ResponseCodes::STATUS_ERROR;
                $this->context['code'] = ResponseCodes::CODE_ERROR_INVALID_TOKEN;
                $this->context['message'] = ResponseCodes::MESSAGE_ERROR_INVALID_TOKEN;
            }
        }
        else {
            $this->context['status'] = ResponseCodes::STATUS_ERROR;
            $this->context['message'] = ResponseCodes::CODE_ERROR_INVALID_METHOD;
            $this->context['message'] = ResponseCodes::MESSAGE_ERROR_INVALID_METHOD;
        }
        return $this->context;
    }

    public final function setServiceName(string $serviceName): void {self::$serviceName = $serviceName;}

    public static final function getServiceName(): string {return self::$serviceName;}

    public final function setRequest(Request $request)
    {
        $this->request = $request;
        $this->requestData = $request->getRequestParamsAsArray();
    }

    public final function setRequestData(array $data): void
    {
        $this->requestData = $data;
    }

    public final function setContext(array $context): void
    {
        $this->context = $context;
    }

    public final function setContextData(array $data): void
    {
        $this->context['data'] = $data;
    }

    public final function setContextStatus(string $status): void
    {
        $this->context['status'] = $status;
    }

    public final function setContextCode(int $code): void
    {
        $this->context['code'] = $code;
    }

    public final function setContextMessage(string $msg): void
    {
        $this->context['message'] = $msg;
    }

    public final function setContextDebug($debug): void
    {
        $this->context['debug'] = $debug;
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
