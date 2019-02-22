<?php

namespace Gone\AppCore\Abstracts;

use Gone\SDK\Common\Filters\Filter;
use Gone\SDK\Common\Filters\FilterCondition;
use Gone\AppCore\ZendSql;
use Gone\SDK\Common\Filters\FilterJoin;
use Zend\Db\Adapter\AdapterInterface;
use Zend\Db\Adapter\Exception\InvalidQueryException;
use Zend\Db\ResultSet\ResultSet;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Predicate;
use Zend\Db\Sql\Predicate\PredicateInterface;
use Zend\Db\Sql\Select;
use Zend\Db\Sql\Where;
use Zend\Db\TableGateway\TableGateway as ZendTableGateway;

abstract class TableGateway extends ZendTableGateway
{
    protected $model;

    public function __construct($table, AdapterInterface $adapter, $features = null, $resultSetPrototype = null, $sql = null)
    {
        $this->adapter = $adapter;
        $this->table = $table;

        if (!$sql) {
            $sql = new ZendSql($this->adapter, $this->table);
        }
        parent::__construct($table, $adapter, $features, $resultSetPrototype, $sql);
    }

    /**
     * @param Model $model
     *
     * @return array|\ArrayObject|null
     */
    public function save(Model $model)
    {
        $model->__pre_save();

        $pk = $model->getPrimaryKeys();

        $pkIsBlank = true;
        foreach ($pk as $key => $value) {
            if (!is_null($value)) {
                $pkIsBlank = false;
            }
        }

        try {
            /** @var Model $oldModel */
            $oldModel = $this->select($pk)->current();
            if ($pkIsBlank || !$oldModel) {
                $pk = $this->saveInsert($model);
                if (!is_array($pk)) {
                    $pk = ['id' => $pk];
                }
            } else {
                $this->saveUpdate($model, $oldModel);
            }
            $updatedModel = $this->getByPrimaryKey($pk);

            // Update the primary key fields on the existant $model object, because we may still be referencing this.
            // While it feels a bit yucky to magically mutate the model object, it is expected behaviour.
            foreach ($model->getPrimaryKeys() as $key => $value) {
                $setter = "set{$key}";
                $getter = "get{$key}";
                $model->$setter($updatedModel->$getter());
            }

            $model->__post_save();

            return $updatedModel;
        } catch (InvalidQueryException $iqe) {
            throw new InvalidQueryException(
                "While trying to call " . get_class() . "->save(): ... " .
                $iqe->getMessage() . "\n\n" .
                substr(var_export($model, true), 0, 1024) . "\n\n",
                $iqe->getCode(),
                $iqe
            );
        }
    }

    /**
     * @param Model $model
     *
     * @return int|null
     */
    public function saveInsert(Model $model)
    {
        $data = $model->__toRawArray();
        $this->insert($data);

        if ($model->hasPrimaryKey()) {
            return $model->getPrimaryKeys();
        }
        return $this->getLastInsertValue();
    }


    /**
     * @param Model $model
     * @param Model $oldModel
     *
     * @return int
     */
    public function saveUpdate(Model $model, Model $oldModel)
    {
        return $this->update(
            $model->__toRawArray(),
            $model->getPrimaryKeys(),
            $oldModel->__toRawArray()
        );
    }

    /**
     * @param array $data
     * @param null  $id
     *
     * @return int
     */
    public function insert($data, &$id = null)
    {
        return parent::insert($data);
    }

    /**
     * @param array       $data
     * @param null        $where
     * @param array|Model $oldData
     *
     * @return int
     */
    public function update($data, $where = null, $oldData = [])
    {
        $data = array_filter($data);
        #!\Kint::dump($data, $oldData, $where);exit;
        return parent::update($data, $where);
    }

    /**
     * @param Filter $filter
     *
     * @return Model|null
     */
    public function fetchFilter(
        Filter $filter
    ): ?Model {
        return $this->fetch(
            $filter->getOffset(),
            $filter->getWheres(),
            $filter->getOrder(),
            $filter->getOrderDirection(),
            $filter->getJoins()
        );
    }

    /**
     * @param int|null $offset
     * @param array    $wheres
     * @param null     $order
     * @param string   $direction
     * @param array    $joins
     *
     * @return Model|null
     */
    public function fetch(
        int $offset = null,
        array $wheres = [],
        $order = null,
        string $direction = Select::ORDER_ASCENDING,
        array $joins = []
    ): ?Model {

        $select = $this->getSql()->select();
        $select->limit(1);
        if (is_numeric($offset)) {
            $select->offset($offset);
        }
        $select = $this->addJoinsToSelect($select, $joins);
        $select = $this->addWheresToSelect($select, $wheres);
        $select = $this->addOrdering($select, $order, $direction);

        $resultSet = $this->selectWith($select);
        if (count($resultSet) === 0) {
            return null;
        }
        return $resultSet->current();
    }

    /**
     * @param Filter $filter
     * @param bool   $doCount
     *
     * @return array
     */
    public function fetchAllFilter(Filter $filter,bool $doCount = true)
    {
        return $this->fetchAll(
            $filter->getLimit(),
            $filter->getOffset(),
            $filter->getWheres(),
            $filter->getOrder(),
            $filter->getOrderDirection(),
            $filter->getJoins(),
            $doCount
        );
    }

    /**
     * This method is only supposed to be used by getListAction.
     *
     * @param int|null               $limit     Number to limit to
     * @param int|null               $offset    Offset of limit statement. Is ignored if limit not set.
     * @param array                  $wheres    Array of conditions to filter by.
     * @param string|Expression|null $order     Column to order on
     * @param string|null            $direction Direction to order on (SELECT::ORDER_ASCENDING|SELECT::ORDER_DESCENDING)
     * @param array                  $joins     Array of join objects for joining tables in query
     * @param bool                   $doCount   Save some time by not counting if this is false
     *
     * @return array [ResultSet,int] Returns an array of resultSet,total_found_rows
     */
    public function fetchAll(
        int $limit = null,
        int $offset = null,
        array $wheres = [],
        $order = null,
        string $direction = Select::ORDER_ASCENDING,
        array $joins = [],
        bool $doCount = true
    ) {
        /** @var Select $select */
        $select = $this->getSql()->select();

        if ($limit !== null && is_numeric($limit)) {
            $select->limit(intval($limit));
            if ($offset !== null && is_numeric($offset)) {
                $select->offset($offset);
            }
        }

        $select = $this->addJoinsToSelect($select, $joins);
        $select = $this->addWheresToSelect($select, $wheres);

        if ($order !== null) {
            if ($order instanceof Expression) {
                $select->order($order);
            } else {
                $select->order("{$order} {$direction}");
            }
        }

        $resultSet = $this->selectWith($select);

        if($doCount) {
            $quantifierSelect = $select
                ->reset(Select::LIMIT)
                ->reset(Select::COLUMNS)
                ->reset(Select::OFFSET)
                ->reset(Select::ORDER)
                ->reset(Select::COMBINE)
                ->columns(['total' => new Expression('COUNT(*)')]);

            /* execute the select and extract the total */
            $row = $this->getSql()
                ->prepareStatementForSqlObject($quantifierSelect)
                ->execute()
                ->current();
            $total = (int)$row['total'];
        } else {
            $total = null;
        }

        return [$resultSet, $total];
    }

    public function fetchDistinctFilter(string $distinctColumn, Filter $filter)
    {
        return $this->fetchDistinct(
            $distinctColumn,
            $filter->getWheres(),
            $filter->getJoins()
        );
    }

    /**
     * This method is only supposed to be used by getListAction.
     *
     * @param string $distinctColumn Column to be distinct on.
     * @param array  $wheres         Array of conditions to filter by.
     * @param array  $joins          Array of join objects for joining tables in query
     *
     * @return array [ResultSet,int] Returns an array of resultSet,total_found_rows
     */
    public function fetchDistinct(
        string $distinctColumn,
        array $wheres = [],
        array $joins = []
    ) {
        /** @var Select $select */
        $select = $this->getSql()->select();
        $select->quantifier(Select::QUANTIFIER_DISTINCT);
        $select->columns([$distinctColumn]);

        $select = $this->addJoinsToSelect($select, $joins);
        $select = $this->addWheresToSelect($select, $wheres);

        $resultSet = $this->selectWith($select);

        $quantifierSelect = $select
            ->reset(Select::LIMIT)
            ->reset(Select::COLUMNS)
            ->reset(Select::OFFSET)
            ->reset(Select::ORDER)
            ->reset(Select::COMBINE)
            ->columns(['total' => new Expression('COUNT(*)')]);

        /* execute the select and extract the total */
        $row = $this->getSql()
            ->prepareStatementForSqlObject($quantifierSelect)
            ->execute()
            ->current();
        $total = (int)$row['total'];

        return [$resultSet, $total];
    }

    /**
     * @return array|\ArrayObject|null
     */
    public function fetchRandom()
    {
        $resultSet = $this->select(function (Select $select) {
            $select->order(new Expression('RAND()'))->limit(1);
        });

        if (0 == count($resultSet)) {
            return null;
        }
        return $resultSet->current();
    }

    /**
     * @param array|Select $where
     * @param array|string $order
     * @param int          $offset
     *
     * @return array|\ArrayObject|null|Model
     */
    public function fetchRow($where = null, $order = null, $offset = null)
    {
        if ($where instanceof Select) {
            $resultSet = $this->selectWith($where);
        } else {
            $resultSet = $this->select(function (Select $select) use ($where, $order, $offset) {
                if (!is_null($where)) {
                    $select->where($where);
                }
                if (!is_null($order)) {
                    $select->order($order);
                }
                if (!is_null($offset)) {
                    $select->offset($offset);
                }
                $select->limit(1);
            });
        }

        return (count($resultSet) > 0) ? $resultSet->current() : null;
    }

    /**
     * @param Where[]|PredicateInterface[] $wheres
     *
     * @return int
     */
    public function getCount($wheres = [], $joins = [])
    {
        $select = $this->getSql()->select();
        $select->columns(['total' => new Expression('IFNULL(COUNT(*),0)')]);

        $select = $this->addJoinsToSelect($select, $joins);
        $select = $this->addWheresToSelect($select, $wheres);

        #\Kint::dump($this->getSql()->getSqlStringForSqlObject($select));

        $row = $this->getSql()
            ->prepareStatementForSqlObject($select)
            ->execute()
            ->current();

        return !is_null($row) ? $row['total'] : 0;
    }

    /**
     * @param string                       $field
     * @param Where[]|PredicateInterface[] $wheres
     * @param Where[]|PredicateInterface[] $wheres
     *
     * @return int
     */
    public function getCountUnique(string $field, $wheres = [], $joins = [])
    {
        $select = $this->getSql()->select();
        $select->columns(['total' => new Expression('DISTINCT ' . $field)]);


        $select = $this->addJoinsToSelect($select, $joins);
        $select = $this->addWheresToSelect($select, $wheres);

        $row = $this->getSql()
            ->prepareStatementForSqlObject($select)
            ->execute()
            ->current();

        return !is_null($row) ? $row['total'] : 0;
    }

    public function getPrimaryKeys()
    {
        /** @var Model $oModel */
        $oModel = $this->getNewMockModelInstance();
        return array_keys($oModel->getPrimaryKeys());
    }

    public function getAutoIncrementKeys()
    {
        /** @var Model $oModel */
        $oModel = $this->getNewMockModelInstance();
        return array_keys($oModel->getAutoIncrementKeys());
    }

    /**
     * Returns an array of all primary keys on the table keyed by the column.
     *
     * @return array
     */
    public function getHighestPrimaryKey()
    {
        $highestPrimaryKeys = [];
        foreach ($this->getPrimaryKeys() as $primaryKey) {
            $Select = $this->getSql()->select();
            $Select->columns(['max' => new Expression("MAX({$primaryKey})")]);
            $row = $this->getSql()
                ->prepareStatementForSqlObject($Select)
                ->execute()
                ->current();

            $highestPrimaryKey = !is_null($row) ? $row['max'] : 0;
            $highestPrimaryKeys[$primaryKey] = $highestPrimaryKey;
        }
        return $highestPrimaryKeys;
    }

    /**
     * Returns an array of all autoincrement keys on the table keyed by the column.
     *
     * @return array
     */
    public function getHighestAutoincrementKey()
    {
        $highestAutoIncrementKeys = [];
        foreach ($this->getPrimaryKeys() as $autoIncrementKey) {
            $Select = $this->getSql()->select();
            $Select->columns(['max' => new Expression("MAX({$autoIncrementKey})")]);
            $row = $this->getSql()
                ->prepareStatementForSqlObject($Select)
                ->execute()
                ->current();

            $highestAutoIncrementKey = !is_null($row) ? $row['max'] : 0;
            $highestAutoIncrementKeys[$autoIncrementKey] = $highestAutoIncrementKey;
        }
        return $highestAutoIncrementKeys;
    }

    /**
     * @param $id
     *
     * @return Model|false
     */
    public function getById($id)
    {
        return $this->getByField('id', $id);
    }

    /**
     * @param $field
     * @param $value
     * @param $orderBy        string Field to sort by
     * @param $orderDirection string Direction to sort (Select::ORDER_ASCENDING || Select::ORDER_DESCENDING)
     *
     * @return array|\ArrayObject|null
     */
    public function getByField($field, $value, $orderBy = null, $orderDirection = Select::ORDER_ASCENDING)
    {
        $select = $this->sql->select();

        $select->where([$field => $value]);
        if ($orderBy) {
            $select->order("{$orderBy} {$orderDirection}");
        }
        $select->limit(1);

        $resultSet = $this->selectWith($select);

        $row = $resultSet->current();
        if (!$row) {
            return null;
        }
        return $row;
    }

    /**
     * @param string $field
     * @param        $value
     * @param        $limit          int
     * @param        $orderBy        string Field to sort by
     * @param        $orderDirection string Direction to sort (Select::ORDER_ASCENDING || Select::ORDER_DESCENDING)
     *
     * @return array|\ArrayObject|null
     */
    public function getManyByField(string $field, $value, int $limit = null, string $orderBy = null, string $orderDirection = Select::ORDER_ASCENDING)
    {
        $select = $this->sql->select();

        $select->where([$field => $value]);
        if ($orderBy) {
            $select->order("{$orderBy} {$orderDirection}");
        }

        if ($limit) {
            $select->limit($limit);
        }

        $resultSet = $this->selectWith($select);

        $results = [];
        if ($resultSet->count() == 0) {
            return null;
        }
        for ($i = 0; $i < $resultSet->count(); $i++) {
            $row = $resultSet->current();
            $results[] = $row;
            $resultSet->next();
        }

        return $results;
    }

    public function countByField(string $field, $value)
    {
        $select = $this->sql->select();
        $select->where([$field => $value]);
        $select->columns([
            new Expression('COUNT(*) as count')
        ]);
        $statement = $this->sql->prepareStatementForSqlObject($select);
        $result = $statement->execute();

        $data = $result->current();

        return $data['count'];
    }

    /**
     * @param array $primaryKeys
     *
     * @return array|\ArrayObject|null
     */
    public function getByPrimaryKey(array $primaryKeys)
    {
        $row = $this->select($primaryKeys)->current();
        if (!$row) {
            return null;
        }
        return $row;
    }

    /**
     * Get single matching object.
     *
     * @param Where|\Closure|string|array|Predicate\PredicateInterface $keyValue
     * @param null                                                     $orderBy
     * @param string                                                   $orderDirection
     *
     * @return array|\ArrayObject|null
     */
    public function getMatching($keyValue = [], $orderBy = null, $orderDirection = Select::ORDER_ASCENDING)
    {
        $select = $this->sql->select();
        $select->where($keyValue);
        if ($orderBy) {
            $select->order("{$orderBy} {$orderDirection}");
        }
        $select->limit(1);

        $resultSet = $this->selectWith($select);

        $row = $resultSet->current();
        if (!$row) {
            return null;
        }
        return $row;
    }

    /**
     * Get many matching objects.
     *
     * @param Where|\Closure|string|array|Predicate\PredicateInterface $keyValue
     * @param null                                                     $orderBy
     * @param string                                                   $orderDirection
     * @param int                                                      $limit
     *
     * @return array|\ArrayObject|null
     */
    public function getManyMatching($keyValue = [], $orderBy = null, $orderDirection = Select::ORDER_ASCENDING, int $limit = null)
    {
        $select = $this->sql->select();
        $select->where($keyValue);
        if ($orderBy) {
            $select->order("{$orderBy} {$orderDirection}");
        }
        if ($limit) {
            $select->limit($limit);
        }
        $resultSet = $this->selectWith($select);

        $results = [];
        if ($resultSet->count() == 0) {
            return null;
        }
        for ($i = 0; $i < $resultSet->count(); $i++) {
            $row = $resultSet->current();
            $results[] = $row;
            $resultSet->next();
        }

        return $results;
    }

    /**
     * @param array $data
     *
     * @return Model
     */
    public function getNewModelInstance(array $data = [])
    {
        $model = $this->model;
        return new $model($data);
    }

    /**
     * @param Select $select
     *
     * @return Model[]
     */
    public function getBySelect(Select $select)
    {
        $resultSet = $this->executeSelect($select);
        $return = [];
        foreach ($resultSet as $result) {
            $return[] = $result;
        }
        return $return;
    }

    /**
     * @param Select $select
     *
     * @return Model[]
     */
    public function getBySelectRaw(Select $select)
    {
        $resultSet = $this->executeSelect($select);
        $return = [];
        while ($result = $resultSet->getDataSource()->current()) {
            $return[] = $result;
            $resultSet->getDataSource()->next();
        }
        return $return;
    }

    /**
     * @return string
     */
    protected function getModelName()
    {
        $modelName = explode("\\", $this->model);
        $modelName = end($modelName);
        $modelName = str_replace("Model", "", $modelName);
        return $modelName;
    }

    /**
     * @param array  $wheres
     * @param Select $select
     *
     * @return Select
     */
    private function addWheresToSelect(Select $select, array $wheres = []): Select
    {
        foreach ($wheres as $conditional) {
            if (
                $conditional instanceof \Closure
                || $conditional instanceof Where
                || $conditional instanceof PredicateInterface
            ) {
                $select->where($conditional);
            } else {
                $spec = function (Where $where) use ($conditional) {
                    $column = (empty($conditional["table"]) ? '' : "{$conditional["table"]}.") . $conditional["column"];
                    switch ($conditional['condition']) {
                        case FilterCondition::CONDITION_EQUAL:
                            $where->equalTo($column, $conditional['value']);
                            break;
                        case FilterCondition::CONDITION_NOT_EQUAL:
                            $where->notEqualTo($column, $conditional['value']);
                            break;
                        case FilterCondition::CONDITION_GREATER_THAN:
                            $where->greaterThan($column, $conditional['value']);
                            break;
                        case FilterCondition::CONDITION_GREATER_THAN_OR_EQUAL:
                            $where->greaterThanOrEqualTo($column, $conditional['value']);
                            break;
                        case FilterCondition::CONDITION_LESS_THAN:
                            $where->lessThan($column, $conditional['value']);
                            break;
                        case FilterCondition::CONDITION_LESS_THAN_OR_EQUAL:
                            $where->lessThanOrEqualTo($column, $conditional['value']);
                            break;
                        case FilterCondition::CONDITION_LIKE:
                            $where->like($column, $conditional['value']);
                            break;
                        case FilterCondition::CONDITION_NOT_LIKE:
                            $where->notLike($column, $conditional['value']);
                            break;
                        case FilterCondition::CONDITION_IN:
                            $where->in($column, $conditional['value']);
                            break;
                        case FilterCondition::CONDITION_NOT_IN:
                            $where->notIn($column, $conditional['value']);
                            break;

                        default:
                            // @todo better exception plz.
                            throw new \Exception("Cannot work out what conditional '{$conditional['condition']}' is supposed to do in Zend... Probably unimplemented?");
                    }
                };
                $select->where($spec);
            }
        }
        return $select;
    }

    /**
     * @param array  $joins
     * @param Select $select
     */
    private function addJoinsToSelect(Select $select, array $joins = []): Select
    {
        foreach ($joins as $join) {
            $select->join(
                $join["tableFrom"],
                "{$join["tableFrom"]}.{$join["fieldFrom"]} = {$join["tableTo"]}.{$join["fieldTo"]}",
                $join["columns"],
                $join["type"]);
        }
        return $select;
    }

    /**
     * @param Select $select
     * @param        $order
     * @param        $direction
     *
     * @return Select
     */
    private function addOrdering(Select $select, $order, $direction): Select
    {
        if ($order !== null) {
            if ($order instanceof Expression) {
                $select->order($order);
            } else {
                $select->order("{$order} {$direction}");
            }
        }
        return $select;
    }
}
