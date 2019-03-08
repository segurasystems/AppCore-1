<?php
namespace Gone\AppCore\Abstracts;

use Gone\AppCore\Interfaces\ModelInterface;
use Gone\SDK\Common\Filters\Filter;
use Slim\Http\Request;
use Slim\Http\Response;
use Zend\Db\Adapter\Exception\InvalidQueryException;

abstract class CrudController extends Controller
{
    public function listRequest(Request $request, Response $response) : Response
    {
        $objects = [];
        $service = $this->getService();
        if ($this->requestHasFilters($request, $response)) {
            $filterBehaviours = $this->parseFilters($request, $response);
            $foundObjects     = $service->getAllFilter($filterBehaviours);
        } else {
            $foundObjects = $service->getAllFilter(Filter::Factory());
        }

        foreach ($foundObjects as $object) {
            $objects[] = $object->__toPublicArray();
        }

        return $this->jsonSuccessResponse(
            [
                'Action'                        => 'LIST',
                $this->service->getTermPlural() => $objects,
            ],
            $request,
            $response
        );
    }

    public function getRequest(Request $request, Response $response, $args) : Response
    {
        $object = $this->getService()->getById($args['id']);
        if ($object) {
            return $this->jsonSuccessResponse(
                [
                    'Action'                          => 'GET',
                    $this->service->getTermSingular() => $object->__toArray(),
                ],
                $request,
                $response
            );
        }
        return $this->jsonFailureResponse(
            sprintf(
                "No such %s found with id %s",
                strtolower($this->service->getTermSingular()),
                $args['id']
            ),
            $request,
            $response
        );
    }

    public function createRequest(Request $request, Response $response, $args) : Response
    {
        $newObjectArray = $request->getParsedBody();
        try {
            $object = $this->getService()->createFromArray($newObjectArray);
            return $this->jsonSuccessResponse(
                [
                    'Action'                          => 'CREATE',
                    $this->service->getTermSingular() => $object->__toArray(),
                ],
                $request,
                $response
            );
        } catch (InvalidQueryException $iqe) {
            return $this->jsonResponseException($iqe, $request, $response);
        }
    }

    public function createBulkRequest(Request $request, Response $response, $args) : Response
    {
        $newObjectArray = $request->getParsedBody();
        try {
            $objects = [];
            foreach ($newObjectArray as $newObjectArrayItem){
                $objects[] = $this->getService()->createFromArray($newObjectArrayItem)->__toArray();
            }
            return $this->jsonSuccessResponse(
                [
                    'Action'                          => 'CREATE',
                    $this->service->getTermPlural() => $objects,
                ],
                $request,
                $response
            );
        } catch (InvalidQueryException $iqe) {
            return $this->jsonResponseException($iqe, $request, $response);
        }
    }

    public function deleteRequest(Request $request, Response $response, $args) : Response
    {
        /** @var ModelInterface $object */
        $object = $this->getService()->getById($args['id']);
        if ($object) {
            $array = $object->__toArray();
            $object->destroy();

            return $this->jsonSuccessResponse(
                [
                    'Action'                          => 'DELETE',
                    $this->service->getTermSingular() => $array,
                ],
                $request,
                $response
            );
        }
        return $this->jsonFailureResponse(
            sprintf(
                "No such %s found with id %s",
                strtolower($this->service->getTermSingular()),
                $args['id']
            ),
            $request,
            $response
        );
    }

    public function updateIDRequest(Request $request, Response $response, $args) : Response
    {
        /** @var ModelInterface $object */
        $object = $this->getService()->updatePK($args['oldID'],$args['newID']);
        if ($object) {
            return $this->jsonSuccessResponse(
                [
                    'Action'                          => 'UPDATE_PK',
                    $this->service->getTermSingular() => $object->__toArray(),
                ],
                $request,
                $response
            );
        }
        return $this->jsonFailureResponse(
            sprintf(
                "No such %s found with id %s",
                strtolower($this->service->getTermSingular()),
                $args['oldID']
            ),
            $request,
            $response
        );
    }
}
