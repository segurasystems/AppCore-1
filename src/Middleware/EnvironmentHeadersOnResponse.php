<?php

namespace Gone\AppCore\Middleware;

use Gone\AppCore\App;
use Gone\AppCore\Container;
use Gone\AppCore\Controllers\InlineCssTrait;
use Gone\AppCore\Zend\Profiler;
use Slim\Http\Request;
use Slim\Http\Response;

class EnvironmentHeadersOnResponse
{
    use InlineCssTrait;

    protected $apiExplorerEnabled = true;

    public function __invoke(Request $request, Response $response, $next)
    {
        /** @var Profiler $profiler */
        $profiler = App::Container()->get(Profiler::class);
        /** @var Response $response */
        $response = $next($request, $response);
        if (isset($response->getHeader('Content-Type')[0])
            and stripos($response->getHeader('Content-Type')[0], 'application/json') !== false
        ) {
            $body = $response->getBody();
            $body->rewind();

            $json = json_decode($body->getContents(), true);

            if (defined('DEBUG_ENABLED') && DEBUG_ENABLED) {

                $gitVersion = null;
                if (file_exists(APP_ROOT . "/version.txt")) {
                    $gitVersion = trim(file_get_contents(APP_ROOT . "/version.txt"));
                    $gitVersion = explode(" ", $gitVersion, 2);
                    $gitVersion = reset($gitVersion);
                }

                $sqlQueries = $profiler->getQueriesArray();
                $sqlQueryTime = 0;
                foreach ($sqlQueries as $query) {
                    $sqlQueryTime += floatval($query["Time"]);
                }

                $sqlQueryData = [
                    "Requests" => $sqlQueries,
                    "Time"     => $sqlQueryTime,
                ];

                $json['Extra'] = array_filter([
                    '_Warning'   => "Do not depend on any variables inside this block - This is for debug only!",
                    'Hostname'   => gethostname(),
                    //'DebugEnabled' => defined('DEBUG_ENABLED') && DEBUG_ENABLED ? 'Yes' : 'No',
                    'GitVersion' => $gitVersion,
                    'Time'       => [
                        'TimeZone'    => date_default_timezone_get(),
                        'CurrentTime' => [
                            'Human' => date("Y-m-d H:i:s"),
                            'Epoch' => time(),
                        ],
                        'Exec'        => number_format(microtime(true) - APP_START, 4) . " sec",
                    ],
                    'Memory'     => [
                        'Used'      => number_format(memory_get_usage(false) / 1024 / 1024, 2) . "MB",
                        'Allocated' => number_format(memory_get_usage(true) / 1024 / 1024, 2) . "MB",
                        'Limit'     => ini_get('memory_limit'),
                    ],
                    'SQL'        => $sqlQueryData,
                    'API'        => class_exists('\Gone\SDK\Common\Profiler')
                        ? \Gone\SDK\Common\Profiler::debugArray()
                        : [
                            "Requests" => [],
                            "Time"     => []
                        ],
                    'DI' => Container::getProfile(),
                ]);
            }

            if (isset($json['Status'])) {
                if (strtolower($json['Status']) != "okay") {
                    $response = $response->withStatus(400);
                } else {
                    $response = $response->withStatus(200);
                }
            }
            $response = $response->withJson($json, null, JSON_PRETTY_PRINT);

//            $twig = App::Container()->get("view");
//            $response->getBody()->rewind();
//            $response = $twig->render($response, 'api/explorer.html.twig', [
//                'page_name'                => "API Explorer",
//                'json'                     => $json,
//                'json_pretty_printed_rows' => explode("\n", json_encode($json, JSON_PRETTY_PRINT)),
//                'inline_css'               => $this->renderInlineCss([
//                    __DIR__ . "/../../assets/css/reset.css",
//                    __DIR__ . "/../../assets/css/api-explorer.css"
//                ])
//            ]);
//            $response = $response->withHeader("Content-type", "text/html");
        }

        return $response;
    }
}
