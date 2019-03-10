<?php

namespace Gone\AppCore\Abstracts;

use Gone\SDK\Common\Abstracts\AbstractModel;
use Gone\SDK\Common\Filters\Filter;
use Zend\Db\ResultSet\ResultSet;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Select;

abstract class Service
{
    /** @var TableAccessLayer */
    private $tableAccessLayer;

    public function __construct(TableAccessLayer $tableAccessLayer)
    {
        $this->tableAccessLayer = $tableAccessLayer;
    }

    protected function getTableAccessLayer()
    {
        return $this->tableAccessLayer;
    }

    /**
     * @param $pk
     * @param $dataArray
     * @return AbstractModel|null
     */
    abstract public function update($pk, $dataArray);

    /**
     * @param $dataArray
     * @return AbstractModel|null
     */
    abstract public function create($dataArray);

    /**
     * @param Filter $filter
     *
     * @return AbstractModel|null
     */
    public function get(Filter $filter)
    {
        return $this->getTableAccessLayer()
            ->get($filter);
    }

    /**
     * @param Filter $filter
     *
     * @return AbstractModel[]
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
