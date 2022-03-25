<?php

namespace Ezpizee\ContextProcessor;

use Ezpizee\ContextProcessor\Slim\DBOContainer;
use Ezpizee\Utils\ListModel;
use Ezpizee\Utils\Logger;
use Ezpizee\Utils\PHPAuth;
use Ezpizee\Utils\Request;
use Ezpizee\Utils\RequestBodyValidator;
use Ezpizee\Utils\RequestEndpointValidator;
use Ezpizee\Utils\Response;
use Ezpizee\Utils\ResponseCodes;
use RuntimeException;

abstract class Base
{
    /** @var DBO $connection */
    protected DBO $connection;
    /** @var DBO $systemConnection */
    protected DBO $systemConnection;
    /** @var array $dataForMessagingService */
    protected array $dataForMessagingService = [];
    /** @var string $serviceName */
    private static string $serviceName = '';
    /** @var array $context */
    protected array $context = [
        'status'  => 'OK',
        'message' => 'SUCCESS',
        'code'    => 200,
        'data'    => null,
        'debug'   => null
    ];
    /** @var Request $request */
    protected Request $request;
    /** @var array $requestData */
    protected array $requestData = [];
    /** @var array $requiredFieldsConfigData */
    protected array $requiredFieldsConfigData = [];
    /** @var bool $isAllRequiredFieldsValid */
    protected bool $isAllRequiredFieldsValid = false;
    /** @var int $timestampNow */
    protected int $timestampNow = 0;
    /** @var bool $hasFormSubmission */
    protected bool $hasFormSubmission = false;
    /** constructor */
    public function __construct() {}

    /**
     * @param DBOContainer $em
     */
    protected final function setEntityManager(DBOContainer $em)
    : void
    {
        $this->timestampNow = strtotime('now');
        if ($em->isConnected()) {
            $this->systemConnection = $em->getConnection();
        }
        $this->connection = $this->systemConnection;
        $this->hasFormSubmission = ($this->request->method()==='POST' || $this->request->method()==='PUT') && !empty($this->request->getRequestParamsAsArray());
    }

    /**
     * Expose db connection
     *
     * @return DBO
     */
    public function getConnection(): DBO {return $this->connection;}

    /**
     * Expose the connection to the ezpz_user database
     *
     * @return DBO
     */
    public function getSystemConnection(): DBO {return $this->systemConnection;}

    public static final function getServiceName(): string{return self::$serviceName;}

    public final function getContext()
    : array
    {
        $this->preProcessContext();

        $method = !empty($this->request) ? $this->request->method() : strtoupper($_SERVER['REQUEST_METHOD']);

        if (in_array($method, $this->allowedMethods())) {

            // 1. check if required fields are valid
            if ($this->validRequiredParams()) {

                $invalidAccessToken = false;

                // 2. check if access token is required
                if ($this->requiredAccessToken()) {
                    $invalidAccessToken = !$this->isValidAccessToken();
                }

                // 3. if access token is not required or if required & valid toke was provided
                if (!$invalidAccessToken) {

                    // 4. if system user is required
                    if ($this->isSystemUserOnly()) {

                        // 5. if system user is required & invalid
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
                    $this->context['code'] = ResponseCodes::CODE_ERROR_INVALID_TOKEN;
                    $this->context['message'] = ResponseCodes::MESSAGE_ERROR_INVALID_TOKEN;
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
            $this->context['message'] = ResponseCodes::CODE_ERROR_INVALID_METHOD;
            $this->context['message'] = ResponseCodes::MESSAGE_ERROR_INVALID_METHOD;
        }

        $this->postProcessContext();

        return $this->context;
    }

    public final function setContext(array $context): void{$this->context = $context;}

    abstract protected function allowedMethods()
    : array;

    abstract protected function requiredAccessToken()
    : bool;

    abstract protected function isValidAccessToken()
    : bool;

    abstract protected function validRequiredParams()
    : bool;

    abstract protected function isSystemUserOnly()
    : bool;

    abstract protected function isSystemUser(string $user, string $pwd)
    : bool;

    public final function setContextDebug($debug): void {$this->context['debug'] = $debug;}

    protected function preProcessContext(): void {}

    abstract public function processContext(): void;

    protected function postProcessContext(): void {}

    public final function setServiceName(string $serviceName): void{self::$serviceName = $serviceName;}

    public final function setRequest(Request $request)
    {
        $this->request = $request;
        $this->requestData = $request->getRequestParamsAsArray();
    }

    public final function setRequestData(array $data): void{$this->requestData = $data;}

    public final function setContextData(array $data): void{$this->context['data'] = $data;}

    public final function setContextStatus(string $status): void{$this->context['status'] = $status;}

    public final function setContextCode(int $code): void{$this->context['code'] = $code;}

    public final function setContextMessage(string $msg): void{$this->context['message'] = $msg;}

    public final function getContextCode()
    : int
    {
        return is_string($this->context['code']) ? (int)$this->context['code'] : $this->context['code'];
    }

    public final function getContextMessage(): string{return $this->context['message'];}

    public final function getContextData()
    : array
    {
        return empty($this->context['data']) ? [] : $this->context['data'];
    }

    public final function getContextDebug()
    : array
    {
        return empty($this->context['debug']) ? [] : $this->context['debug'];
    }

    public final function logger($msg, string $type = 'error')
    {
        if (is_array($msg) || is_object($msg)) {
            $msg = json_encode($msg);
        }
        else if (!is_string($msg)) {
            $msg = "" . $msg;
        }
        Logger::{$type}($msg);
    }

    public final function testDisplay($val, bool $isJSON = false): void{Logger::testDisplay($val, $isJSON);}

    protected function subRequest(
        string $method,
        string $path,
        string $query = '',
        array $headers = array(),
        array $cookies = array(),
        string $bodyContent = '',
        $response = null
    )
    : Response
    {
        $context = $this->context;
        $context['message'] = 'Should be implemented by sub-class';
        $context['code'] = 500;
        $context['data'] = [$query, $headers, $cookies, $bodyContent, $response];
        return new Response($method, $path, json_encode($context));
    }

    protected final function getUriParam($key): string{return RequestEndpointValidator::getUriParam($key);}

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
    protected final function defaultRequiredParamsValidator(string $configFilePath = '')
    : bool
    {
        if (!$configFilePath) {
            $configFilePath = CustomLoader::getDir(get_called_class()) . EZPIZEE_DS. 'required-fields.json';
        }
        if (!file_exists($configFilePath)) {
            $configFilePath = str_replace(EZPIZEE_DS .'Update' . EZPIZEE_DS, EZPIZEE_DS . 'Add' . EZPIZEE_DS, $configFilePath);
            if (!file_exists($configFilePath)) {
                throw new RuntimeException(
                    self::class . '.defaultRequiredParamsValidator: required fields config file missing ' . $configFilePath,
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

    protected final function displayRequiredFields()
    : void
    {
        if ($this->getRequestData('display') === 'required-fields') {
            header('Content-Type: application/json');
            die(json_encode($this->requiredFieldsConfigData));
        }
    }

    private function getRequestData(string $key)
    {
        if (!empty($this->request)) {
            return $this->request->getRequestParam($key);
        }
        else if (!empty($this->requestData) && isset($this->requestData[$key])) {
            return $this->requestData[$key];
        }
        else if (isset($_GET[$key])) {
            return $_GET[$key];
        }
        else if (isset($_POST[$key])) {
            return $_POST[$key];
        }
        return null;
    }

    private function hasRequestData(string $key)
    : bool
    {
        if (!empty($this->request)) {
            return $this->request->hasRequestParam($key);
        }
        else if (!empty($this->requestData) && isset($this->requestData[$key])) {
            return true;
        }
        else if (isset($_GET[$key]) || isset($_POST[$key])) {
            return true;
        }
        return false;
    }

    /**
     * @param null $arg
     * @param string $key
     *
     * @return array
     */
    protected final function getFieldFromRequiredFields($arg = null, $key = 'name')
    : array
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

    public final function closeDBConnection()
    : void
    {
        if ($this->connection instanceof DBO && $this->connection->isConnected()) {
            $this->connection->closeConnection();
        }
    }

    protected final function triggerMessagingServiceProducer()
    : void
    {
        if (defined('FIREBASE_INTEGRATED') && FIREBASE_INTEGRATED &&
            method_exists($this, 'sendFirebaseTopic')) {
            $this->sendFirebaseTopic();
        }
    }

    protected function sendFirebaseTopic(): void {}
}
