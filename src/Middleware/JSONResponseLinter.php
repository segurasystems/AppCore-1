<?php

namespace Gone\AppCore\Middleware;

use Slim\Http\Request;
use Slim\Http\Response;

class JSONResponseLinter
{
    public function __invoke(Request $request, Response $response, $next)
    {
        $encodingOptions = 0;
        $jsonCompact = $request->hasHeader('CompactJson');

        if(!$jsonCompact)
            $encodingOptions = JSON_PRETTY_PRINT;

        try {
            $response = $next($request, $response);
            $jsonMode = (isset($response->getHeader('Content-Type')[0]) and stripos($response->getHeader('Content-Type')[0], 'application/json') !== false);
            if ($jsonMode) {
                $body = $response->getBody();
                $body->rewind();
                $json = json_decode($body->getContents(), true);
                if ($jsonCompact){
                    if(isset($json['Extra'])) {
                        unset($json['Extra']);
                    }
                }
                if (isset($json['Status'])) {
                    $json['Status'] = ucfirst(strtolower($json['Status']));
                }
                $response = $response->withJson($json, null, $encodingOptions);
            }
            return $response;
        } catch (\Exception $exception) {
            $trace = array_map(function($a){return "{$a["file"]}: {$a["line"]}";},$exception->getTrace());
            array_walk($trace, function (&$elem) {
                $highlightLocations = [
                    '/app/src/',
                    '/app/tests/'
                ];
                foreach ($highlightLocations as $highlightLocation) {
                    if (strpos($elem, $highlightLocation) == 0) {
                        $elem = "*** {$elem}";
                    }
                }
            });
            $response = $response->withJson(
                [
                    'Status' => 'Fail',
                    'Exception' => get_class($exception),
                    'Reason' => $exception->getMessage(),
                    'Trace' => $trace,
                ],
                500,
                $encodingOptions
            );
            return $response;
        }
    }
}
