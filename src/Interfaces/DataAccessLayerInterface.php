<?php

namespace Gone\AppCore\Interfaces;

use Gone\SDK\Common\Abstracts\AbstractModel;
use Gone\AppCore\QueryBuilder\Query;

interface DataAccessLayerInterface
{
    public function get(int $id, bool $hydrate = false);

    public function getAll(Query $filter = null, array $fields = null, bool $hydrate = false);

    public function save(AbstractModel $model, bool $hydrate = false);

    public function create(AbstractModel $model, bool $hydrate = false);

    public function update(AbstractModel $model, bool $hydrate = false);

    public function count(Query $filter = null);
}