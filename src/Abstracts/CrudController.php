<?php

namespace Gone\AppCore\Abstracts;

use Gone\AppCore\Interfaces\ModelInterface;
use Gone\SDK\Common\Filters\Filter;
use Slim\Http\Request;
use Slim\Http\Response;
use Zend\Db\Adapter\Exception\InvalidQueryException;

abstract class CrudController extends Controller
{
    protected $singularTerm = "Data";
    protected $pluralTerm = "Datas";


    public function getAllRequest(Request $request, Response $response): Response
    {
        if ($request->hasHeader("fields")) {
            return $this->getFieldsRequest($request, $response);
        }
        if ($request->hasHeader("count")) {
            return $this->getCountRequest($request, $response);
        }

        $service = $this->getService();
        $filter = $this->parseQueryHeader($request);
        // TODO
        //$this->responder->successResponse($action,$data,$request,$response);

        return $this->jsonSuccessResponse(
            [
                'Action' => 'LIST',
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
                    'Action' => 'GET',
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
                    'Action' => 'UPDATE',
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
                    'Action' => 'CREATE',
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
        return $this->jsonSuccessResponse(
            [
                'Action' => 'VALIDATE',
                'Validation' => $this->getService()->validateData($newObjectArray),
            ],
            $request,
            $response
        );
    }

    public function createBulkRequest(Request $request, Response $response): Response
    {
        $newObjectArray = $request->getParsedBody();
        try {
            $objects = [];
            foreach ($newObjectArray as $key => $newObjectArrayItem) {
                $objects[$key] = $this->getService()->create($newObjectArrayItem);
            }
            return $this->jsonSuccessResponse(
                [
                    'Action' => 'BULK_CREATE',
                    $this->pluralTerm => $objects,
                ],
                $request,
                $response
            );
        } catch (InvalidQueryException $iqe) {
            return $this->jsonResponseException($iqe, $request, $response);
        }
    }

    public function getFieldsRequest(Request $request, Response $response): Response
    {
        $filter = $this->parseQueryHeader($request);
        $fields = $request->getHeader("Fields")[0] ?? "[]";
        $fields = json_decode($fields, true) ?? [];

        $count = count($fields);

        if ($count > 1) {
            $result = $this->getService()->getAllFields($fields, $filter);
        } else {
            if ($count === 1) {
                $field = $fields[0];
                $result = $this->getService()->getAllField($field, $filter);
            } else {
                $result = [];
            }
        }
        return $this->jsonSuccessResponse(
            [
                'Action' => 'LIST_FIELDS',
                $this->pluralTerm => $result,
            ],
            $request,
            $response
        );
    }

    public function getCountRequest(Request $request, Response $response): Response
    {
        $filter = $this->parseQueryHeader($request);

        $result = $this->getService()->count($filter);

        return $this->jsonSuccessResponse(
            [
                'Action' => 'COUNT',
                $this->pluralTerm => $result,
            ],
            $request,
            $response
        );
    }

//    public function deleteRequest(Request $request, Response $response, $args): Response
//    {
//        /** @var ModelInterface $object */
//        $object = $this->getService()->getById($args['id']);
//        if ($object) {
//            $array = $object->__toArray();
//            $object->destroy();
//
//            return $this->jsonSuccessResponse(
//                [
//                    'Action'                          => 'DELETE',
//                    $this->service->getTermSingular() => $array,
//                ],
//                $request,
//                $response
//            );
//        }
//        return $this->jsonFailureResponse(
//            sprintf(
//                "No such %s found with id %s",
//                strtolower($this->service->getTermSingular()),
//                $args['id']
//            ),
//            $request,
//            $response
//        );
//    }

//    public function updatePKRequest(Request $request, Response $response, $args): Response
//    {
//        /** @var ModelInterface $object */
//        $object = $this->getService()->updatePK($args['oldPK'], $args['newPK']);
//        if ($object) {
//            return $this->jsonSuccessResponse(
//                [
//                    'Action'            => 'UPDATE_PK',
//                    $this->singularTerm => $object,
//                ],
//                $request,
//                $response
//            );
//        }
//        return $this->jsonFailureResponse(
//            sprintf(
//                "No such %s found with id %s",
//                strtolower($this->singularTerm),
//                $args['oldPK']
//            ),
//            $request,
//            $response
//        );
//    }
}
