<?php

namespace Gone\AppCore\Controllers;

use Gone\AppCore\Abstracts\Controller;
use Gone\AppCore\App;
use Gone\AppCore\Router\Router;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Views\Twig;

class ApiListController extends Controller
{
    public function listAllRoutes(Request $request, Response $response, $args)
    {
        if ($request->getContentType() == "application/json" || $request->getHeader("Accept")[0] == "application/json") {
            $json = [];
            $json['Status'] = "Okay";
            $models = [];
            $propertyArray = $this->loadPropertyArray(APP_ROOT . "/src/Routes/SDKData");
            foreach (Router::Instance()->getRoutes() as $route) {
                $routeArray = [
                    'name'     => $route->getName(),
                    'endpoint' => $route->getHttpEndpoint(),
                    'pattern'  => $route->getRouterPattern(),
                    'method'   => $route->getHttpMethod(),
                    'access'   => $route->getAccess(),
                    'example'  => $route->getExampleEntity() ? $route->getExampleEntity()->__toArray() : null,
                ];
                $sdkProperties = $route->getSdkProperties();
                if(empty($sdkProperties)) {
                    $sdkClass = $route->getSDKClass();
                    $sdkFunction = $route->getSDKFunction();
                    $sdkProperties = $propertyArray[$sdkClass][$sdkFunction] ?? [];
                }
                if (!empty($sdkProperties["responseClass"])) {
                    $class = $sdkProperties["responseClass"];
                    $className = $class::NAME_SINGULAR;
                    $models[$className]["table"] = $class::TABLE_NAME;
                    $models[$className]["singular"] = $class::NAME_SINGULAR;
                    $models[$className]["plural"] = $class::NAME_PLURAL;
                    $models[$className]["properties"] = $class::getPublicPropertyMeta();
                    $models[$className]["primaryKeys"] = $class::getPublicPrimaryKeyFields();
                    $sdkProperties["responseClass"] = $className;
                    if (empty($sdkProperties["responseKey"])) {
                        $sdkProperties["responseKey"] = $sdkProperties["returnsArray"] ? $class::NAME_PLURAL : $class::NAME_SINGULAR;
                    }
                }
                $routeArray = array_merge($routeArray, $sdkProperties);

                $json['Routes'][] = array_filter($routeArray);
            }
            $json["Models"] = $models;
            return $this->jsonResponse($json, $request, $response);
        }
        $router = App::Container()->get("router");
        $routes = $router->getRoutes();

        $displayRoutes = [];

        foreach (Router::Instance()->getRoutes() as $route) {
            if (json_decode($route->getName()) !== null) {
                $routeJson = json_decode($route->getName(), true);
                $routeJson['pattern'] = $route->getPattern();
                $routeJson['methods'] = $route->getMethods();
                $displayRoutes[] = $routeJson;
            } else {
                $callable = $route->getCallback();

                if ($callable instanceof \Closure) {
                    $callable = "\Closure";
                }

                if (is_array($callable)) {
                    list($callableClass, $callableFunction) = $callable;
                    if (is_object($callableClass)) {
                        $callableClass = get_class($callableClass);
                    }
                    $callable = "{$callableClass}:{$callableFunction}";
                }

                $displayRoutes[] = [
                    'name'     => $route->getName(),
                    'pattern'  => $route->getRouterPattern(),
                    'methods'  => $route->getHttpMethod(),
                    'callable' => $callable,
                    'access'   => $route->getAccess(),
                ];
            }
        }

        /** @var Twig $twig */
        $twig = App::Instance()->getContainer()->get("view");

        return $twig->render($response, 'api/list.html.twig', [
            'page_name'  => "API Endpoint List",
            'routes'     => $displayRoutes,
            'inline_css' => $this->renderInlineCss([
                __DIR__ . "/../../assets/css/reset.css",
                __DIR__ . "/../../assets/css/api-explorer.css",
                __DIR__ . "/../../assets/css/api-list.css",
            ])
        ]);
    }

    private function loadPropertyArray($dir)
    {
        $props = [];
        if (file_exists($dir)) {
            foreach (new \DirectoryIterator($dir) as $file) {
                $_props = [];
                if (!$file->isDot()) {
                    if ($file->isFile() && $file->getExtension() == 'php') {
                        $_props = include $file->getRealPath() . "";
                    } elseif ($file->isDir()) {
                        $_props = $this->loadPropertyArray($file->getRealPath());
                    }
                }
                foreach ($_props as $class => $funcs){
                    foreach ($funcs as $func => $details){
                        $props[$class][$func] = $details;
                    }
                }
            }
        }
        return $props;
    }
}
