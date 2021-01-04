<?php

namespace Ezpizee\ContextProcessor;

use Ezpizee\Utils\ListModel;
use Ezpizee\Utils\Logger;
use Ezpizee\Utils\PHPAuth;
use Ezpizee\Utils\Request;
use Ezpizee\Utils\RequestBodyValidator;
use Ezpizee\Utils\RequestEndpointValidator;
use Ezpizee\Utils\Response;
use Ezpizee\Utils\ResponseCodes;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

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

    protected $isAllRequiredFieldsValid = false;

    public function __construct() {}

    abstract protected function allowedMethods(): array;

    abstract protected function requiredAccessToken(): bool;
    abstract protected function isValidAccessToken(): bool;

    abstract protected function validRequiredParams(): bool;

    abstract protected function isSystemUserOnly(): bool;
    abstract protected function isSystemUser(string $user, string $pwd): bool;

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
        if ($this->getRequestData('display') === 'required-fields') {
            header('Content-Type: application/json');
            die(json_encode($this->requiredFieldsConfigData));
        }
    }

    /**
     * Allow child class to invoke default fields validator
     * required fields config format:
     * [
     *     {"name": "field_name", "type": "string|number", "size": 0|###, "defaultValue": ["element",...]},
     *     ...
     * ]
     *
     * @param string $configFilePath
     *
     * @return bool
     */
    protected final function defaultRequiredParamsValidator(string $configFilePath=''): bool
    {
        if (!$configFilePath) {
            $configFilePath = CustomLoader::getDir(get_called_class()) . DS . 'required-fields.json';
            die($configFilePath);
        }
        if (!file_exists($configFilePath)) {
            $configFilePath = str_replace(DS.'Update'.DS, DS.'Add'.DS, $configFilePath);
            if (!file_exists($configFilePath)) {
                throw new RuntimeException(
                    self::class.'.defaultRequiredParamsValidator: required fields config file missing '.$configFilePath,
                    ResponseCodes::CODE_ERROR_INTERNAL_SERVER
                );
            }
        }
        $this->requiredFieldsConfigData = json_decode(file_get_contents($configFilePath), true);

        $this->displayRequiredFields();

        if (!empty($this->requiredFieldsConfigData)) {
            foreach ($this->requiredFieldsConfigData as $field) {
                if (isset($field['name']) && isset($field['type']) && isset($field['size']) && isset($field['defaultValue'])) {
                    $field['type'] = strtolower($field['type']);
                    if (!$this->hasRequestData($field['name'])) {
                        RequestBodyValidator::validateFile(new ListModel($field));
                    }
                    else {
                        $v = $this->getRequestData($field['name']);
                        RequestBodyValidator::validate(new ListModel($field), $v);
                    }
                }
                else {
                    RequestBodyValidator::throwError(new ListModel($field));
                }
            }
        }

        $this->isAllRequiredFieldsValid = true;
        return $this->isAllRequiredFieldsValid;
    }

    /**
     * @param null   $arg
     * @param string $key
     *
     * @return array
     */
    protected final function getFieldFromRequiredFields($arg=null, $key='name'): array
    {
        $data1 = [];
        $data2 = [];
        if ($arg !== null && $arg) {
            if (is_string($arg) && file_exists($arg)) {
                $data1 = json_decode(file_get_contents($arg), true);
            }
            else if (is_array($arg)) {
                $data1 = $arg;
            }
            else if (is_object($arg)) {
                $data1 = json_decode(json_encode($arg), true);
            }
            if (!empty($data1)) {
                foreach ($data1 as $field) {
                    if (isset($field[$key])) {
                        $data2[] = $field[$key];
                    }
                }
            }
        }
        else if (!empty($this->requiredFieldsConfigData)) {
            foreach ($this->requiredFieldsConfigData as $field) {
                if (isset($field[$key])) {
                    $data2[] = $field[$key];
                }
            }
        }
        return $data2;
    }

    abstract public function processContext(): void;

    public final function getContext(): array
    {
        $method = !empty($this->request) ? $this->request->method() : strtoupper($_SERVER['REQUEST_METHOD']);

        if (in_array($method, $this->allowedMethods())) {
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
                            $this->processContext();
                        }
                    }
                    else {
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

    private final function getRequestData(string $key) {
        if (!empty($this->requestData) && isset($this->requestData[$key])) {
            return $this->requestData[$key];
        }
        else if (!empty($this->request)) {
            return $this->request->getRequestParam($key);
        }
        else if (isset($_GET[$key])) {
            return $_GET[$key];
        }
        return null;
    }

    private final function hasRequestData(string $key): bool {
        if (!empty($this->requestData) && isset($this->requestData[$key])) {
            return true;
        }
        else if (!empty($this->request)) {
            return $this->request->hasRequestParam($key);
        }
        else if (isset($_GET[$key])) {
            return true;
        }
        return false;
    }

    public final function logger(string $msg, string $type='error')
    {
        Logger::{$type}($msg);
    }

    public final function testDisplay($val, bool $isJSON=false): void
    {
        Logger::testDisplay($val, $isJSON);
    }
}
