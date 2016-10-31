<?php
namespace Segura\AppCore\Abstracts;

use Segura\AppCore\Exceptions\TableGatewayException;
use Segura\AppCore\Exceptions\TableGatewayRecordNotFoundException;
use Segura\AppCore\Filters\FilterCondition;
use Zend\Db\Adapter\Exception\InvalidQueryException;
use Zend\Db\ResultSet\ResultSet;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Select;
use Zend\Db\Sql\Where;
use Zend\Db\TableGateway\TableGateway as ZendTableGateway;

abstract class TableGateway extends ZendTableGateway
{
    protected $model;

    /**
     * @param Model $model
     *
     * @throws TableGatewayException
     *
     * @return array|\ArrayObject|null
     */
    public function save(Model $model)
    {
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
        $data = $model->__toArray();
        $this->insert($data);

        if ($model->hasPrimaryKey()) {
            return $model->getPrimaryKeys();
        } else {
            return $this->getLastInsertValue();
        }
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
            $model->__toArray(),
            $model->getPrimaryKeys(),
            $oldModel->__toArray()
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
        return parent::update($data, $where);
    }

    /**
     * This method is only supposed to be used by getListAction.
     *
     * @param int    $limit     Number to limit to
     * @param int    $offset    Offset of limit statement. Is ignored if limit not set.
     * @param array  $wheres    Array of conditions to filter by.
     * @param string $order     Column to order on
     * @param string $direction Direction to order on (SELECT::ORDER_ASCENDING|SELECT::ORDER_DESCENDING)
     *
     * @return array [ResultSet,int] Returns an array of resultSet,total_found_rows
     */
    public function fetchAll(
        int $limit = null,
        int $offset = null,
        array $wheres = null,
        string $order = null,
        string $direction = Select::ORDER_ASCENDING
    )
    {
        /** @var Select $select */
        $select = $this->getSql()->select();

        if ($limit !== null && is_numeric($limit)) {
            $select->limit(intval($limit));
            if($offset !== null && is_numeric($offset)){
                $select->offset($offset);
            }
        }
        #\Kint::dump($limit, $offset, $wheres, $order, $direction);
        if($wheres != null) {
            #var_dump($wheres);exit;
            #exit;
            foreach ($wheres as $conditional) {
                $spec = function(Where $where) use ($conditional){
                    switch($conditional['condition']){
                        case FilterCondition::CONDITION_EQUAL:
                            $where->equalTo($conditional['column'], $conditional['value']);
                            break;
                        case FilterCondition::CONDITION_GREATER_THAN:
                            $where->greaterThan($conditional['column'], $conditional['value']);
                            break;
                        case FilterCondition::CONDITION_GREATER_THAN_OR_EQUAL:
                            $where->greaterThanOrEqualTo($conditional['column'], $conditional['value']);
                            break;
                        case FilterCondition::CONDITION_LESS_THAN:
                            $where->lessThan($conditional['column'], $conditional['value']);
                            break;
                        case FilterCondition::CONDITION_LESS_THAN_OR_EQUAL:
                            $where->lessThanOrEqualTo($conditional['column'], $conditional['value']);
                            break;#
                        case FilterCondition::CONDITION_LIKE:
                            $where->like($conditional['column'], $conditional['value']);
                            break;
                        default:
                            // @todo better exception plz.
                            throw new \Exception("Cannot work out what conditional {$conditional['condition']} is supposed to do in Zend... Probably unimplemented?");
                    }
                };
                $select->where($spec);
            }
        }

        if ($order !== null) {
            $select->order("{$order} {$direction}");
        }

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
     * @throws TableGatewayException
     *
     * @return array|\ArrayObject|null
     */
    public function fetchRandom()
    {
        $resultSet = $this->select(function (Select $select) {
            $select->order(new Expression('RAND()'))->limit(1);
        });

        if (0 == count($resultSet)) {
            throw new TableGatewayException("No data found in table!");
        }

        return $resultSet->current();
    }

    /**
     * @param array|Select $where
     * @param array|string $order
     * @param int          $offset
     *
     * @throws TableGatewayException
     *
     * @return array|\ArrayObject|null|Model
     */
    public function fetchRow($where = null, $order = null, $offset = null)
    {
        if ($where instanceof Select) {
            $resultSet = $this->selectWith($where);
        } else {
            $resultSet = $this->select(function (Select $select)
            use ($where, $order, $offset) {
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

    public function getCount($where = [])
    {
        $Select = $this->getSql()->select();
        $Select->columns(['total' => new Expression('IFNULL(COUNT(*),0)')])->where($where);

        $row = $this->getSql()
            ->prepareStatementForSqlObject($Select)
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

            $highestPrimaryKey               = !is_null($row) ? $row['max'] : 0;
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

            $highestAutoIncrementKey                     = !is_null($row) ? $row['max'] : 0;
            $highestAutoIncrementKeys[$autoIncrementKey] = $highestAutoIncrementKey;
        }
        return $highestAutoIncrementKeys;
    }

    /**
     * @param $id
     *
     * @throws TableGatewayException
     *
     * @return Model
     */
    public function getById($id)
    {
        try {
            return $this->getByField('id', $id);
        } catch (TableGatewayException $tge) {
            throw new TableGatewayException("Cannot find record by ID '{$id}'");
        }
    }

    /**
     * @param $field
     * @param $value
     *
     * @throws TableGatewayRecordNotFoundException
     *
     * @return array|\ArrayObject|null
     */
    public function getByField($field, $value)
    {
        $row = $this->select([$field => $value])->current();
        if (!$row) {
            throw new TableGatewayRecordNotFoundException("Could not find record by ['{$field}' => '{$value}']");
        }
        return $row;
    }

    /**
     * @param array $primaryKeys
     *
     * @throws TableGatewayException
     *
     * @return array|\ArrayObject|null
     */
    public function getByPrimaryKey(array $primaryKeys)
    {
        $row = $this->select($primaryKeys)->current();
        if (!$row) {
            throw new TableGatewayRecordNotFoundException("Could not find record by primary keys: " . var_export($primaryKeys, true) . ".");
        }
        return $row;
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
        $return    = [];
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
        $return    = [];
        while ($result = $resultSet->getDataSource()->current()) {
            $return[] = $result;
            $resultSet->getDataSource()->next();
        }
        return $return;
    }
}
