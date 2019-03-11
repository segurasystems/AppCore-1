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

    protected $modelClass;

    public function __construct(TableAccessLayer $tableAccessLayer)
    {
        $this->tableAccessLayer = $tableAccessLayer;
    }

    protected function getAccessLayer()
    {
        return $this->tableAccessLayer;
    }

    /**
     * @param $pk
     * @param $dataArray
     * @return AbstractModel|null
     */
    public function update($pk, $dataArray){
        $model = $this->getByPK($pk);
        $model->setProperties($dataArray);
        return $this->getAccessLayer()->update($model);
    }

    /**
     * @param $dataArray
     * @return AbstractModel|null
     */
    public function create($dataArray){
        $model = new $this->modelClass($dataArray);
        return $this->getAccessLayer()->create($model);
    }

    /**
     * @param $pk
     * @return AbstractModel|null
     */
    public function getByPK($pk){
        return $this->getAccessLayer()->getByPK($pk);
    }

    /**
     * @param Filter $filter
     *
     * @return AbstractModel|null
     */
    public function get(Filter $filter)
    {
        return $this->getAccessLayer()
            ->get($filter);
    }

    /**
     * @param Filter $filter
     *
     * @return AbstractModel[]
     */
    public function getAll(Filter $filter): array
    {
        list($matches, $count) = $this->getAccessLayer()
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
        return $this->getAccessLayer()
            ->count($filter);
    }

    public function updatePK($oldPK,$newPK){
        $model = $this->getByPK($oldPK);
        return $this->getAccessLayer()->updatePK($newPK);
    }
}
