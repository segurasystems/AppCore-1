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
        if($request->hasHeader("fields")){
            return $this->getFieldsRequest($request,$response);
        }

        $service = $this->getService();
        $filter = $this->parseFilters($request);
        // TODO
        //$this->responder->successResponse($action,$data,$request,$response);

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

    public function createBulkRequest(Request $request, Response $response): Response
    {
        $newObjectArray = $request->getParsedBody();
        try {
            $objects = [];
            foreach ($newObjectArray as $newObjectArrayItem) {
                $objects[] = $this->getService()->create($newObjectArrayItem);
            }
            return $this->jsonSuccessResponse(
                [
                    'Action'          => 'BULK_CREATE',
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
        $filter = $this->parseFilters($request);
        $fields = $request->getHeader("Fields")[0] ?? "[]";
        $fields = json_decode($fields, true) ?? [];
        $types = $request->getHeader("Types")[0] ?? "[]";
        $types = json_decode($types, true) ?? [];

        $count = count($fields);

        if ($count > 1) {
            $result = $this->getService()->getAllFields($fields, $filter, $types);
        } else {
            if ($count === 1) {
                $field = $fields[0];
                $result = $this->getService()->getAllField($field, $filter, $types[$field] ?? null);
            } else {
                $result = [];
            }
        }
        return $this->jsonSuccessResponse(
            [
                'Action'          => 'LIST_FIELDS',
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
