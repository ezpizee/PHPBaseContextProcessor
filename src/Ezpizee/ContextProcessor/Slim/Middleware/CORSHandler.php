<?php

namespace Ezpizee\ContextProcessor\Slim\Middleware;

use Exception;
use Ezpizee\ContextProcessor\Slim\DBOContainer;
use Ezpizee\Utils\Logger;
use Ezpizee\Utils\Request as EzRequest;
use Ezpizee\Utils\RequestEndpointValidator;
use Ezpizee\Utils\UUID;
use RuntimeException;
use Slim\App;
use Slim\Http\Request;
use Slim\Http\Response;

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

        if ($this->passCORS) {
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

    public function validate(Request $req, App $app): void
    {
        $em = $app->getContainer()->get(DBOContainer::class);
        $headers = '';
        $referer = $req->getHeaderLine('Referer');
        $origin = strip_tags($req->getHeaderLine('Origin'));
        $request = new EzRequest(['request'=>$req]);

        if ($this->isAjaxRequest($req, $request) && $origin && $referer &&
            (strpos($referer, $origin) !== false || $referer === $origin) &&
            strpos($origin, $_SERVER['HTTP_HOST']) === false) {
            $uri = strip_tags($req->getUri()->getPath());
            $headers = $request->getHeaderKeysAsString();
            $merchantPublicKey = strip_tags($req->getHeaderLine('merchant_public_key'));
            if (empty($merchantPublicKey)) {
                RequestEndpointValidator::validate($uri, $this->endPointPath);
                $merchantPublicKey = RequestEndpointValidator::getUriParam('public_key');
            }
            if (!empty($merchantPublicKey)) {
                $this->passCOSR($em, $merchantPublicKey, $origin);
            }
        }

        if ($this->passCORS && $headers) {
            header('Access-Control-Allow-Origin: '.$origin);
            header('Access-Control-Allow-Headers: '.$headers);
            header('Access-Control-Allow-Methods: '.$req->getMethod());
        }
    }

    public function isAjaxRequest(Request $req, EzRequest $request): bool {
        $requestHeaders = explode(',', $req->getHeaderLine('Access-Control-Request-Headers'));
        return $request->isAjax() ||
            $req->getHeaderLine('X-Requested-With') === 'EzpizeeHttpClient' ||
            in_array('x-requested-with', $requestHeaders);
    }

    public function isPassCORS(): bool {return $this->passCORS;}

    private function passCOSR(DBOContainer $em, string $publicKey, string $origin): void
    {
        if (UUID::isValid($publicKey)) {
            $origin = str_replace(['https://','http://','/'], '', $origin);
            $conn = $em->getConnection();
            $sql = 'SELECT host'.' 
                    FROM allowed_hosts 
                    WHERE host_md5='.$conn->quote(md5($origin)).'
                    AND public_key='.$conn->quote($publicKey);
            $row = $conn->loadAssoc($sql);
            if (!empty($row) && $row['host'] === $origin) {
                $this->passCORS = true;
            }
        }
    }
}