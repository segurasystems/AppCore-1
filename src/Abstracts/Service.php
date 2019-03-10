<?php

namespace Gone\AppCore\Abstracts;

use Gone\SDK\Common\Filters\Filter;
use Zend\Db\ResultSet\ResultSet;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Select;

abstract class Service
{
    /** @var TableAccessLayer */
    private $tableAccessLayer;

    public function __construct(TableAccessLayer $tableGateway)
    {
        $this->tableAccessLayer = $tableGateway;
    }

    protected function getTableAccessLayer()
    {
        return $this->tableAccessLayer;
    }

    abstract public function update($pk, $dataArray): ?Model;

    abstract public function create($dataArray): ?Model;

    abstract public function getByPK($pk): ?Model;

    /**
     * @param Filter $filter
     *
     * @return Model|null
     */
    public function get(Filter $filter): ?Model
    {
        return $this->getTableAccessLayer()
            ->get($filter);
    }

    /**
     * @param Filter $filter
     *
     * @return Model[]
     */
    public function getAll(Filter $filter): array
    {
        list($matches, $count) = $this->getTableAccessLayer()
            ->getAll($filter);
        $result = [];
        if ($matches instanceof ResultSet) {
            foreach ($matches as $match) {
                $result[] = $match;
            }
        }
        return $result;
    }

    /**
     * @param Filter $filter
     *
     * @return int
     */
    public function count(Filter $filter = null): int
    {
        return $this->getTableAccessLayer()
            ->count($filter);
    }
}
