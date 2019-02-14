<?php
namespace Gone\AppCore\Abstracts;

use Zend\Db\ResultSet\ResultSet;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Select;

abstract class Service
{
    abstract public function getNewModelInstance();

    abstract public function getTermPlural() : string;

    abstract public function getTermSingular() : string;

    abstract public function getNewTableGatewayInstance();

    /**
     * @param int|null               $limit
     * @param int|null               $offset
     * @param array|\Closure[]|null  $wheres
     * @param string|Expression|null $order
     * @param string|null            $orderDirection
     *
     * @return Model[]
     */
    public function getAll(
        int $limit = null,
        int $offset = null,
        array $wheres = [],
        $order = null,
        string $orderDirection = null,
        array $joins = []
    ) {
        /** @var TableGateway $tableGateway */
        $tableGateway              = $this->getNewTableGatewayInstance();
        list($matches, $count)     = $tableGateway->fetchAll(
            $limit,
            $offset,
            $wheres,
            $order,
            $orderDirection !== null ? $orderDirection : Select::ORDER_ASCENDING,
            $joins
        );
        $return = [];

        if ($matches instanceof ResultSet) {
            foreach ($matches as $match) {
                $return[] = $match;
            }
        }
        return $return;
    }

    /**
     * @param string|null           $distinctColumn
     * @param array|\Closure[]|null $wheres
     * @param array|                $joins
     *
     * @return Model[]
     */
    public function getDistinct(
        string $distinctColumn,
        array $wheres = [],
        array $joins = []
    ) {
        /** @var TableGateway $tableGateway */
        $tableGateway = $this->getNewTableGatewayInstance();
        list($matches, $count) = $tableGateway->fetchDistinct(
            $distinctColumn,
            $wheres,
            $joins
        );

        $return = [];
        if ($matches instanceof ResultSet) {
            foreach ($matches as $match) {
                $return[] = $match;
            }
        }
        return $return;
    }

    /**
     * @param array|\Closure[]|null $wheres
     * @param array                 $joins
     *
     * @return int
     */
    public function countAll(
        array $wheres = null,
        array $joins = []
    ) {
        /** @var TableGateway $tableGateway */
        $tableGateway              = $this->getNewTableGatewayInstance();
        return $tableGateway->getCount($wheres,$joins);
    }
}
