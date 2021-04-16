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

    public function __construct(string $endpointPath)
    {
        $this->endPointPath = $endpointPath;
    }

    public function __invoke(Request $req, Response $res, App $next): Response
    {
        $passCORS = false;
        $em = $em = $next->getContainer()->get(DBOContainer::class);
        $referer = $req->getHeaderLine('Referer');
        $requestHeaders = explode(',', $req->getHeaderLine('Access-Control-Request-Headers'));
        $origin = strip_tags($req->getHeaderLine('Origin'));
        $method = 'GET,POST,DELETE,PUT,PATCH,OPTIONS';
        $headers = '';
        $request = new EzRequest(['request'=>$req]);
        $isAjax = $request->isAjax() ||
            $req->getHeaderLine('X-Requested-With') === 'EzpizeeHttpClient' ||
            in_array('x-requested-with', $requestHeaders);

        if ($isAjax && $origin && $referer &&
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
                $passCORS = $this->passCOSR($em, $merchantPublicKey, $origin);
            }
        }

        if ($passCORS && $res instanceof Response && is_callable($next)) {
            try {
                $res = $next($req, $res);
            }
            catch (Exception $e) {
                Logger::error($e->getMessage());
                throw new RuntimeException($e->getMessage(), 422);
            }
            /*
            $res->withHeader('Access-Control-Allow-Origin', $origin)
                ->withHeader('Access-Control-Allow-Headers', $headers)
                ->withHeader('Access-Control-Allow-Methods', $method);
            */
            header('Access-Control-Allow-Origin: '.$origin);
            header('Access-Control-Allow-Headers: '.$headers);
            header('Access-Control-Allow-Methods: '.$method);
        }

        return $res;
    }

    public function passCOSR(DBOContainer $em, string $merchantPublicKey, string $origin): bool
    {
        $passCORS = false;
        if (UUID::isValid($merchantPublicKey)) {
            $conn = $em->getConnection();
            $sql = 'SELECT user_id'.' 
                    FROM allowed_hosts 
                    WHERE host_md5='.$conn->quote(md5(str_replace(['https://','http://','/'], '', $origin))).'
                    AND public_key='.$conn->quote($merchantPublicKey);
            $row = $conn->loadAssoc($sql);
            if (!empty($row)) {
                $passCORS = true;
            }
        }
        return $passCORS;
    }
}