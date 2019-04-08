<?php

namespace Gone\AppCore\QueryBuilder;

use JsonSerializable;

class Query implements JsonSerializable
{
    const ORDER_ASC = "ASC";
    const ORDER_DESC = "DESC";
    const ORDER_RAND = "RAND";

    private $limit = 0;
    private $offset = 0;
    private $joins = [];
    private $columns = [];
    private $distinct = false;
    private $order = [];
    private $grouping = [];
    private $baseTable = null;
    private $condition = null;
    private $typing = [];

    public function __construct(string $baseTable = null)
    {
        $this->from($baseTable);
    }

    public static function CreateFromJSON(string $json)
    {
        $array = json_decode($json, true) ?? [];
        return self::CreateFromArray($array);
    }

    public static function CreateFromArray(array $array)
    {
        return self::Factory()
            ->from($array["table"] ?? null)
            ->setLimit($array["limit"] ?? 0)
            ->setOffset($array["offset"] ?? 0)
            ->setJoins($array["joins"] ?? [])
            ->setColumns($array["columns"] ?? [])
            ->distinct($array["distinct"] ?? false)
            ->setOrder($array["order"] ?? [])
            ->setGrouping($array["grouping"] ?? [])
            ->where($array["condition"] ?? null)
            ->typing($array["typing"] ?? []);
    }

    public function isEmpty()
    {
        return
            empty($this->getBaseTable())
            && empty($this->getLimit())
            && empty($this->getOffset())
            && empty($this->getJoins())
            && empty($this->getColumns())
            && empty($this->isDistinct())
            && empty($this->getOrder())
            && empty($this->getGrouping())
            && empty($this->getTyping())
            && empty($this->getCondition());
    }

    public function jsonSerialize()
    {
        return array_filter([
            "limit"     => $this->getLimit(),
            "offset"    => $this->getOffset(),
            "joins"     => $this->getJoins(),
            "columns"   => $this->getColumns(),
            "distinct"  => $this->isDistinct(),
            "order"     => $this->getOrder(),
            "grouping"  => $this->getGrouping(),
            "condition" => $this->getCondition(),
            "table"     => $this->getBaseTable(),
            "typing"    => $this->getTyping(),
        ]);
    }

    public static function Factory(string $baseTable = null)
    {
        return new self($baseTable);
    }

    /**
     * @return mixed
     */
    public function getLimit(): int
    {
        return $this->limit;
    }


    public function limit(int $limit)
    {
        return $this->setLimit($limit);
    }

    /**
     * @param mixed $limit
     *
     * @return Query
     */
    private function setLimit(int $limit)
    {
        $this->limit = $limit;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getOffset(): int
    {
        return $this->offset;
    }

    public function offset(int $offset)
    {
        return $this->setOffset($offset);
    }

    /**
     * @param mixed $offset
     *
     * @return Query
     */
    private function setOffset(int $offset)
    {
        $this->offset = $offset;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getJoins(): array
    {
        return $this->joins;
    }

    private function setJoins(array $joins)
    {
        foreach ($joins as $join) {
            if (is_array($join)) {
                $this->addJoin(Join::CreateFromArray($join));
            } else {
                if ($join instanceof Join) {
                    $this->addJoin($join);
                }
            }
        }
        return $this;
    }


    private $_existingJoins = [];

    /**
     * @param Join $join
     *
     * @return Query
     */
    private function addJoin(Join $join)
    {
        $json = json_encode($join);
        if (!in_array($json, $this->_existingJoins)) {
            $this->joins[] = $join;
            $this->_existingJoins[] = $json;
        }
        return $this;
    }

    public function join(array $join, array $to, string $type = null, string $alias = null)
    {
        return $this->addJoin(new Join($join, $to, $type, $alias));
    }

    /**
     * @return mixed
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    public function columns(array $columns = [])
    {
        return $this->setColumns($columns);
    }

    /**
     * @param string|array $columns
     *
     * @return Query
     */
    private function setColumns($columns)
    {
        if (empty($columns)) {
            $columns = [];
        }
        if (!is_array($columns)) {
            $columns = [$columns];
        }
        $this->columns = $columns;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getOrder(): array
    {
        return $this->order;
    }

    public function order(string $field, string $direction = self::ORDER_ASC)
    {
        $this->order[$field] = $direction;
        return $this;
    }

    /**
     * @param array $order
     *
     * @return $this
     */
    public function setOrder(array $order = [])
    {
        $this->order = $order;
        return $this;
    }

    /**
     * @return array
     */
    public function getGrouping(): array
    {
        return $this->grouping;
    }

    public function groupBy(array $grouping)
    {
        return $this->setGrouping($grouping);
    }

    /**
     * @param mixed $grouping
     *
     * @return Query
     */
    private function setGrouping(array $grouping)
    {
        $this->grouping = $grouping;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getBaseTable(): ?string
    {
        return $this->baseTable;
    }

    /**
     * @param string|null $baseTable
     *
     * @return $this
     */
    public function from(string $baseTable = null)
    {
        $this->baseTable = $baseTable;
        return $this;
    }

    /**
     * @return ConditionGroup|null
     */
    public function getCondition(): ?ConditionGroup
    {
        return $this->condition;
    }

    /**
     * @param ConditionGroup $condition
     *
     * @return $this
     */
    public function setCondition(ConditionGroup $condition = null)
    {
        $this->condition = $condition;
        return $this;
    }

    /**
     * @param array|ConditionGroup $where
     *
     * @return Query
     */
    public function where($where)
    {
        if ($where instanceof ConditionGroup) {
            $condition = $where;
        } elseif (is_array($where)) {
            $condition = ConditionGroup::CreateFromArray($where);
        } else {
            return $this;
        }
        return $this->setCondition($condition);
    }

    public function distinct(bool $distinct = false)
    {
        $this->distinct = $distinct;
        return $this;
    }

    public function isDistinct(): bool
    {
        return $this->distinct;
    }

    public function typing(array $typing)
    {
        $this->typing = $typing;
        return $this;
    }

    public function getTyping(): array
    {
        return $this->typing;
    }

}