<?php
namespace Gone\AppCore\Interfaces;

use Zend\Db\Sql\Expression;

interface ServiceInterface
{
    /**
     * @param int|null           $offset
     * @param array|null         $wheres
     * @param string|Expression| null $order
     * @param string|null        $orderDirection
     * @param array|null         $joins
     *
     * @return ModelInterface|null
     */
    public function get(
        int $offset = null,
        array $wheres = [],
        $order = null,
        string $orderDirection = null,
        array $joins = []
    );

    /**
     * @param int|null           $limit
     * @param int|null           $offset
     * @param array|null         $wheres
     * @param string|Expression| null $order
     * @param string|null        $orderDirection
     * @param array|null         $joins
     *
     * @return ModelInterface[]
     */
    public function getAll(
        int $limit = null,
        int $offset = null,
        array $wheres = [],
        $order = null,
        string $orderDirection = null,
        array $joins = []
    );

    public function getById(int $id);

    public function getByField(string $field, $value);

    public function getRandom();
}
