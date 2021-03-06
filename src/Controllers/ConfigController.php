<?php
namespace Gone\AppCore\Controllers;

use Gone\AppCore\Abstracts\Controller;
use Gone\AppCore\App;
use Gone\AppCore\Services\EnvironmentService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Views\Twig;

class ConfigController extends Controller
{
    public function showConfig(Request $request, Response $response, $args)
    {
        /** @var EnvironmentService $environmentService */
        $environmentService = App::Container()->get(EnvironmentService::class);
        $envvars            = $environmentService->__toArray();
        ksort($envvars);

        if ($request->getContentType() == "application/json" || $request->getHeader("Accept")[0] == "application/json") {
            $json = [
                'Status'      => 'Okay',
                'Environment' => $envvars,
            ];
            return $this->jsonResponse($json, $request, $response);
        }

        /** @var Twig $twig */
        $twig = App::Instance()->getContainer()->get("view");

        return $twig->render($response, 'config/environment.html.twig', [
            'page_name'  => "Environment Variables List",
            'envvars'    => $envvars,
        ]);
    }
}
