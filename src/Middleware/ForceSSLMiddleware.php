<?php

namespace Segura\AppCore\Middleware;

use Slim\Http\Request;
use Slim\Http\Response;

class ForceSSLMiddleware
{
    public function __invoke(Request $request, Response $response, $next)
    {
        /** @var Response $response */
        if ($request->getServerParam('SERVER_PORT') == '80' && !strtolower($request->getServerParam('FORCE_HTTPS')) == 'yes') {
            return $response->withRedirect("https://" . $request->getServerParam('HTTP_HOST') . "/" . ltrim($request->getServerParam('REQUEST_URI'),"/"));
        }
        $response = $next($request, $response);
        return $response;
    }
}
