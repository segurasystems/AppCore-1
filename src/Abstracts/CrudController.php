<?php

namespace Gone\AppCore\Abstracts;

use Gone\AppCore\Validator\AbstractValidator;
use Slim\Http\Request;
use Slim\Http\Response;
use Zend\Db\Adapter\Exception\InvalidQueryException;
use Exception;

abstract class CrudController extends Controller
{
    protected $singularTerm = null;
    protected $pluralTerm = null;

    /**
     * CrudController constructor.
     *
     * @throws Exception
     */
    public function __construct()
    {
        if($this->singularTerm == null || $this->pluralTerm == null){
            throw new Exception(get_called_class() . " is missing values for singularTerm and pluralTerm");
        }
        parent::__construct();
    }

    /**
     * @param Request  $request
     * @param Response $response
     *
     * @return Response
     * @throws Exception
     */
    public function getAllRequest(Request $request, Response $response): Response
    {
        if ($request->hasHeader("count")) {
            return $this->getCountRequest($request, $response);
        }

        $service = $this->getService();
        $filter = $this->parseFilters($request);

        return $this->jsonSuccessResponse(
            [
                'Action'          => 'LIST',
                $this->pluralTerm => $service->getAll($filter),
            ],
            $request,
            $response
        );
    }

    public function getRequest(Request $request, Response $response, $args): Response
    {
        $object = $this->getService()->getByPK($args);
        if ($object) {
            return $this->jsonSuccessResponse(
                [
                    'Action'            => 'GET',
                    $this->singularTerm => $object,
                ],
                $request,
                $response
            );
        }
        return $this->jsonFailureResponse(
            sprintf(
                "No such %s found with id %s",
                strtolower($this->singularTerm),
                $args['id']
            ),
            $request,
            $response
        );
    }

    public function updateRequest(Request $request, Response $response, $args): Response
    {
        $newObjectArray = $request->getParsedBody();
        try {
            $object = $this->getService()->update($args, $newObjectArray);
            if ($object === false) {
                return $this->jsonFailureResponse([
                    "Reason" => "Validation failure",
                    "Errors" => $this->getService()->getValidationErrors()
                ], $request, $response);
            }
            return $this->jsonSuccessResponse(
                [
                    'Action'            => 'UPDATE',
                    $this->singularTerm => $object,
                ],
                $request,
                $response
            );
        } catch (InvalidQueryException $iqe) {
            return $this->jsonResponseException($iqe, $request, $response);
        }
    }

    public function createRequest(Request $request, Response $response): Response
    {
        $newObjectArray = $request->getParsedBody();
        try {
            $object = $this->getService()->create($newObjectArray);
            if ($object === false) {
                return $this->jsonFailureResponse([
                    "Reason" => "Validation failure",
                    "Errors" => $this->getService()->getValidationErrors()
                ], $request, $response);
            }
            return $this->jsonSuccessResponse(
                [
                    'Action'            => 'CREATE',
                    $this->singularTerm => $object,
                ],
                $request,
                $response
            );
        } catch (InvalidQueryException $iqe) {
            return $this->jsonResponseException($iqe, $request, $response);
        }
    }

    public function validateRequest(Request $request, Response $response): Response
    {
        $newObjectArray = $request->getParsedBody();

        $scenario = $request->getHeader("scenario");
        if (empty($scenario)) {
            $scenario = AbstractValidator::SCENARIO_DEFAULT;
        } else {
            if (is_array($scenario)) {
                $scenario = $scenario[0];
            }
        }

        $this->getService()->validateData($newObjectArray, $scenario);

        return $this->jsonSuccessResponse(
            [
                'Action'     => 'VALIDATE',
                'Validation' => $this->getService()->getValidationErrors(),
            ],
            $request,
            $response
        );
    }

    /**
     * @param Request  $request
     * @param Response $response
     *
     * @return Response
     * @throws Exception
     */
    public function getCountRequest(Request $request, Response $response): Response
    {
        $filter = $this->parseFilters($request);

        $result = $this->getService()->count($filter);

        return $this->jsonSuccessResponse(
            [
                'Action'          => 'COUNT',
                $this->pluralTerm => $result,
            ],
            $request,
            $response
        );
    }
}
