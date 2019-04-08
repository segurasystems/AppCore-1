<?php

namespace Gone\AppCore\QueryBuilder;

use JsonSerializable;

class ConditionGroup implements JsonSerializable
{
    const AND = "AND";
    const OR = "OR";

    const COMPARATORS
        = [
            self:: AND,
            self:: OR,
        ];

    private $conditions = [];
    private $comparator = self:: AND;

    public function __construct(string $comparator, array $conditions = [])
    {
        $this->setComparator($comparator);
        $this->setConditions($conditions);
    }

    public static function CreateFromArray(array $array): ConditionGroup
    {
        $comparator = $array[0];
        if (!self::IsComparator($comparator)) {
            $comparator = self:: AND;
        } else {
            array_shift($array);
        }
        if (Condition::IsComparator($array[0])) {
            if (Condition::IsSingularComparator($array[0]) && is_array($array[2] ?? null)) {
                $set = [];
                foreach ($array[2] as $val) {
                    $_set = [$array[0], $array[1], $val];
                    if (isset($array[3])) {
                        $_set[] = $array[3];
                    }
                    $set[] = $_set;
                }
                $array = $set;
            } else {
                $array = [$array];
            }
        }
        return new self($comparator, $array);
    }

    public function jsonSerialize()
    {
        $array = [$this->getComparator()];
        foreach ($this->getConditions() as $condition) {
            $array[] = $condition;
        }
        return $array;
    }

    public static function IsComparator($comparator)
    {
        if (is_string($comparator)) {
            return in_array(strtoupper($comparator), self::COMPARATORS);
        }
        return false;
    }

    /**
     * @return mixed
     */
    public function getConditions(): array
    {
        return $this->conditions;
    }

    /**
     * @param array $conditions
     *
     * @return $this
     */
    public function setConditions(array $conditions)
    {
        $this->conditions = [];
        foreach ($conditions as $condition) {
            $this->addCondition($condition);
        }
        return $this;
    }

    public function addCondition($condition)
    {
        if ($condition instanceof Condition || $condition instanceof ConditionGroup) {
            $this->conditions[] = $condition;
        } elseif (is_array($condition)) {
            $this->conditions[] = $this->createCondition($condition);
        }
        return $this;
    }

    private function createCondition(array $array)
    {
        $first = $array[0];
        if (Condition::IsComparator($first)) {
            return Condition::CreateFromArray($array);
        } else {
            return self::CreateFromArray($array);
        }
    }

    /**
     * @return mixed
     */
    public function getComparator()
    {
        return $this->comparator;
    }

    /**
     * @param mixed $comparator
     *
     * @return ConditionGroup
     */
    public function setComparator(string $comparator)
    {
        $comparator = strtoupper($comparator);
        if (!in_array($comparator, self::COMPARATORS)) {
            $comparator = self:: AND;
        }
        $this->comparator = $comparator;
        return $this;
    }
}