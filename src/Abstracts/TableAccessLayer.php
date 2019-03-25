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
use Gone\SDK\Common\QueryBuilder\Condition;
use Gone\SDK\Common\QueryBuilder\ConditionGroup;
use Gone\SDK\Common\QueryBuilder\Join;
use Gone\SDK\Common\QueryBuilder\Query;
use mysql_xdevapi\Exception;
use Zend\Db\Adapter\AdapterInterface;
use Zend\Db\ResultSet\ResultSet;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Predicate\Between;
use Zend\Db\Sql\Predicate\In;
use Zend\Db\Sql\Predicate\Like;
use Zend\Db\Sql\Predicate\NotBetween;
use Zend\Db\Sql\Predicate\NotIn;
use Zend\Db\Sql\Predicate\NotLike;
use Zend\Db\Sql\Predicate\Operator;
use Zend\Db\Sql\Predicate\PredicateInterface;
use Zend\Db\Sql\Predicate\PredicateSet;
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
     * @param Query $filter
     *
     * @return AbstractModel|null
     */
    public function get(Query $filter)
    {
        $filter->limit(1);
        $filtered = $this->getAll($filter);
        return $filtered[0] ?? null;
    }

    /**
     * @param Query|null $filter
     *
     * @return AbstractModel[]
     */
    public function getAll(Query $filter = null)
    {
        $select = $this->getSql()->select();
        $this->applyFilterToSelect($select, $filter);
        return $this->getWithSelect($select);
    }

    public function getAllField(string $field, Query $filter = null, string $type = null)
    {
        return array_column($this->getAllFields([$field], $filter, [$field => $type]), $field);
    }

    public function getAllFields(array $fields, Query $filter = null, array $types = [])
    {
        $select = $this->getSQL()->select();
        $this->applyFilterToSelect($select, $filter);
        $select->columns($fields);
        return array_map(function ($item) use ($types) {
            foreach ($types as $field => $type) {
                if (isset($item[$field]) && $type) {
                    $value = $item[$field];
                    switch ($type) {
                        case "int":
                        case "integer":
                            $value = intval($value);
                            break;
                        case "decimal":
                        case "float":
                        case "double":
                            $value = floatval($value);
                            break;
                    }
                    $item[$field] = $value;
                }
            }
            return $item;
        }, $this->getWithSelectRaw($select));
    }

    /**
     * @param Query|null $filter
     *
     * @return int
     */
    public function count(Query $filter = null)
    {
        $select = $this->getSQL()->select();
        $select->columns(['count' => new Expression('IFNULL(COUNT(*),0)')]);
        $filter->limit(0)->offset(0)->setOrder([]);
        $this->applyFilterToSelect($select, $filter);
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

    private function applyFilterToSelect(Select $select, Query $filter = null)
    {
        if ($filter) {
            $this->applyFilterLimitToSelect($select, $filter);
            $this->applyFilterOrderToSelect($select, $filter);
            $this->applyFilterWhereToSelect($select, $filter);
            $this->applyFilterJoinsToSelect($select, $filter);
            $this->applyFilterDistinctToSelect($select, $filter);
        }
    }

    private function applyFilterLimitToSelect(Select $select, Query $filter)
    {
        if ($filter->getLimit()) {
            $select->limit($filter->getLimit());
            if ($filter->getOffset()) {
                $select->offset($filter->getOffset());
            }
        }
    }

    private function applyFilterOrderToSelect(Select $select, Query $filter)
    {
        if (!empty($filter->getOrder())) {
            $orders = [];
            foreach ($filter->getOrder() as $field => $direction) {
                $orders[] = "{$field} {$direction}";
            }
            if (!empty($orders)) {
                $select->order(implode(", ", $orders));
            }
        }
    }

    private function applyFilterWhereToSelect(Select $select, Query $filter)
    {
        $condition = $filter->getCondition();
        if ($condition instanceof ConditionGroup) {
            $this->applyFilterConditionGroupToSelect($select, $condition);
        }
    }

    private function applyFilterJoinsToSelect(Select $select, Query $filter)
    {
        foreach ($filter->getJoins() as $join) {
            $this->applyJoinToSelect($select, $join);
        }
    }

    private function applyFilterDistinctToSelect(Select $select, Query $filter)
    {
//        TODO : Add distinct to Filter
//        if($filter->getDistinct()){
//            $select->quantifier(Select::QUANTIFIER_DISTINCT)
//                ->columns($filter->getDistinct());
//        }
    }

    private function applyJoinToSelect(Select $select, Join $join)
    {
        $table = $join->getJoin()[0];
        $alias = $table;
        if(!empty($join->getAlias())){
            $alias = $join->getAlias();
            $table = [$alias=>$table];
        }
        $select->join($table,"{$alias}.{$join->getJoin()[1]} = {$join->getTo()[0]}.{$join->getTo()[1]}",[],$join->getType() ?? Select::JOIN_INNER);
    }

    private function applyFilterConditionGroupToSelect(Select $select, ConditionGroup $condition)
    {
        $select->where($this->createPredicateFromConditionGroup($condition));
    }

    private function createPredicateFromConditionGroup(ConditionGroup $condition)
    {
        $predicates = [];
        foreach ($condition->getConditions() as $condition) {
            if ($condition instanceof ConditionGroup) {
                $predicates[] = $this->createPredicateFromConditionGroup($condition);
            } else {
                if ($condition instanceof Condition) {
                    $predicates[] = $this->createPredicateFromCondition($condition);
                }
            }
        }
        return new PredicateSet($predicates, $condition->getComparator());
    }

    /**
     * @param Condition $condition
     *
     * @return PredicateInterface
     * @throws \Exception
     */
    private function createPredicateFromCondition(Condition $condition)
    {
        if($condition->getTable()){
            $table = "{$condition->getTable()}";
        } else {
            $table = $this->table;
        }
        $field = "{$table}.{$condition->getColumn()}";
        switch ($condition->getComparator()) {
            case Condition::IN:
                return new In($field,$condition->getValue());
            case Condition::NOT_IN:
                return new NotIn($field,$condition->getValue());
            case Condition::LIKE:
                return new Like($field,$condition->getValue());
            case Condition::NOT_LIKE:
                return new NotLike($field,$condition->getValue());
            case Condition::BETWEEN:
                return new Between($field,$condition->getValue()["min"],$condition->getValue()["max"]);
            case Condition::NOT_BETWEEN:
                return new NotBetween($field,$condition->getValue()["min"],$condition->getValue()["max"]);
            case Condition::LESS_THAN:
            case Condition::LESS_THAN_OR_EQUAL:
            case Condition::GREATER_THAN:
            case Condition::GREATER_THAN_OR_EQUAL:
            case Condition::EQUAL:
            case Condition::NOT_EQUAL:
                return new Operator($field,$condition->getComparator(),$condition->getValue());
            default:
                throw new \Exception("Unable to map comparator for condition to predicate");
                break;
        }
    }
}