<?php

namespace Gone\AppCore\Abstracts;

use Gone\SDK\Common\Abstracts\AbstractModel;
use Gone\AppCore\QueryBuilder\Condition;
use Gone\AppCore\QueryBuilder\ConditionGroup;
use Gone\AppCore\QueryBuilder\Join;
use Gone\AppCore\QueryBuilder\Query;
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
use Zend\Db\Sql\Sql;
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
     * @param Query $filter
     *
     * @return mixed|null
     * @throws Exception
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
     * @return array
     * @throws Exception
     */
    public function getAll(Query $filter = null)
    {
        //print json_encode($filter);die();
        $select = $this->createSelectFromQuery($filter);
        if (!empty($filter)) {
            if (!empty($filter->getColumns())) {
                $result = $this->getWithSelectRaw($select);
                $types = $filter->getTyping();
                if(!empty($types)) {
                    $result = array_map(function ($item) use ($types) {
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
                    }, $result);
                }
                if(count($filter->getColumns()) === 1){
                    $columns = $filter->getColumns();
                    $key = array_keys($columns)[0];
                    if(is_numeric($key)){
                        $key = $columns[$key];
                    }
                    return array_column($result,$key);
                }
                return $result;
            }
        }
        return $this->getWithSelect($select);

    }

    /**
     * @param string      $field
     * @param Query|null  $filter
     * @param string|null $type
     *
     * @return array
     * @throws Exception
     */
    public function getAllField(string $field, Query $filter = null, string $type = null)
    {
        return array_column($this->getAllFields([$field], $filter, [$field => $type]), $field);
    }

    /**
     * @param array      $fields
     * @param Query|null $filter
     * @param array      $types
     *
     * @return array
     * @throws Exception
     */
    public function getAllFields(array $fields, Query $filter = null, array $types = [])
    {
        $select = $this->createSelectFromQuery($filter);
        $select->columns($fields, false);
        return $this->getWithSelectRaw($select);
    }

    /**
     * @param Query|null $filter
     *
     * @return int
     * @throws Exception
     */
    public function count(Query $filter = null)
    {
        $filter->limit(0)->offset(0)->setOrder([]);
        $select = $this->createSelectFromQuery($filter);
        $select->columns(['count' => new Expression('IFNULL(COUNT(*),0)')]);
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

    /**
     * @param Query|null $filter
     *
     * @return Select
     * @throws Exception
     */
    private function createSelectFromQuery(Query $filter = null): Select
    {
        if (empty($filter)) {
            $filter = Query::Factory();
        }


        if (empty($filter->getBaseTable()) || $filter->getBaseTable() === $this->table) {
            $sql = $this->getSQL();
        } else {
            $sql = new Sql($this->_adapter, $filter->getBaseTable());
        }


        $select = $sql->select();
        $this->applyQueryToSelect($select, $filter);
        return $select;
    }

    /**
     * @param Select $select
     * @param Query  $filter
     *
     * @throws Exception
     */
    private function applyQueryToSelect(Select $select, Query $filter)
    {
        $this->applyFilterLimitToSelect($select, $filter);
        $this->applyFilterOrderToSelect($select, $filter);
        $this->applyFilterWhereToSelect($select, $filter);
        $this->applyFilterJoinsToSelect($select, $filter);
        $this->applyFilterDistinctToSelect($select, $filter);
        $this->applyFilterColumnsToSelect($select, $filter);
    }

    private function applyFilterColumnsToSelect(Select $select, Query $filter)
    {
        if (!empty($filter->getColumns())) {
            $select->columns($filter->getColumns(), false);
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

    /**
     * @param Select $select
     * @param Query  $filter
     *
     * @throws Exception
     */
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
        if ($filter->isDistinct()) {
            $select->quantifier(Select::QUANTIFIER_DISTINCT);
        }
    }

    private function applyJoinToSelect(Select $select, Join $join)
    {
        $from = $join->getJoin();
        $to = $join->getTo();

        $table = $from[0];
        $alias = $table;
        if (!empty($join->getAlias())) {
            $alias = $join->getAlias();
            $table = [$alias => $table];
        }
        $select->join($table, "{$alias}.{$from[1]} = {$to[0]}.{$to[1]}", [], $join->getType() ?? Select::JOIN_INNER);
    }

    /**
     * @param Select         $select
     * @param ConditionGroup $condition
     *
     * @throws Exception
     */
    private function applyFilterConditionGroupToSelect(Select $select, ConditionGroup $condition)
    {
        $select->where($this->createPredicateFromConditionGroup($condition));
    }

    /**
     * @param ConditionGroup $conditionGroup
     *
     * @return PredicateSet
     * @throws Exception
     */
    private function createPredicateFromConditionGroup(ConditionGroup $conditionGroup)
    {
        $predicates = [];
        foreach ($conditionGroup->getConditions() as $condition) {
            if ($condition instanceof ConditionGroup) {
                $predicates[] = $this->createPredicateFromConditionGroup($condition);
            } else {
                if ($condition instanceof Condition) {
                    $predicates[] = $this->createPredicateFromCondition($condition);
                }
            }
        }
        return new PredicateSet($predicates, $conditionGroup->getComparator());
    }

    /**
     * @param Condition $condition
     *
     * @return PredicateInterface
     * @throws Exception
     */
    private function createPredicateFromCondition(Condition $condition)
    {
        if ($condition->getTable()) {
            $table = "{$condition->getTable()}";
        } else {
            $table = $this->table;
        }
        $field = "{$table}.{$condition->getColumn()}";

        $value = $condition->getValue();
        if ($value instanceof Query) {
            $value = $this->createSelectFromQuery($value);
        }

        switch ($condition->getComparator()) {
            case Condition::IN:
                return new In($field, $value);
            case Condition::NOT_IN:
                return new NotIn($field, $value);
            case Condition::LIKE:
                return new Like($field, $value);
            case Condition::NOT_LIKE:
                return new NotLike($field, $value);
            case Condition::BETWEEN:
                return new Between($field, $value[0], $value[1]);
            case Condition::NOT_BETWEEN:
                return new NotBetween($field, $value[0], $value[1]);
            case Condition::LESS_THAN:
            case Condition::LESS_THAN_OR_EQUAL:
            case Condition::GREATER_THAN:
            case Condition::GREATER_THAN_OR_EQUAL:
            case Condition::EQUAL:
            case Condition::NOT_EQUAL:
                return new Operator($field, $condition->getComparator(), $value);
            default:
                throw new Exception("Unable to map comparator for condition to predicate");
                break;
        }
    }
}