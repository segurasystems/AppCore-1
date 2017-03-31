<?php

namespace Segura\AppCore\Middleware;

use Segura\AppCore\App;
use Slim\Http\Request;
use Slim\Http\Response;

class EnvironmentHeadersOnResponse
{
    protected $apiExplorerEnabled = true;

    public function __invoke(Request $request, Response $response, $next)
    {
        /** @var Response $response */
        $response = $next($request, $response);

        if (stripos($response->getHeader('Content-Type')[0], 'application/json') !== false) {
            $body = $response->getBody();
            $body->rewind();

            $json = json_decode($body->getContents(), true);

            $json['Extra'] = array_filter([
                'Hostname'   => gethostname(),
                'GitVersion' => file_exists(APP_ROOT . "/version.txt") ? trim(file_get_contents(APP_ROOT . "/version.txt")) : null,
                'Time'       => [
                    'Exec'   => number_format(microtime(true) - APP_START, 4) . " sec"
                ],
                'Memory'     => [
                    'Used'       => number_format(memory_get_usage(false)/1024/1024, 2) . "MB",
                    'Allocated'  => number_format(memory_get_usage(true)/1024/1024, 2) . "MB",
                    'Limit'      => ini_get('memory_limit'),
                ],
            ]);

            if (isset($json['Status'])) {
                if (strtolower($json['Status']) != "okay") {
                    $response = $response->withStatus(400);
                } else {
                    $response = $response->withStatus(200);
                }
            }

            if (
                ($request->hasHeader('Content-type') && $request->getHeader('Content-type')[0] == 'application/json')  ||
                ($request->hasHeader('Accept') && $request->getHeader('Accept')[0] == 'application/json')  ||
                $this->apiExplorerEnabled === false
            ) {
                $response = $response->withJson($json, null, JSON_PRETTY_PRINT);
            } else {
                /** @var Twig $twig */
                $twig = App::Container()->get("view");
                $response->getBody()->rewind();
                $response = $twig->render($response, 'api/explorer.html.twig', [
                    'page_name'                => "API Explorer",
                    'json'                     => $json,
                    'json_pretty_printed_rows' => explode("\n", json_encode($json, JSON_PRETTY_PRINT)),
                ]);
                $response = $response->withHeader("Content-type", "text/html");
            }
        }

        return $response;
    }
}
