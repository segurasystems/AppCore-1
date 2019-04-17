<?php

namespace Gone\AppCore\Abstracts;

use Gone\SDK\Common\Abstracts\AbstractModel;
use Gone\AppCore\Abstracts\Cleaner as AbstractCleaner;
use Gone\AppCore\Validator\AbstractValidator;
use Exception;
use Gone\SDK\Common\Filters\Filter;
use Zend\Db\Sql\Predicate\In;
use Zend\Db\Sql\Predicate\Like;
use Zend\Db\Sql\Predicate\NotIn;
use Zend\Db\Sql\Predicate\NotLike;
use Zend\Db\Sql\Predicate\Operator;
use Zend\Db\Sql\Predicate\PredicateInterface;
use Zend\Db\Sql\Predicate\PredicateSet;
use Zend\Db\Sql\Select;

abstract class Service
{
    /** @var TableAccessLayer */
    private $tableAccessLayer;
    /** @var AbstractValidator */
    private $validator;
    /** @var AbstractCleaner */
    private $cleaner;

    protected $modelClass;

    public function __construct(
        TableAccessLayer $tableAccessLayer,
        AbstractValidator $validator = null,
        AbstractCleaner $cleaner = null
    ) {
        $this->tableAccessLayer = $tableAccessLayer;
        $this->validator = $validator;
        $this->cleaner = $cleaner;
        $this->__afterConstruct();
    }

    protected function __afterConstruct()
    {

    }

    protected final function getSelect(string $alias = null): Select
    {
        return $this->getAccessLayer()->getSelect($alias);
    }

    protected final function getAccessLayer(): TableAccessLayer
    {
        return $this->tableAccessLayer;
    }

    /**
     * @param AbstractModel $model
     * @param string        $scenario
     *
     * @return bool
     */
    public function validate(AbstractModel $model, string $scenario = AbstractValidator::SCENARIO_DEFAULT)
    {
        if ($this->validator instanceof AbstractValidator) {
            return $this->validator->validate($model, $scenario);
        }
        return true;
    }

    public function validateData(array $data, string $scenario = AbstractValidator::SCENARIO_DEFAULT)
    {
        $model = new $this->modelClass($data);
        return $this->validate($model, $scenario);
    }

    /**
     * @return array
     */
    public function getValidationErrors()
    {
        if ($this->validator instanceof AbstractValidator) {
            return $this->validator->getErrors();
        }
        return [];
    }

    public function clean(AbstractModel $model)
    {
        if ($this->cleaner instanceof AbstractCleaner) {
            $this->cleaner->clean($model);
        }
    }

    /**
     * @param AbstractModel $model
     *
     * @return AbstractModel|null|false
     */
    public function save(AbstractModel $model)
    {
        $this->clean($model);
        if ($this->validate($model)) {
            return $this->getAccessLayer()->save($model);
        }
        return false;
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
     * @param Filter $filter
     *
     * @return mixed|null
     * @throws Exception
     */
    public function get(Filter $filter)
    {
        return $this->getAccessLayer()
            ->get($this->autoFilterToSelect($filter));
    }

    /**
     * @param Filter|null $filter
     *
     * @return array
     * @throws Exception
     */
    public function getAll(Filter $filter = null): array
    {
        return $this->getAccessLayer()
            ->getAll($this->autoFilterToSelect($filter));
    }

    /**
     * @param Filter|null $filter
     *
     * @return int
     * @throws Exception
     */
    public function count(Filter $filter = null): int
    {
        return $this->getAccessLayer()
            ->count($this->autoFilterToSelect($filter));
    }

    protected function autoFilterToSelect(Filter $filter, $columnMap = []): Select
    {
        $select = new Select($this->tableAccessLayer->getTable());
        if (!empty($filter->getLimit())) {
            $select->limit($filter->getLimit());
        }
        if (!empty($filter->getOffset())) {
            $select->offset($filter->getOffset());
        }
        if (!empty($filter->getOrder())) {
            $select->order($filter->getOrder());
        }
        $predicates = [];
        foreach ($filter->getParameters() as $field => $value) {
            if (!empty($columnMap)) {
                if (isset($columnMap[$field])) {
                    $field = $columnMap[$field];
                } else {
                    continue;
                }
            }
            $predicates[] = $this->filterVarToPredicate($field, $value);
        }
        $basePredicate = $this->getBasePredicate();
        if (!empty($basePredicate)) {
            if (!empty($predicates)) {
                $basePredicate->addPredicate(new PredicateSet($predicates, $filter->getCombination()));
            }
            $select->where($basePredicate);
        } elseif (!empty($predicates)) {
            $select->where(new PredicateSet($predicates, $filter->getCombination()));
        }
        if (!empty($filter->getColumns())) {
            $select->columns($filter->getColumns());
        }
        return $select;
    }

    protected function getBasePredicate(): ?PredicateSet
    {
        return null;
    }

    protected function filterVarToPredicate($column, $value, $negative = false): ?PredicateInterface
    {
        if (is_array($value)) {
            if ($negative) {
                return new NotIn($column, $value);
            } else {
                return new In($column, $value);
            }
        } elseif (is_string($value)) {
            $first = substr($value, 0, 1);
            $last = substr($value, -1);
            if ($first === "%" || $last === "%") {
                if ($negative) {
                    return new NotLike($column, $value);
                } else {
                    return new Like($column, $value);
                }
            } elseif ($first === ">") {
                if ($negative) {
                    return new Operator($column, Operator::OP_LT, $value);
                } else {
                    return new Operator($column, Operator::OP_GT, $value);
                }
            } elseif ($first === "<") {
                if ($negative) {
                    return new Operator($column, Operator::OP_GT, $value);
                } else {
                    return new Operator($column, Operator::OP_LT, $value);
                }
            } elseif ($first === "!") {
                if ($negative) {
                    return new Operator($column, Operator::OP_EQ, $value);
                } else {
                    return new Operator($column, Operator::OP_NE, $value);
                }
            }
        }
        if ($negative) {
            return new Operator($column, Operator::OP_NE, $value);
        } else {
            return new Operator($column, Operator::OP_EQ, $value);
        }
    }
}
