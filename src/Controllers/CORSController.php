<?php
namespace Gone\AppCore\Controllers;

use Gone\AppCore\Abstracts\Controller;
use Slim\Http\Request;
use Slim\Http\Response;

class CORSController extends Controller
{
    public function optionsRequest(Request $request, Response $response)
    {
        return $this->jsonResponse([
            'Status' => 'Okay',
            'Message' => 'CORS IS YES',
        ], $request, $response);
    }
}
