<?php
namespace Gone\AppCore\Abstracts;

use Gone\AppCore\Controllers\InlineCssTrait;
use Gone\SDK\Common\Exceptions\FilterDecodeException;
use Gone\SDK\Common\Filters\Filter;
use Slim\Http\Request;
use Slim\Http\Response;

abstract class Controller
{
    use InlineCssTrait;

    /** @var Service */
    protected $service;
    /** @var bool */
    protected $apiExplorerEnabled = true;

    public function __construct()
    {
    }

    /**
     * @return Service
     */
    public function getService()
    {
        return $this->service;
    }

    /**
     * @param $service
     *
     * @return Controller
     */
    public function setService($service) : self
    {
        $this->service = $service;
        return $this;
    }

    /**
     * @return bool
     */
    public function isApiExplorerEnabled()  : bool
    {
        return $this->apiExplorerEnabled;
    }

    /**
     * @param bool $apiExplorerEnabled
     *
     * @return Controller
     */
    public function setApiExplorerEnabled(bool $apiExplorerEnabled) : self
    {
        $this->apiExplorerEnabled = $apiExplorerEnabled;
        return $this;
    }

    private function jsonResponse($json, Response $response) : Response
    {
        return $response->withJson($json);
    }

    public function jsonResponseException(\Exception $e, Request $request, Response $response) : Response
    {
        return $this->jsonResponse(
            [
                'Status' => 'Fail',
                'Reason' => $e->getMessage(),
            ],
            $response
        );
    }

    public function jsonFailureResponse($data,Request $request ,Response $response){
        if(is_array($data)){
            $data = array_merge(['Status' => 'Fail'],$data);
        } else {
            $data = [
                'Status' => 'Fail',
                'Reason' => $data,
            ];
        }
        return $this->jsonResponse(
            $data,
            $response
        );
    }

    public function jsonSuccessResponse(array $data,Request $request ,Response $response){
        return $this->jsonResponse(
            array_merge(['Status' => 'Okay'],$data),
            $response
        );
    }

    /**
     * Decide if a request has a json header attached to it.
     *
     * @param Request $request
     * @param string  $header
     *
     * @return bool
     * @throws FilterDecodeException
     */
    protected function requestHasJsonHeader(Request $request, string $header) : bool
    {
        if ($request->hasHeader($header)) {
            $headerText = trim($request->getHeader($header)[0]);
            if (!empty($headerText)) {
                $decode = json_decode($headerText,true);
                if ($decode !== null || $headerText === "null") {
                    return true;
                }
                throw new FilterDecodeException("Could not decode given {$header}. Reason: Not JSON. Given: \"" . $headerText . "\"");
            }
        }
        return false;
    }

    /**
     * @param Request  $request
     *
     * @return Filter
     * @throws FilterDecodeException
     */
    protected function parseFilters(Request $request) : Filter
    {
        if($this->requestHasJsonHeader($request,"Filter")) {
            return Filter::CreateFromJSON($request->getHeader('Filter')[0]);
        }
        return Filter::Factory();
    }
}
