<?php

namespace Gone\AppCore\Abstracts;

use Gone\SDK\Common\Abstracts\AbstractModel;
use Gone\AppCore\Abstracts\Cleaner as AbstractCleaner;
use Gone\AppCore\QueryBuilder\Query;
use Gone\AppCore\Validator\AbstractValidator;
use Exception;

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

    protected function getAccessLayer()
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
     * @param Query $filter
     *
     * @return mixed|null
     * @throws Exception
     */
    public function get(Query $filter)
    {
        return $this->getAccessLayer()
            ->get($filter);
    }

    /**
     * @param Query|null $filter
     *
     * @return array
     * @throws Exception
     */
    public function getAll(Query $filter = null): array
    {
        return $this->getAccessLayer()
            ->getAll($filter);
    }

    /**
     * @param Query|null $filter
     *
     * @return int
     * @throws Exception
     */
    public function count(Query $filter = null): int
    {
        return $this->getAccessLayer()
            ->count($filter);
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
        return $this->getAccessLayer()->getAllField($field, $filter, $type);
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
        return $this->getAccessLayer()->getAllFields($fields, $filter, $types);
    }
}
