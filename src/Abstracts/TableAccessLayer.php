<?php

namespace Gone\AppCore\Abstracts;

use Gone\SDK\Common\Abstracts\AbstractModel;
use Zend\Db\Adapter\AdapterInterface;
use Zend\Db\ResultSet\ResultSet;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Select;
use \Zend\Db\TableGateway\TableGateway as TableGateway;
use Gone\SDK\Common\Abstracts\AbstractModel as Model;
use Exception;

abstract class TableAccessLayer
{
    private $_tableGateway;
    private $_rawTableGateway;
    protected $modelClass;
    protected $isView;

    protected $table;

    private $_adapter;
    private $_zendFeatures;

    public function __construct(AdapterInterface $adapter, $table, $zendfeatures = null)
    {
        $this->table = $table;
        $this->_adapter = $adapter;
        $this->_zendFeatures = $zendfeatures;
    }

    protected function getSelect(){
        return new Select($this->getTable());
    }

    public function getTable()
    {
        return $this->table;
    }

    protected function getRawTableGateway()
    {
        if (!isset($this->_rawTableGateway)) {
            $this->_rawTableGateway = new TableGateway($this->getTable(), $this->_adapter, $this->_zendFeatures);
        }
        return $this->_rawTableGateway;
    }

    protected function getTableGateway()
    {
        if (!isset($this->_tableGateway)) {
            $resultSetPrototype = new ResultSet(ResultSet::TYPE_ARRAYOBJECT, new $this->modelClass);
            $this->_tableGateway = new TableGateway($this->getTable(), $this->_adapter, $this->_zendFeatures, $resultSetPrototype);
        }
        return $this->_tableGateway;
    }

    protected function getSQL()
    {
        return $this->getTableGateway()->getSql();
    }

    protected function getRawSQL()
    {
        return $this->getRawTableGateway()->getSql();
    }

    public function save(Model $model)
    {
        $pks = $model->getOriginalPrimaryKeys();
        $pkCount = count($pks);
        $pks = array_filter($pks);
        if (count($pks) == $pkCount) {
            //$oldModel = $this->getByPK($pks);
            return $this->update($model);
        }
        return $this->create($model);
    }

    public function update(Model $model)
    {
        if ($this->isView) {
            $this->updateThroughView($model);
        } else {
            $this->getTableGateway()
                ->update(
                    $model->__toDirtyArray(),
                    $model->getOriginalPrimaryKeys()
                );
        }
        return $this->getMatching($model->getPrimaryKeys());
    }

    private function updateThroughView(Model $model)
    {
        $data = $model->__toDirtyArray();
        $pks = $model->getOriginalPrimaryKeys();
        $breakdown = $this->getViewModelBreakdown();
        $rows = 0;
        foreach ($breakdown as $baseTable => $structure) {
            $columns = $structure["columns"];
            $_data = array_filter($data, function ($key) use ($columns) {
                return in_array($key, $columns);
            }, ARRAY_FILTER_USE_KEY);
            if (!empty($_data)) {
                $rows += $this->getTableGateway()->update(
                    $_data,
                    $pks
                );
            }
        }
        return $rows;
    }

    public function create(Model $model)
    {
        if ($this->isView) {
            $pk = $this->createThroughView($model);
        } else {
            $this->getTableGateway()->insert($model->__toArray());
            $pk = $this->getTableGateway()->getLastInsertValue();
        }
        if (!is_array($pk)) {
            $pk = ["id" => $pk];
        }
        return $this->getMatching($pk);
    }

    private function createThroughView(Model $model)
    {
        $data = $model->__toArray();
        $breakdown = $this->getViewModelBreakdown();
        $inserts = [];
        unset($data["id"]);
        foreach ($breakdown as $baseTable => $structure) {
            $columns = $structure["columns"];
            $_data = array_filter($data, function ($key) use ($columns) {
                return in_array($key, $columns);
            }, ARRAY_FILTER_USE_KEY);
            if (!empty($structure["dependent"])) {
                foreach ($structure["dependent"] as $dependant => $source) {
                    $_data[$dependant] = $inserts[$source];
                }
            }
            $this->getTableGateway()->insert($_data);
            $inserts[$baseTable] = $this->getTableGateway()->getLastInsertValue();
        }
        foreach ($breakdown as $baseTable => $structure) {
            if ($structure["pk"] == "id") {
                $data["id"] = $inserts[$baseTable];
            }
        }
        $inserted = [];
        foreach ($model->getPrimaryKeyFields() as $key) {
            $inserted[$key] = $data[$key] ?? null;
        }
        return $inserted;
    }

    protected function getViewModelBreakdown()
    {
        return [];
    }

    /**
     * @param $_pk
     *
     * @return AbstractModel|null
     */
    public function getByPK($_pk)
    {
        if (!is_array($_pk)) {
            $pk = ["id" => $_pk];
        } else {
            $pk = $_pk;
        }
        return $this->getMatching($pk);
    }

    /**
     * @param array  $keyValue
     * @param null   $orderBy
     * @param string $orderDirection
     *
     * @return AbstractModel|null
     */
    public function getMatching($keyValue = [], $orderBy = null, $orderDirection = Select::ORDER_DESCENDING)
    {
        $matching = $this->getAllMatching($keyValue, $orderBy, $orderDirection, 1);
        if (count($matching) > 0) {
            return $matching[0];
        }
        return null;
    }

    /**
     * @param array    $keyValue
     * @param null     $orderBy
     * @param string   $orderDirection
     * @param int|null $limit
     *
     * @return AbstractModel[]
     */
    public function getAllMatching(
        $keyValue = [],
        $orderBy = null,
        $orderDirection = Select::ORDER_DESCENDING,
        int $limit = null
    ) {
        $select = $this->getSQL()->select();
        $select->where($keyValue);
        if ($orderBy) {
            $select->order("{$orderBy} {$orderDirection}");
        }
        if ($limit) {
            $select->limit($limit);
        }
        return $this->getWithSelect($select);
    }

    /**
     * @param Select $select
     *
     * @return mixed|null
     * @throws Exception
     */
    public function get(Select $select)
    {
        $select->limit(1);
        $selected = $this->getAll($select);
        return $selected[0] ?? null;
    }

    /**
     * @param Select $select
     *
     * @return mixed|null
     * @throws Exception
     */
    public function getRaw(Select $select)
    {
        $select->limit(1);
        $selected = $this->getAllRaw($select);
        return $selected[0] ?? null;
    }

    /**
     * @param Select $select
     *
     * @return array
     * @throws Exception
     */
    public function getAll(Select $select)
    {
        return $this->getWithSelect($select);
    }

    /**
     * @param Select $select
     *
     * @return array
     * @throws Exception
     */
    public function getAllRaw(Select $select)
    {
        return $this->getWithSelectRaw($select);
    }

    /**
     * @param Select $select
     *
     * @return int
     * @throws Exception
     */
    public function count(Select $select)
    {
        $select->limit(0)
            ->offset(0)
            ->columns(['count' => new Expression('IFNULL(COUNT(*),0)')]);
        $row = $this->getSQL()
            ->prepareStatementForSqlObject($select)
            ->execute()
            ->current();
        return intval($row["count"] ?? 0);
    }

    /**
     * @param Select $select
     *
     * @return array
     */
    private function getWithSelect(Select $select)
    {
        /** @var ResultSet $resultSet */
        $resultSet = $this->getTableGateway()->selectWith($select);
        $results = [];
        for ($i = 0; $i < $resultSet->count(); $i++) {
            $results[] = $resultSet->current();
            $resultSet->next();
        }
        return $results;
    }

    private function getWithSelectRaw(Select $select)
    {
        /** @var ResultSet $resultSet */
        $resultSet = $this->getRawTableGateway()->selectWith($select);
        $results = [];
        for ($i = 0; $i < $resultSet->count(); $i++) {
            $results[] = $resultSet->current();
            $resultSet->next();
        }
        return $results;
    }
}