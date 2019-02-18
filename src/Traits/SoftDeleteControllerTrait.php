<?php

namespace Gone\AppCore\Traits;

use Slim\Http\Request;
use Slim\Http\Response;

trait SoftDeleteControllerTrait
{


    public function reinstateRequest(Request $request, Response $response, $args): Response
    {
        $object = $this->getService()->getById($args["id"]);
        if ($object) {
            $object->reinstate();

            return $this->jsonResponse(
                [
                    'Status'                          => 'Okay',
                    'Action'                          => 'REINSTATE',
                    $this->service->getTermSingular() => $object->__toArray(),
                ],
                $request,
                $response
            );
        }
        return $this->jsonResponse(
            [
                'Status'                          => 'Fail',
                'Reason'                          => sprintf(
                    "No such %s found with id %s",
                    strtolower($this->service->getTermSingular()),
                    $args['id']
                )
            ],
            $request,
            $response
        );
    }
    }
}
