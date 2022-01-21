<?php

namespace Ezpizee\ContextProcessor\Slim\Middleware;

use Exception;
use Ezpizee\ContextProcessor\Slim\DBOContainer;
use Ezpizee\Utils\Logger;
use Ezpizee\Utils\Request as EzRequest;
use Ezpizee\Utils\RequestEndpointValidator;
use Ezpizee\Utils\StringUtil;
use Ezpizee\Utils\UUID;
use RuntimeException;
use Slim\App;
use Slim\Http\Request;
use Slim\Http\Response;
use Unirest\Request as UnirestClient;

class CORSHandler
{
    public $endPointPath = '';
    private $passCORS = false;

    public function __construct(string $endpointPath)
    {
        $this->endPointPath = $endpointPath;
    }

    public function __invoke(Request $req, Response $res, App $next): Response
    {
        $this->validate($req, $next);

        if ($this->passCORS && is_callable($next)) {
            try {
                $res = $next($req, $res);
            }
            catch (Exception $e) {
                Logger::error($e->getMessage());
                throw new RuntimeException($e->getMessage(), 422);
            }
        }

        return $res;
    }

    public function validate(Request $req, App $app, bool $allow = false): void
    {
        $em = $app->getContainer()->get(DBOContainer::class);
        $referer = $req->getHeaderLine('Referer');
        $origin = strip_tags($req->getHeaderLine('Origin'));
        $request = new EzRequest(['request'=>$req]);
        $method = $req->getMethod();
        $headers = $request->getHeaderKeysAsString();
        $isAjax = $this->isAjaxRequest($req, $request);
        $token = $request->getHeaderParam('X-CSRF-Token', '');
        $requestUniqueId = $request->getUserInfoAsUniqueId();

        if ($isAjax && $origin && $referer &&
            (strpos($referer, $origin) !== false || $referer === $origin) &&
            strpos($origin, $_SERVER['HTTP_HOST']) === false) {
            $uri = strip_tags($req->getUri()->getPath());
            $merchantPublicKey = strip_tags($req->getHeaderLine('merchant_public_key'));
            if (empty($merchantPublicKey)) {
                RequestEndpointValidator::validate($uri, $this->endPointPath, $method==='OPTIONS' ? null : $method);
                $merchantPublicKey = RequestEndpointValidator::getUriParam('public_key');
            }
            if (!empty($merchantPublicKey)) {
                $this->passCOSR($em, $merchantPublicKey, $origin);
            }
        }

        if ($allow || ($this->passCORS && $this->validCSRFToken($em, $token, $requestUniqueId, $method, $origin))) {
            header('Access-Control-Allow-Origin: '.$origin);
            header('Access-Control-Allow-Headers: '.$headers);
            header('Access-Control-Allow-Methods: '.$method);
        }
    }

    public function isAjaxRequest(Request $slimRequest, EzRequest $request): bool {
        $requestHeaders = explode(',', $slimRequest->getHeaderLine('Access-Control-Request-Headers'));
        return $request->isAjax() ||
            $slimRequest->getHeaderLine('X-Requested-With') === 'EzpizeeHttpClient' ||
            in_array('x-requested-with', $requestHeaders);
    }

    public function isPassCORS(): bool {return $this->passCORS;}

    private function passCOSR(DBOContainer $em, string $publicKey, string $origin): void
    {
        if (UUID::isValid($publicKey)) {
            $host = StringUtil::getHost($origin);
            $uri = EZECO_AUTH_HOST.'/api/host/validate/'.$publicKey;
            $resp = UnirestClient::get($uri, [
                'Ref-Host' => $host
            ]);
            if ($resp->code === 200 && $resp->raw_body) {
                $respContent = json_decode($resp->raw_body, true);
                if (is_array($respContent) &&
                    isset($respContent['data']) &&
                    isset($respContent['data'][$publicKey])) {
                    $this->passCORS = $respContent['data'][$publicKey];
                }
            }
        }
    }

    private function validCSRFToken(DBOContainer $em, string $token, string $requestUniqueId, string $method, string $origin): bool
    {
        if ($method === 'OPTIONS') {
            return true;
        }
        if (UUID::isValid($token)) {
            $host = StringUtil::getHost($origin);
            $uri = EZECO_AUTH_HOST.'/api/csrf/validate/'.$token;
            $resp = UnirestClient::get($uri, [
                'Ref-Host' => $host,
                'Request-Unique-Id' => $requestUniqueId
            ]);
            if ($resp->code === 200 && $resp->raw_body) {
                $respContent = json_decode($resp->raw_body, true);
                if (is_array($respContent) &&
                    isset($respContent['data']) &&
                    isset($respContent['data'][$token])) {
                    return true;
                }
            }
        }
        return false;
    }
}