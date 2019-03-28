<?php

namespace Gone\AppCore\Abstracts;

use Gone\SDK\Common\Abstracts\AbstractModel;
//use Gone\SDK\Common\Filters\Filter;
use Gone\SDK\Common\QueryBuilder\Query;
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
        $this->__afterConstruct();
    }

    protected function __afterConstruct()
    {

    }

    protected function getAccessLayer()
    {
        return $this->tableAccessLayer;
    }

    /**
     * @param AbstractModel $model
     *
     * @return AbstractModel|null
     */
    public function save(AbstractModel $model)
    {
        return $this->getAccessLayer()->save($model);
    }

    public function update($pk, $dataArray)
    {
        $model = $this->getByPK($pk);
        $model->setProperties($dataArray);
        return $this->save($model);
    }

    /**
     * @param $dataArray
     *
     * @return AbstractModel|null
     */
    public function create($dataArray)
    {
        $model = new $this->modelClass($dataArray);
        return $this->save($model);
    }

    /**
     * @param $pk
     *
     * @return AbstractModel|null
     */
    public function getByPK($pk)
    {
        return $this->getAccessLayer()->getByPK($pk);
    }

    /**
     * @param Query $filter
     *
     * @return AbstractModel|null
     */
    public function get(Query $filter)
    {
        return $this->getAccessLayer()
            ->get($filter);
    }

    /**
     * @param Query $filter
     *
     * @return AbstractModel[]
     */
    public function getAll(Query $filter = null): array
    {
        return $this->getAccessLayer()
            ->getAll($filter);
    }

    /**
     * @param Query $filter
     *
     * @return int
     */
    public function count(Query $filter = null): int
    {
        return $this->getAccessLayer()
            ->count($filter);
    }

    public function getAllField(string $field, Query $filter = null, string $type = null)
    {
        return $this->getAccessLayer()->getAllField($field, $filter, $type);
    }

    public function getAllFields(array $fields, Query $filter = null, array $types = [])
    {
        return $this->getAccessLayer()->getAllFields($fields, $filter, $types);
    }
}
