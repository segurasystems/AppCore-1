<?php
/**
 * Created by PhpStorm.
 * User: wolfgang
 * Date: 10/03/2019
 * Time: 13:50
 */

namespace Gone\AppCore\Abstracts;

use Gone\SDK\Common\Abstracts\AbstractModel;
use Gone\SDK\Common\Filters\Filter;
use Gone\SDK\Common\Filters\FilterCondition;
use Zend\Db\Adapter\AdapterInterface;
use Zend\Db\ResultSet\ResultSet;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Predicate\PredicateInterface;
use Zend\Db\Sql\Select;
use Zend\Db\Sql\Where;
use \Zend\Db\TableGateway\TableGateway as TableGateway;
use Zend\Db\Sql\Sql as ZendSQL;
use Gone\SDK\Common\Abstracts\AbstractModel as Model;

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
     * @param Filter $filter
     *
     * @return AbstractModel|null
     */
    public function get(Filter $filter)
    {
        $filter->setLimit(1);
        $filtered = $this->getAllFilter($filter);
        if (count($filtered) > 0) {
            return $filtered[0];
        }
        return null;
    }

    /**
     * @param Filter|null $filter
     *
     * @return AbstractModel[]
     */
    public function getAll(Filter $filter = null)
    {
        $select = $this->getSql()->select();
        $this->applyFilterToSelect($select, $filter);
        return $this->getWithSelect($select);
    }

    public function getAllField(string $field, Filter $filter = null, $type = null)
    {
        $result = $this->getAllFields([$field], $filter);
        array_walk($this->getAllFields([$field], $filter),function($item,$key) use($field,$type){
            $item = $item[$field];
            if($type){
                switch ($type){
                    case "int":
                    case "integer":
                        $item = intval($item);
                        break;
                    case "decimal":
                    case "float":
                    case "double":
                        $item = floatval($item);
                        break;
                }
            }
        });
        return $result;
    }

    public function getAllFields(array $fields, Filter $filter = null)
    {
        $select = $this->getSQL()->select();
        $this->applyFilterToSelect($select, $filter);
        $select->columns($fields);
        return $this->getWithSelectRaw($select);
    }

    /**
     * @param Filter|null $filter
     *
     * @return int
     */
    public function count(Filter $filter = null)
    {
        $select = $this->getSQL()->select();
        $select->columns(['count' => new Expression('IFNULL(COUNT(*),0)')]);
        $this->applyFilterToSelect($select);
        $row = $this->getSQL()
            ->prepareStatementForSqlObject($select)
            ->execute()
            ->current();
        return $row["count"] ?? 0;
    }

    /**
     * @param Select $select
     *
     * @return array
     */
    private function getWithSelect(Select $select)
    {
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

        $resultSet = $this->getRawTableGateway()->selectWith($select);
        $results = [];
        for ($i = 0; $i < $resultSet->count(); $i++) {
            $results[] = $resultSet->current();
            $resultSet->next();
        }
        return $results;
    }

    private function applyFilterToSelect(Select $select, Filter $filter = null)
    {
        if ($filter) {
            $this->applyFilterLimitToSelect($select, $filter);
            $this->applyFilterOrderToSelect($select, $filter);
            $this->applyFilterWhereToSelect($select, $filter);
            $this->applyFilterJoinsToSelect($select, $filter);
            $this->applyFilterDistinctToSelect($select, $filter);
        }
    }

    private function applyFilterLimitToSelect(Select $select, Filter $filter)
    {
        if ($filter->getLimit()) {
            $select->limit($filter->getLimit());
            if ($filter->getOffset()) {
                $select->offset($filter->getOffset());
            }
        }
    }

    private function applyFilterOrderToSelect(Select $select, Filter $filter)
    {
        if ($filter->getOrder() !== null) {
            $select->order("{$filter->getOrder()} {$filter->getOrderDirection()}");
        }
    }

    private function applyFilterWhereToSelect(Select $select, Filter $filter)
    {
        foreach ($filter->getWheres() as $where) {
            $this->applyWhereToSelect($select, $where);
        }
    }

    private function applyFilterJoinsToSelect(Select $select, Filter $filter)
    {
        foreach ($filter->getJoins() as $join) {
            $this->applyJoinToSelect($select, $join);
        }
    }

    private function applyFilterDistinctToSelect(Select $select, Filter $filter)
    {
//        TODO : Add distinct to Filter
//        if($filter->getDistinct()){
//            $select->quantifier(Select::QUANTIFIER_DISTINCT)
//                ->columns($filter->getDistinct());
//        }
    }

    private function applyJoinToSelect(Select $select, $join)
    {
        $select->join(
            $join["tableFrom"],
            "{$join["tableFrom"]}.{$join["fieldFrom"]} = {$join["tableTo"]}.{$join["fieldTo"]}",
            $join["columns"],
            $join["type"]);
    }

    private function applyWhereToSelect(Select $select, $condition)
    {
        if (
            $condition instanceof \Closure
            || $condition instanceof Where
            || $condition instanceof PredicateInterface
        ) {
            $select->where($condition);
        } else {
            $spec = function (Where $where) use ($condition) {
                $column = (empty($condition["table"]) ? '' : "{$condition["table"]}.") . $condition["column"];
                switch ($condition['condition']) {
                    case FilterCondition::CONDITION_EQUAL:
                        $where->equalTo($column, $condition['value']);
                        break;
                    case FilterCondition::CONDITION_NOT_EQUAL:
                        $where->notEqualTo($column, $condition['value']);
                        break;
                    case FilterCondition::CONDITION_GREATER_THAN:
                        $where->greaterThan($column, $condition['value']);
                        break;
                    case FilterCondition::CONDITION_GREATER_THAN_OR_EQUAL:
                        $where->greaterThanOrEqualTo($column, $condition['value']);
                        break;
                    case FilterCondition::CONDITION_LESS_THAN:
                        $where->lessThan($column, $condition['value']);
                        break;
                    case FilterCondition::CONDITION_LESS_THAN_OR_EQUAL:
                        $where->lessThanOrEqualTo($column, $condition['value']);
                        break;
                    case FilterCondition::CONDITION_LIKE:
                        $where->like($column, $condition['value']);
                        break;
                    case FilterCondition::CONDITION_NOT_LIKE:
                        $where->notLike($column, $condition['value']);
                        break;
                    case FilterCondition::CONDITION_IN:
                        $where->in($column, $condition['value']);
                        break;
                    case FilterCondition::CONDITION_NOT_IN:
                        $where->notIn($column, $condition['value']);
                        break;

                    default:
                        // @todo better exception plz.
                        throw new \Exception("Cannot work out what conditional '{$condition['condition']}' is supposed to do in Zend... Probably unimplemented?");
                }
            };
            $select->where($spec);
        }
    }
}